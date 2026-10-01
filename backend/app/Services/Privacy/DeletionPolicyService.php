<?php

namespace App\Services\Privacy;

use App\Models\Player;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DeletionPolicyService
{
    public function __construct(
        protected PlayerAnonymizationService $anonymizationService,
        protected AuditService $auditService
    ) {}

    /**
     * Determina a ação apropriada para cada categoria de dados sob obrigações legais (Art. 16 da LGPD).
     */
    public function evaluateCategory(string $category): string
    {
        return match (strtoupper($category)) {
            'MARKETING_CONSENTS', 'TEMPORARY_SESSIONS', 'EXPORT_FILES' => 'DELETE',
            'PLAYER_IDENTIFIERS', 'CONTACT_DATA' => 'ANONYMIZE',
            'FINANCIAL_RECORDS', 'BETTING_HISTORY', 'AUDIT_LOGS' => 'RETAIN',
            default => 'ANONYMIZE',
        };
    }

    /**
     * Processa a solicitação de exclusão (Right to Delete) de forma segura e em conformidade regulatória.
     */
    public function processPlayerDeletion(Player $player, bool $confirmed = false, ?int $actorId = null, ?string $reason = null): array
    {
        if (!$confirmed) {
            throw new InvalidArgumentException("A solicitação de exclusão exige confirmação explícita.");
        }

        // Verifica se existem transações financeiras ou apostas registradas que exigem retenção fiscal/AML
        $hasFinancialEvents = $player->events()
            ->whereHas('eventType', function ($q) {
                $q->whereIn(DB::raw('UPPER(key)'), ['DEPOSIT_SUCCESS', 'WITHDRAWAL_SUCCESS', 'BET_PLACED', 'BET_SETTLED', 'DEPOSIT', 'WITHDRAWAL', 'BET']);
            })
            ->exists();

        if ($hasFinancialEvents) {
            // Cumprimento do Art. 16, I da LGPD (guarda para obrigação legal/regulatória): anonimiza os dados pessoais
            $this->anonymizationService->anonymize(
                $player,
                true,
                $actorId,
                $reason ?: 'Exclusão convertida em anonimização para cumprimento de obrigações legais fiscais/regulatórias'
            );

            return [
                'action_taken' => 'ANONYMIZED',
                'reason' => 'Dados anonimizados devido a obrigações legais de guarda fiscal/financeira (Lei 13.709/2018 Art. 16, I).',
                'player_id' => $player->id,
            ];
        }

        // Caso não haja registros fiscais/regulatórios obrigatórios, executa exclusão
        return DB::transaction(function () use ($player, $actorId, $reason) {
            $oldValues = [
                'id' => $player->id,
                'name' => $player->name,
                'email' => $player->email,
            ];

            // Remove tags e consentimentos
            $player->tags()->detach();
            $player->consents()->delete();

            // Soft-delete do player
            $player->delete();

            $this->auditService->log(
                $player->platform_id,
                'PLAYER_DELETED',
                'Player',
                $player->id,
                $oldValues,
                ['status' => 'DELETED', 'reason' => $reason ?: 'Direito de Exclusão LGPD atendido integralmente'],
                'USER',
                $actorId,
                request()?->ip(),
                request()?->userAgent()
            );

            return [
                'action_taken' => 'DELETED',
                'reason' => 'Jogador excluído com sucesso.',
                'player_id' => $player->id,
            ];
        });
    }
}
