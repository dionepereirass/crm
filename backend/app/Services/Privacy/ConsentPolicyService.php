<?php

namespace App\Services\Privacy;

use App\Enums\ConsentStatus;
use App\Enums\ConsentType;
use App\Models\Consent;
use App\Models\ConsentHistory;
use App\Models\Player;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ConsentPolicyService
{
    public function __construct(
        protected ConsentEvidenceService $evidenceService,
        protected AuditService $auditService
    ) {}

    /**
     * Verifica se o jogador autorizou recebimento de E-mail Marketing.
     */
    public function canSendMarketingEmail(Player $player): bool
    {
        return $this->hasConsent($player, ConsentType::MARKETING_EMAIL->value);
    }

    /**
     * Verifica se o jogador autorizou recebimento de SMS Marketing.
     */
    public function canSendMarketingSms(Player $player): bool
    {
        return $this->hasConsent($player, ConsentType::MARKETING_SMS->value);
    }

    /**
     * Verifica se o jogador autorizou recebimento de WhatsApp Marketing.
     */
    public function canSendMarketingWhatsapp(Player $player): bool
    {
        return $this->hasConsent($player, ConsentType::MARKETING_WHATSAPP->value);
    }

    /**
     * Verifica se o jogador autorizou recebimento de Push Notification Marketing.
     */
    public function canSendMarketingPush(Player $player): bool
    {
        return $this->hasConsent($player, ConsentType::MARKETING_PUSH->value);
    }

    /**
     * Verifica se o jogador pode receber automações de marketing em geral.
     */
    public function canRunMarketingAutomation(Player $player): bool
    {
        // Se ao menos um canal de marketing estiver autorizado
        return $this->canSendMarketingEmail($player)
            || $this->canSendMarketingSms($player)
            || $this->canSendMarketingWhatsapp($player)
            || $this->canSendMarketingPush($player);
    }

    /**
     * Verifica elegibilidade para inclusão em campanhas de marketing para canal específico.
     */
    public function canIncludeInMarketingCampaign(Player $player, string $channel): bool
    {
        $consentType = ConsentType::fromChannel($channel)->value;
        return $this->hasConsent($player, $consentType);
    }

    /**
     * Consulta centralizada com cache Redis e suporte a aliases.
     */
    public function hasConsent(Player $player, string $type): bool
    {
        $platformId = $player->platform_id;
        $playerId = $player->id;
        $upperType = strtoupper($type);

        $cacheKey = "betcrm:consent:{$platformId}:{$playerId}:{$upperType}";

        return Cache::remember($cacheKey, 60, function () use ($player, $upperType) {
            $channel = ConsentType::tryFrom($upperType)?->toChannel() ?? $upperType;

            return $player->consents()
                ->where(function ($q) use ($upperType, $channel) {
                    $q->where('type', $upperType)
                      ->orWhere('channel', $channel)
                      ->orWhere('channel', $upperType);
                })
                ->where(function ($q) {
                    $q->where('status', ConsentStatus::GRANTED->value)
                      ->orWhere('is_granted', true);
                })
                ->whereNull('revoked_at')
                ->exists();
        });
    }

    /**
     * Concede consentimento, registra histórico imutável e grava auditoria.
     */
    public function grantConsent(Player $player, string $type, array $data = []): Consent
    {
        $upperType = strtoupper($type);
        $channel = ConsentType::tryFrom($upperType)?->toChannel() ?? $upperType;

        return DB::transaction(function () use ($player, $upperType, $channel, $data) {
            $existing = Consent::where('player_id', $player->id)
                ->where(function ($q) use ($upperType, $channel) {
                    $q->where('type', $upperType)->orWhere('channel', $channel);
                })
                ->first();

            $prevStatus = $existing ? $existing->status : null;

            // Constrói evidência e hash SHA-256
            $evidenceParams = array_merge($data, [
                'player_id' => $player->id,
                'platform_id' => $player->platform_id,
                'type' => $upperType,
                'previous_status' => $prevStatus,
                'new_status' => ConsentStatus::GRANTED->value,
            ]);

            $evidence = $this->evidenceService->buildEvidence($evidenceParams);
            $evidenceHash = $this->evidenceService->generateHash($evidence);

            if ($existing) {
                $existing->update([
                    'status' => ConsentStatus::GRANTED->value,
                    'is_granted' => true,
                    'type' => $upperType,
                    'channel' => $channel,
                    'granted_at' => now(),
                    'revoked_at' => null,
                    'consent_source' => $data['source'] ?? $existing->consent_source ?? 'api',
                    'consent_version' => $data['version'] ?? $existing->consent_version ?? 'v1.0',
                    'consent_ip' => $data['ip_address'] ?? $existing->consent_ip,
                    'user_agent' => $data['user_agent'] ?? $existing->user_agent,
                    'evidence' => $evidence,
                    'evidence_hash' => $evidenceHash,
                ]);
                $consent = $existing;
            } else {
                $consent = Consent::create([
                    'platform_id' => $player->platform_id,
                    'player_id' => $player->id,
                    'type' => $upperType,
                    'channel' => $channel,
                    'status' => ConsentStatus::GRANTED->value,
                    'is_granted' => true,
                    'consent_date' => now(),
                    'granted_at' => now(),
                    'consent_source' => $data['source'] ?? 'api',
                    'consent_version' => $data['version'] ?? 'v1.0',
                    'consent_ip' => $data['ip_address'] ?? null,
                    'user_agent' => $data['user_agent'] ?? null,
                    'evidence' => $evidence,
                    'evidence_hash' => $evidenceHash,
                ]);
            }

            // Registra no histórico imutável (append-only)
            ConsentHistory::create([
                'consent_id' => $consent->id,
                'platform_id' => $player->platform_id,
                'player_id' => $player->id,
                'previous_status' => $prevStatus,
                'new_status' => ConsentStatus::GRANTED->value,
                'action' => 'GRANT',
                'source' => $data['source'] ?? 'api',
                'version' => $data['version'] ?? 'v1.0',
                'ip_address' => $data['ip_address'] ?? null,
                'user_agent' => $data['user_agent'] ?? null,
                'evidence' => $evidence,
                'evidence_hash' => $evidenceHash,
                'performed_at' => now(),
            ]);

            // Auditoria
            $this->auditService->log(
                $player->platform_id,
                'CONSENT_GRANTED',
                'Consent',
                $consent->id,
                ['previous_status' => $prevStatus],
                ['new_status' => ConsentStatus::GRANTED->value, 'type' => $upperType],
                $data['actor_type'] ?? 'USER',
                $data['actor_id'] ?? null,
                $data['ip_address'] ?? null,
                $data['user_agent'] ?? null
            );

            // Invalida cache
            $this->invalidateCache($player, $upperType, $channel);

            return $consent;
        });
    }

    /**
     * Revoga consentimento imediatamente, bloqueando canais de marketing e registrando auditoria.
     */
    public function revokeConsent(Player $player, string $type, array $data = []): Consent
    {
        $upperType = strtoupper($type);
        $channel = ConsentType::tryFrom($upperType)?->toChannel() ?? $upperType;

        return DB::transaction(function () use ($player, $upperType, $channel, $data) {
            $consent = Consent::where('player_id', $player->id)
                ->where(function ($q) use ($upperType, $channel) {
                    $q->where('type', $upperType)->orWhere('channel', $channel);
                })
                ->first();

            $prevStatus = $consent ? $consent->status : null;

            $evidenceParams = array_merge($data, [
                'player_id' => $player->id,
                'platform_id' => $player->platform_id,
                'type' => $upperType,
                'previous_status' => $prevStatus,
                'new_status' => ConsentStatus::REVOKED->value,
            ]);

            $evidence = $this->evidenceService->buildEvidence($evidenceParams);
            $evidenceHash = $this->evidenceService->generateHash($evidence);

            if ($consent) {
                $consent->update([
                    'status' => ConsentStatus::REVOKED->value,
                    'is_granted' => false,
                    'revoked_at' => now(),
                    'evidence' => $evidence,
                    'evidence_hash' => $evidenceHash,
                ]);
            } else {
                $consent = Consent::create([
                    'platform_id' => $player->platform_id,
                    'player_id' => $player->id,
                    'type' => $upperType,
                    'channel' => $channel,
                    'status' => ConsentStatus::REVOKED->value,
                    'is_granted' => false,
                    'consent_date' => now(),
                    'revoked_at' => now(),
                    'consent_source' => $data['source'] ?? 'api',
                    'evidence' => $evidence,
                    'evidence_hash' => $evidenceHash,
                ]);
            }

            // Registra no histórico imutável (append-only)
            ConsentHistory::create([
                'consent_id' => $consent->id,
                'platform_id' => $player->platform_id,
                'player_id' => $player->id,
                'previous_status' => $prevStatus,
                'new_status' => ConsentStatus::REVOKED->value,
                'action' => 'REVOKE',
                'source' => $data['source'] ?? 'api',
                'version' => $data['version'] ?? 'v1.0',
                'ip_address' => $data['ip_address'] ?? null,
                'user_agent' => $data['user_agent'] ?? null,
                'evidence' => $evidence,
                'evidence_hash' => $evidenceHash,
                'performed_at' => now(),
            ]);

            // Auditoria
            $this->auditService->log(
                $player->platform_id,
                'CONSENT_REVOKED',
                'Consent',
                $consent->id,
                ['previous_status' => $prevStatus],
                ['new_status' => ConsentStatus::REVOKED->value, 'type' => $upperType],
                $data['actor_type'] ?? 'USER',
                $data['actor_id'] ?? null,
                $data['ip_address'] ?? null,
                $data['user_agent'] ?? null
            );

            // Invalida cache
            $this->invalidateCache($player, $upperType, $channel);

            return $consent;
        });
    }

    protected function invalidateCache(Player $player, string $type, string $channel): void
    {
        $platformId = $player->platform_id;
        $playerId = $player->id;

        Cache::forget("betcrm:consent:{$platformId}:{$playerId}:{$type}");
        Cache::forget("betcrm:consent:{$platformId}:{$playerId}:{$channel}");
    }
}
