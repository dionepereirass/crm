<?php

namespace App\Services\Privacy;

use App\Models\AuditLog;
use App\Models\Message;
use App\Models\Player;
use Illuminate\Support\Facades\DB;

class DataExportService
{
    public function __construct(
        protected SensitiveDataSanitizer $sanitizer,
        protected AuditService $auditService
    ) {}

    /**
     * Gera pacote completo e estruturado de dados pessoais do jogador (Art. 18, II e V da LGPD).
     */
    public function export(Player $player, string $format = 'json', ?int $actorId = null): array|string
    {
        // 1. Dados cadastrais e perfil
        $playerData = [
            'id' => $player->id,
            'external_id' => $player->external_id,
            'name' => $player->name,
            'cpf' => $player->cpf,
            'email' => $player->email,
            'phone' => $player->phone,
            'birth_date' => $player->birth_date?->format('Y-m-d'),
            'city' => $player->city,
            'state' => $player->state,
            'zip_code' => $player->zip_code,
            'status' => $player->status,
            'source' => $player->source,
            'affiliate' => $player->affiliate,
            'registered_at' => $player->registered_at?->toIso8601String(),
            'last_login_at' => $player->last_login_at?->toIso8601String(),
            'created_at' => $player->created_at?->toIso8601String(),
        ];

        // 2. Consentimentos e Histórico
        $consents = $player->consents()->get()->map(function ($c) {
            return [
                'type' => $c->type ?? $c->channel,
                'channel' => $c->channel,
                'status' => $c->status,
                'is_granted' => (bool) $c->is_granted,
                'source' => $c->consent_source,
                'version' => $c->consent_version,
                'granted_at' => $c->granted_at?->toIso8601String() ?? $c->consent_date?->toIso8601String(),
                'revoked_at' => $c->revoked_at?->toIso8601String(),
            ];
        })->toArray();

        // 3. Tags associadas
        $tags = $player->tags()->get(['tags.id', 'tags.name'])->toArray();

        // 4. Mensagens enviadas
        $messages = Message::where('player_id', $player->id)
            ->where('platform_id', $player->platform_id)
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get(['id', 'channel', 'subject', 'status', 'created_at', 'sent_at'])
            ->toArray();

        // 5. Histórico de Eventos na plataforma (sanitizados)
        $events = $player->events()
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get()
            ->map(function ($e) {
                return [
                    'id' => $e->id,
                    'event_type' => $e->event_type ?? 'GENERIC',
                    'created_at' => $e->created_at?->toIso8601String(),
                    'payload' => $this->sanitizer->sanitize($e->payload ?? []),
                ];
            })
            ->toArray();

        // 6. Jornadas e Automações percorridas
        $automationRuns = $player->automationRuns()
            ->with('automation:id,name,trigger_type')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->map(function ($r) {
                return [
                    'id' => $r->id,
                    'automation_name' => $r->automation?->name,
                    'trigger_type' => $r->automation?->trigger_type,
                    'status' => $r->status,
                    'started_at' => $r->started_at?->toIso8601String(),
                    'completed_at' => $r->completed_at?->toIso8601String(),
                ];
            })
            ->toArray();

        // 7. Trilha de Auditoria vinculada ao titular
        $auditLogs = AuditLog::where('platform_id', $player->platform_id)
            ->where('resource_type', 'Player')
            ->where('resource_id', (string) $player->id)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get(['action', 'created_at', 'ip_address'])
            ->toArray();

        $bundle = [
            'export_metadata' => [
                'platform_id' => $player->platform_id,
                'exported_at' => now()->toIso8601String(),
                'standard' => 'LGPD (Lei 13.709/2018) - Direitos do Titular',
                'schema_version' => '1.0',
            ],
            'player' => $playerData,
            'consents' => $consents,
            'tags' => $tags,
            'messages' => $messages,
            'events' => $events,
            'automation_runs' => $automationRuns,
            'audit_history' => $auditLogs,
        ];

        // Auditoria da exportação
        $this->auditService->log(
            $player->platform_id,
            'DATA_EXPORT_GENERATED',
            'Player',
            $player->id,
            null,
            ['format' => $format, 'player_id' => $player->id],
            'USER',
            $actorId,
            request()?->ip(),
            request()?->userAgent()
        );

        if ($format === 'csv') {
            return $this->formatAsCsv($bundle);
        }

        return $bundle;
    }

    protected function formatAsCsv(array $bundle): string
    {
        $csv = "Categoria,Campo,Valor\n";
        foreach ($bundle['player'] as $field => $val) {
            $csv .= "Cadastro,{$field},\"{$val}\"\n";
        }
        foreach ($bundle['consents'] as $c) {
            $csv .= "Consentimento,{$c['type']},\"Status: {$c['status']}\"\n";
        }
        return $csv;
    }
}
