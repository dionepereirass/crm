<?php

namespace App\Services\Analytics;

use App\Models\Campaign;
use App\Models\Message;
use App\Models\MessageEvent;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class MarketingAnalyticsService
{
    public function __construct(
        protected PeriodService $periodService,
        protected AnalyticsCacheService $cacheService
    ) {}

    /**
     * Retorna visão analítica consolidada de marketing e mensageria.
     */
    public function getAnalytics(int $platformId, array $filters = []): array
    {
        return $this->cacheService->remember($platformId, 'marketing_overview', $filters, 300, function () use ($platformId, $filters) {
            $resolved = $this->periodService->resolvePeriod($filters);
            $current = $resolved['current'];
            $prior = $resolved['prior'];

            $curr = $this->calculateMarketingStats($platformId, $current['start'], $current['end']);
            $prev = $this->calculateMarketingStats($platformId, $prior['start'], $prior['end']);

            $kpis = [
                'campaigns_count' => [
                    'value' => $curr['campaigns'],
                    'prior_value' => $prev['campaigns'],
                    'change_percentage' => $this->periodService->calculatePercentageChange($curr['campaigns'], $prev['campaigns']),
                ],
                'messages_sent' => [
                    'value' => $curr['sent'],
                    'prior_value' => $prev['sent'],
                    'change_percentage' => $this->periodService->calculatePercentageChange($curr['sent'], $prev['sent']),
                ],
                'messages_delivered' => [
                    'value' => $curr['delivered'],
                    'prior_value' => $prev['delivered'],
                    'change_percentage' => $this->periodService->calculatePercentageChange($curr['delivered'], $prev['delivered']),
                ],
                'messages_opened' => [
                    'value' => $curr['opened'],
                    'prior_value' => $prev['opened'],
                    'change_percentage' => $this->periodService->calculatePercentageChange($curr['opened'], $prev['opened']),
                ],
                'messages_clicked' => [
                    'value' => $curr['clicked'],
                    'prior_value' => $prev['clicked'],
                    'change_percentage' => $this->periodService->calculatePercentageChange($curr['clicked'], $prev['clicked']),
                ],
                'delivery_rate' => [
                    'value' => $curr['delivery_rate'],
                    'formatted' => $curr['delivery_rate'] . '%',
                ],
                'open_rate' => [
                    'value' => $curr['open_rate'],
                    'formatted' => $curr['open_rate'] . '%',
                ],
                'click_through_rate' => [
                    'value' => $curr['ctr'],
                    'formatted' => $curr['ctr'] . '%',
                ],
                'unsubscribes' => [
                    'value' => $curr['unsubscribed'],
                    'prior_value' => $prev['unsubscribed'],
                    'change_percentage' => $this->periodService->calculatePercentageChange($curr['unsubscribed'], $prev['unsubscribed']),
                ],
            ];

            // Funil de Marketing
            $funnel = [
                ['stage' => 'AUDIÊNCIA', 'count' => $curr['audience'], 'percentage' => 100.0],
                ['stage' => 'ENVIADO', 'count' => $curr['sent'], 'percentage' => $curr['audience'] > 0 ? round(($curr['sent'] / $curr['audience']) * 100, 1) : 0.0],
                ['stage' => 'ENTREGUE', 'count' => $curr['delivered'], 'percentage' => $curr['sent'] > 0 ? round(($curr['delivered'] / $curr['sent']) * 100, 1) : 0.0],
                ['stage' => 'ABERTO', 'count' => $curr['opened'], 'percentage' => $curr['delivered'] > 0 ? round(($curr['opened'] / $curr['delivered']) * 100, 1) : 0.0],
                ['stage' => 'CLIQUE', 'count' => $curr['clicked'], 'percentage' => $curr['opened'] > 0 ? round(($curr['clicked'] / $curr['opened']) * 100, 1) : 0.0],
                ['stage' => 'CONVERSÃO', 'count' => $curr['converted'], 'percentage' => $curr['clicked'] > 0 ? round(($curr['converted'] / $curr['clicked']) * 100, 1) : 0.0],
            ];

            // Ranking Operacional de Campanhas
            $campaignRankings = $this->getCampaignRankings($platformId, $current['start'], $current['end']);

            // Distribuição por Canal (Email vs SMS)
            $channels = $this->getChannelsDistribution($platformId, $current['start'], $current['end']);

            return [
                'period' => [
                    'key' => $resolved['period_key'],
                    'current' => $current,
                    'prior' => $prior,
                ],
                'kpis' => $kpis,
                'funnel' => $funnel,
                'rankings' => $campaignRankings,
                'channels' => $channels,
            ];
        });
    }

    protected function calculateMarketingStats(int $platformId, Carbon $start, Carbon $end): array
    {
        $campaignsCount = Campaign::withoutGlobalScopes()
            ->where('platform_id', $platformId)
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $messagesQuery = Message::withoutGlobalScopes()
            ->where('platform_id', $platformId)
            ->whereBetween('created_at', [$start, $end]);

        $sentCount = (clone $messagesQuery)->whereIn('status', ['SENT', 'DELIVERED'])->count();
        $deliveredCount = (clone $messagesQuery)->where('status', 'DELIVERED')->count();
        $failedCount = (clone $messagesQuery)->where('status', 'FAILED')->count();

        // Eventos no período
        $messageIds = (clone $messagesQuery)->pluck('id');
        $events = MessageEvent::whereIn('message_id', $messageIds)
            ->select('event_type', DB::raw('count(distinct message_id) as unique_msgs'))
            ->groupBy('event_type')
            ->pluck('unique_msgs', 'event_type');

        $openedCount = (int) ($events['OPENED'] ?? 0);
        $clickedCount = (int) ($events['CLICKED'] ?? 0);
        $unsubscribedCount = (int) ($events['UNSUBSCRIBED'] ?? 0);
        $convertedCount = (int) ($events['CONVERTED'] ?? 0);

        // Audiência planejada
        $audienceTotal = max($sentCount + $failedCount, $sentCount);

        $deliveryRate = $sentCount > 0 ? round(($deliveredCount / $sentCount) * 100, 1) : 0.0;
        $openRate = $deliveredCount > 0 ? round(($openedCount / $deliveredCount) * 100, 1) : 0.0;
        $ctr = $deliveredCount > 0 ? round(($clickedCount / $deliveredCount) * 100, 1) : 0.0;

        return [
            'campaigns' => $campaignsCount,
            'audience' => $audienceTotal,
            'sent' => $sentCount,
            'delivered' => $deliveredCount,
            'opened' => $openedCount,
            'clicked' => $clickedCount,
            'failed' => $failedCount,
            'unsubscribed' => $unsubscribedCount,
            'converted' => $convertedCount,
            'delivery_rate' => $deliveryRate,
            'open_rate' => $openRate,
            'ctr' => $ctr,
        ];
    }

    protected function getCampaignRankings(int $platformId, Carbon $start, Carbon $end, int $limit = 5): array
    {
        $campaigns = Campaign::withoutGlobalScopes()
            ->where('platform_id', $platformId)
            ->whereBetween('created_at', [$start, $end])
            ->with(['metrics'])
            ->get();

        $ranked = $campaigns->map(function ($camp) {
            $m = $camp->metrics;
            $sent = $m->messages_sent ?? 0;
            $delivered = $m->messages_delivered ?? 0;
            $opened = $m->unique_opens ?? 0;
            $clicked = $m->unique_clicks ?? 0;

            $openRate = $delivered > 0 ? round(($opened / $delivered) * 100, 1) : 0.0;
            $ctr = $delivered > 0 ? round(($clicked / $delivered) * 100, 1) : 0.0;

            return [
                'id' => $camp->id,
                'name' => $camp->name,
                'channel' => $camp->channel,
                'status' => $camp->status,
                'sent' => $sent,
                'delivered' => $delivered,
                'open_rate' => $openRate,
                'ctr' => $ctr,
            ];
        })->sortByDesc('open_rate')->values()->take($limit)->all();

        return $ranked;
    }

    protected function getChannelsDistribution(int $platformId, Carbon $start, Carbon $end): array
    {
        $distribution = Message::withoutGlobalScopes()
            ->where('platform_id', $platformId)
            ->whereBetween('created_at', [$start, $end])
            ->select('channel', DB::raw('count(*) as count'))
            ->groupBy('channel')
            ->pluck('count', 'channel')
            ->all();

        return [
            'email' => (int) ($distribution['EMAIL'] ?? 0),
            'sms' => (int) ($distribution['SMS'] ?? 0),
            'whatsapp' => (int) ($distribution['WHATSAPP'] ?? 0),
            'push' => (int) ($distribution['PUSH'] ?? 0),
        ];
    }
}
