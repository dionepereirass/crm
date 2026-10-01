<?php

namespace App\Services\Reports;

use App\Models\Automation;
use App\Models\Campaign;
use App\Models\DataSubjectRequest;
use App\Models\Event;
use App\Models\Player;
use App\Services\Analytics\PeriodService;
use App\Services\Privacy\SensitiveDataSanitizer;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class ReportsService
{
    public function __construct(
        protected PeriodService $periodService,
        protected SensitiveDataSanitizer $sanitizer
    ) {}

    /**
     * Gera os dados tabulares do relatório solicitado.
     */
    public function generateReportData(int $platformId, string $reportType, array $filters = [], bool $maskPersonalData = true): array
    {
        $resolved = $this->periodService->resolvePeriod($filters);
        $start = $resolved['current']['start'];
        $end = $resolved['current']['end'];

        return match (strtoupper($reportType)) {
            'PLAYERS' => $this->generatePlayersReport($platformId, $start, $end, $maskPersonalData),
            'FINANCIAL' => $this->generateFinancialReport($platformId, $start, $end),
            'MARKETING' => $this->generateMarketingReport($platformId, $start, $end),
            'AUTOMATIONS' => $this->generateAutomationsReport($platformId, $start, $end),
            'PRIVACY' => $this->generatePrivacyReport($platformId, $start, $end),
            default => throw new \InvalidArgumentException("Tipo de relatório '{$reportType}' não suportado."),
        };
    }

    /**
     * Converte o conjunto de dados para formato CSV.
     */
    public function exportToCsv(array $data): string
    {
        if (empty($data)) {
            return "Nenhum dado encontrado para o relatório no período selecionado.\n";
        }

        $output = fopen('php://temp', 'r+');
        // UTF-8 BOM para compatibilidade com Excel
        fputs($output, "\xEF\xBB\xBF");

        // Cabeçalhos a partir das chaves do primeiro registro
        fputcsv($output, array_keys($data[0]), ';');

        foreach ($data as $row) {
            $cleanRow = $this->sanitizer->sanitizeCsvRow(array_values($row));
            fputcsv($output, $cleanRow, ';');
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    protected function generatePlayersReport(int $platformId, Carbon $start, Carbon $end, bool $mask): array
    {
        $players = Player::withoutGlobalScopes()
            ->where('platform_id', $platformId)
            ->whereBetween('created_at', [$start, $end])
            ->take(500)
            ->get();

        return $players->map(function ($p) use ($mask) {
            return [
                'ID' => $p->id,
                'External_ID' => $p->external_id,
                'Nome' => $p->name,
                'Email' => $mask ? $this->sanitizer->maskEmail($p->email) : $p->email,
                'Telefone' => $mask ? $this->sanitizer->maskPhone($p->phone) : $p->phone,
                'Status' => $p->status,
                'Data_Cadastro' => $p->created_at ? $p->created_at->format('d/m/Y H:i') : '—',
                'Ultimo_Login' => $p->last_login_at ? Carbon::parse($p->last_login_at)->format('d/m/Y H:i') : '—',
            ];
        })->all();
    }

    protected function generateFinancialReport(int $platformId, Carbon $start, Carbon $end): array
    {
        $events = Event::withoutGlobalScopes()
            ->where('platform_id', $platformId)
            ->whereBetween('occurred_at', [$start, $end])
            ->with(['eventType', 'player:id,name,external_id'])
            ->take(1000)
            ->get();

        return $events->map(function ($ev) {
            $payload = $ev->normalized_payload ?? $ev->payload ?? [];
            $amount = $payload['data']['amount'] ?? $payload['amount'] ?? 0.0;

            return [
                'ID_Evento' => $ev->id,
                'Tipo' => $ev->eventType?->key ?? 'EVENTO',
                'Jogador' => $ev->player?->name ?? "Jogador #{$ev->player_id}",
                'Valor' => 'R$ ' . number_format((float) $amount, 2, ',', '.'),
                'Data' => $ev->occurred_at ? Carbon::parse($ev->occurred_at)->format('d/m/Y H:i') : '—',
                'Status_Processamento' => $ev->processing_status,
            ];
        })->all();
    }

    protected function generateMarketingReport(int $platformId, Carbon $start, Carbon $end): array
    {
        $campaigns = Campaign::withoutGlobalScopes()
            ->where('platform_id', $platformId)
            ->whereBetween('created_at', [$start, $end])
            ->with(['metrics'])
            ->get();

        return $campaigns->map(function ($c) {
            $m = $c->metrics;
            $sent = $m->messages_sent ?? 0;
            $delivered = $m->messages_delivered ?? 0;
            $opened = $m->unique_opens ?? 0;
            $clicked = $m->unique_clicks ?? 0;

            return [
                'ID' => $c->id,
                'Campanha' => $c->name,
                'Canal' => $c->channel,
                'Status' => $c->status,
                'Enviados' => $sent,
                'Entregues' => $delivered,
                'Abertos' => $opened,
                'Cliques' => $clicked,
                'Taxa_Entrega' => $sent > 0 ? round(($delivered / $sent) * 100, 1) . '%' : '0%',
                'Taxa_Abertura' => $delivered > 0 ? round(($opened / $delivered) * 100, 1) . '%' : '0%',
                'CTR' => $delivered > 0 ? round(($clicked / $delivered) * 100, 1) . '%' : '0%',
                'Criada_Em' => $c->created_at ? $c->created_at->format('d/m/Y H:i') : '—',
            ];
        })->all();
    }

    protected function generateAutomationsReport(int $platformId, Carbon $start, Carbon $end): array
    {
        $automations = Automation::withoutGlobalScopes()
            ->where('platform_id', $platformId)
            ->withCount(['runs'])
            ->get();

        return $automations->map(function ($a) {
            return [
                'ID' => $a->id,
                'Nome' => $a->name,
                'Gatilho' => $a->trigger_type,
                'Status' => $a->status,
                'Total_Execucoes' => $a->runs_count,
                'Criada_Em' => $a->created_at ? $a->created_at->format('d/m/Y H:i') : '—',
            ];
        })->all();
    }

    protected function generatePrivacyReport(int $platformId, Carbon $start, Carbon $end): array
    {
        $requests = DataSubjectRequest::withoutGlobalScopes()
            ->where('platform_id', $platformId)
            ->whereBetween('created_at', [$start, $end])
            ->with(['player:id,name'])
            ->get();

        return $requests->map(function ($r) {
            return [
                'Protocolo' => $r->id,
                'UUID' => $r->uuid,
                'Titular' => $r->player?->name ?? "Jogador #{$r->player_id}",
                'Tipo_Direito' => $r->type,
                'Status' => $r->status,
                'Solicitado_Em' => $r->requested_at ? Carbon::parse($r->requested_at)->format('d/m/Y H:i') : '—',
                'Prazo_SLA' => $r->due_at ? Carbon::parse($r->due_at)->format('d/m/Y') : '—',
                'Concluido_Em' => $r->completed_at ? Carbon::parse($r->completed_at)->format('d/m/Y H:i') : '—',
            ];
        })->all();
    }
}
