<?php

namespace App\Services\Privacy;

use App\Enums\ConsentStatus;
use App\Models\Player;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PlayerAnonymizationService
{
    public function __construct(
        protected AuditService $auditService,
        protected ConsentPolicyService $consentPolicyService
    ) {}

    /**
     * Anonimiza irreversivelmente os dados pessoais de um jogador (Art. 16 e 18, VI da LGPD).
     * Preserva integridade relacional financeira, contábil e de auditoria legal.
     */
    public function anonymize(Player $player, bool $confirmed = false, ?int $actorId = null, ?string $reason = null): bool
    {
        if (!$confirmed) {
            throw new InvalidArgumentException("A anonimização exige confirmação explícita devido ao seu caráter irreversível.");
        }

        if ($player->status === 'ANONYMIZED') {
            return true;
        }

        return DB::transaction(function () use ($player, $actorId, $reason) {
            $oldValues = [
                'name' => $player->name,
                'email' => $player->email,
                'phone' => $player->phone,
                'cpf' => $player->cpf,
                'status' => $player->status,
            ];

            $anonHash = substr(hash('sha256', $player->external_id . $player->id . config('app.key')), 0, 12);
            $anonEmail = "anon_{$player->id}_{$anonHash}@anonymized.betcrm.local";

            // 1. Atualiza registro do jogador com valores anonimizados
            $player->update([
                'name' => "ANONYMIZED_USER_{$player->id}",
                'email' => $anonEmail,
                'phone' => '00000000000',
                'cpf' => null,
                'city' => null,
                'state' => null,
                'zip_code' => null,
                'birth_date' => null,
                'custom_fields' => null,
                'status' => 'ANONYMIZED',
            ]);

            // 2. Revoga todos os consentimentos ativos
            $activeConsents = $player->consents()->where('status', ConsentStatus::GRANTED->value)->get();
            foreach ($activeConsents as $consent) {
                $this->consentPolicyService->revokeConsent($player, $consent->type ?? $consent->channel, [
                    'source' => 'anonymization_procedure',
                    'reason' => 'Player anonymization requested',
                    'actor_type' => 'USER',
                    'actor_id' => $actorId,
                ]);
            }

            // 3. Remove associações de tags de marketing
            $player->tags()->detach();

            // 4. Registra auditoria da anonimização
            $this->auditService->log(
                $player->platform_id,
                'PLAYER_ANONYMIZED',
                'Player',
                $player->id,
                $oldValues,
                [
                    'status' => 'ANONYMIZED',
                    'anonymized_at' => now()->toIso8601String(),
                    'reason' => $reason ?: 'Direito de Anonimização LGPD exercido',
                ],
                'USER',
                $actorId,
                request()?->ip(),
                request()?->userAgent()
            );

            return true;
        });
    }
}
