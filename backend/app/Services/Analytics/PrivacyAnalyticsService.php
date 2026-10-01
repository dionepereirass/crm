<?php

namespace App\Services\Analytics;

use App\Models\AuditLog;
use App\Models\Consent;
use App\Models\DataSubjectRequest;
use App\Models\Player;
use App\Models\RetentionPolicy;
use Carbon\Carbon;

class PrivacyAnalyticsService
{
    public function __construct(
        protected PeriodService $periodService,
        protected AnalyticsCacheService $cacheService
    ) {}

    /**
     * Retorna indicadores agregados de conformidade LGPD e privacidade.
     */
    public function getAnalytics(int $platformId, array $filters = []): array
    {
        return $this->cacheService->remember($platformId, 'privacy_overview', $filters, 300, function () use ($platformId, $filters) {
            $resolved = $this->periodService->resolvePeriod($filters);
            $start = $resolved['current']['start'];
            $end = $resolved['current']['end'];

            // 1. Consentimentos
            $activeConsents = Consent::withoutGlobalScopes()
                ->where('platform_id', $platformId)
                ->where(function ($q) {
                    $q->where('status', 'GRANTED')->orWhere('is_granted', true);
                })
                ->whereNull('revoked_at')
                ->count();

            $revokedConsents = Consent::withoutGlobalScopes()
                ->where('platform_id', $platformId)
                ->where(function ($q) {
                    $q->where('status', 'REVOKED')->orWhere('is_granted', false);
                })
                ->count();

            // 2. Solicitações de Titular (DSR)
            $dsrQuery = DataSubjectRequest::withoutGlobalScopes()
                ->where('platform_id', $platformId);

            $openDsr = (clone $dsrQuery)->whereIn('status', ['OPEN', 'IN_PROGRESS'])->count();
            $completedDsr = (clone $dsrQuery)->where('status', 'COMPLETED')->count();
            $rejectedDsr = (clone $dsrQuery)->where('status', 'REJECTED')->count();

            $now = Carbon::now();
            $nearSlaDsr = (clone $dsrQuery)
                ->whereIn('status', ['OPEN', 'IN_PROGRESS'])
                ->where('due_at', '>', $now)
                ->where('due_at', '<=', $now->copy()->addHours(48))
                ->count();

            $expiredDsr = (clone $dsrQuery)
                ->whereIn('status', ['OPEN', 'IN_PROGRESS'])
                ->where('due_at', '<', $now)
                ->count();

            // 3. Anonimizações e Retenção
            $anonymizedPlayers = Player::withoutGlobalScopes()
                ->where('platform_id', $platformId)
                ->where('name', 'LIKE', 'ANONYMIZED_USER_%')
                ->count();

            $activeRetentionPolicies = RetentionPolicy::withoutGlobalScopes()
                ->where('platform_id', $platformId)
                ->where('active', true)
                ->count();

            $auditEventsCount = AuditLog::withoutGlobalScopes()
                ->where('platform_id', $platformId)
                ->whereBetween('created_at', [$start, $end])
                ->count();

            return [
                'period' => $resolved['current'],
                'kpis' => [
                    'active_consents' => $activeConsents,
                    'revoked_consents' => $revokedConsents,
                    'open_dsr_requests' => $openDsr,
                    'completed_dsr_requests' => $completedDsr,
                    'rejected_dsr_requests' => $rejectedDsr,
                    'near_sla_dsr_requests' => $nearSlaDsr,
                    'expired_dsr_requests' => $expiredDsr,
                    'anonymized_players' => $anonymizedPlayers,
                    'active_retention_policies' => $activeRetentionPolicies,
                    'audit_events_count' => $auditEventsCount,
                ],
            ];
        });
    }
}
