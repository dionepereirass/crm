<?php

namespace App\Services\Analytics;

use App\Models\Message;
use App\Models\MessageEvent;
use App\Models\Template;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TemplateAnalyticsService
{
    public function __construct(
        protected PeriodService $periodService,
        protected AnalyticsCacheService $cacheService
    ) {}

    /**
     * Compara performance operacional de templates de e-mail e SMS.
     */
    public function getAnalytics(int $platformId, array $filters = []): array
    {
        return $this->cacheService->remember($platformId, 'templates_overview', $filters, 300, function () use ($platformId, $filters) {
            $resolved = $this->periodService->resolvePeriod($filters);
            $start = $resolved['current']['start'];
            $end = $resolved['current']['end'];

            $templates = Template::withoutGlobalScopes()
                ->where('platform_id', $platformId)
                ->get();

            $results = [];

            foreach ($templates as $tmpl) {
                $messagesQuery = Message::withoutGlobalScopes()
                    ->where('platform_id', $platformId)
                    ->where('template_id', $tmpl->id)
                    ->whereBetween('created_at', [$start, $end]);

                $totalSent = (clone $messagesQuery)->whereIn('status', ['SENT', 'DELIVERED'])->count();
                $totalDelivered = (clone $messagesQuery)->where('status', 'DELIVERED')->count();
                $totalFailed = (clone $messagesQuery)->where('status', 'FAILED')->count();

                $messageIds = (clone $messagesQuery)->pluck('id');
                $events = MessageEvent::whereIn('message_id', $messageIds)
                    ->select('event_type', DB::raw('count(distinct message_id) as count'))
                    ->groupBy('event_type')
                    ->pluck('count', 'event_type');

                $opened = (int) ($events['OPENED'] ?? 0);
                $clicked = (int) ($events['CLICKED'] ?? 0);
                $unsubscribed = (int) ($events['UNSUBSCRIBED'] ?? 0);

                $deliveryRate = $totalSent > 0 ? round(($totalDelivered / $totalSent) * 100, 1) : 0.0;
                $openRate = $totalDelivered > 0 ? round(($opened / $totalDelivered) * 100, 1) : 0.0;
                $ctr = $totalDelivered > 0 ? round(($clicked / $totalDelivered) * 100, 1) : 0.0;

                $results[] = [
                    'id' => $tmpl->id,
                    'name' => $tmpl->name,
                    'channel' => $tmpl->channel,
                    'total_sent' => $totalSent,
                    'total_delivered' => $totalDelivered,
                    'total_failed' => $totalFailed,
                    'total_opened' => $opened,
                    'total_clicked' => $clicked,
                    'total_unsubscribed' => $unsubscribed,
                    'delivery_rate' => $deliveryRate,
                    'open_rate' => $openRate,
                    'ctr' => $ctr,
                ];
            }

            usort($results, fn($a, $b) => $b['total_sent'] <=> $a['total_sent']);

            return [
                'period' => $resolved['current'],
                'templates' => $results,
                'total_templates' => count($templates),
            ];
        });
    }
}
