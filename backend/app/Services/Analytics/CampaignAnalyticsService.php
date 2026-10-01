<?php

namespace App\Services\Analytics;

use App\Enums\MessageEventType;
use App\Models\Campaign;
use App\Models\CampaignMetric;
use App\Models\CampaignRecipient;
use App\Models\Message;
use App\Models\MessageEvent;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CampaignAnalyticsService
{
    /**
     * Retorna as métricas completas de uma campanha, lendo da tabela agregada
     * ou recalculando em tempo real se ainda não computada.
     */
    public function getCampaignAnalytics(int $campaignId, int $platformId): array
    {
        $campaign = Campaign::where('platform_id', $platformId)->find($campaignId);
        if (!$campaign) {
            throw new InvalidArgumentException("Campanha não encontrada para a plataforma atual.");
        }

        $metric = CampaignMetric::where('campaign_id', $campaignId)->first();
        if (!$metric) {
            $metric = $this->rebuildForCampaign($campaignId, $platformId);
        }

        // Funil operacional padronizado
        $funnel = [
            ['key' => 'AUDIENCE', 'label' => 'Público Alvo', 'count' => $metric->audience_count],
            ['key' => 'ELIGIBLE', 'label' => 'Elegíveis (LGPD)', 'count' => $metric->eligible_count],
            ['key' => 'QUEUED', 'label' => 'Enfileirados', 'count' => $metric->queued_count],
            ['key' => 'SENT', 'label' => 'Enviados', 'count' => $metric->sent_count],
            ['key' => 'DELIVERED', 'label' => 'Entregues', 'count' => $metric->delivered_count],
            ['key' => 'OPENED', 'label' => 'Aberturas Únicas', 'count' => $metric->unique_openers_count],
            ['key' => 'CLICKED', 'label' => 'Cliques Únicos', 'count' => $metric->unique_clickers_count],
        ];

        // Distribuição por provedor
        $providers = Message::where('campaign_id', $campaignId)
            ->whereNotNull('provider_id')
            ->select(
                'provider_id',
                DB::raw('COUNT(*) as total_messages'),
                DB::raw("COUNT(CASE WHEN status IN ('SENT', 'DELIVERED') THEN 1 END) as sent_count"),
                DB::raw("COUNT(CASE WHEN status = 'DELIVERED' THEN 1 END) as delivered_count"),
                DB::raw("COUNT(CASE WHEN status = 'FAILED' THEN 1 END) as failed_count"),
                DB::raw("COUNT(CASE WHEN status = 'BOUNCED' THEN 1 END) as bounced_count")
            )
            ->groupBy('provider_id')
            ->with('provider:id,name,driver,channel')
            ->get();

        // Série temporal de eventos (agrupado por hora ou dia)
        $timelineEvents = MessageEvent::join('messages', 'message_events.message_id', '=', 'messages.id')
            ->where('messages.campaign_id', $campaignId)
            ->whereIn('message_events.event_type', ['SENT', 'DELIVERED', 'OPENED', 'CLICKED'])
            ->select(
                DB::raw("DATE(message_events.occurred_at) as event_date"),
                'message_events.event_type',
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('event_date', 'message_events.event_type')
            ->orderBy('event_date', 'asc')
            ->get();

        return [
            'campaign_id' => $campaign->id,
            'campaign_name' => $campaign->name,
            'channel' => $campaign->channel,
            'status' => $campaign->status,
            'metrics' => [
                'audience' => $metric->audience_count,
                'eligible' => $metric->eligible_count,
                'queued' => $metric->queued_count,
                'sent' => $metric->sent_count,
                'delivered' => $metric->delivered_count,
                'failed' => $metric->failed_count,
                'bounced' => $metric->bounced_count,
                'opened' => $metric->opened_count,
                'unique_openers' => $metric->unique_openers_count,
                'clicked' => $metric->clicked_count,
                'unique_clickers' => $metric->unique_clickers_count,
                'unsubscribed' => $metric->unsubscribed_count,
                'delivery_rate' => $metric->delivery_rate,
                'open_rate' => $metric->open_rate,
                'click_rate' => $metric->click_rate,
                'bounce_rate' => $metric->bounce_rate,
                'failure_rate' => $metric->failure_rate,
                'unsubscribe_rate' => $metric->unsubscribe_rate,
                'updated_at' => $metric->updated_at?->toIso8601String(),
            ],
            'funnel' => $funnel,
            'providers' => $providers,
            'timeline' => $timelineEvents,
        ];
    }

    /**
     * Retorna a linha do tempo de eventos paginada para uma campanha.
     */
    public function getCampaignEvents(int $campaignId, int $platformId, int $perPage = 25): LengthAwarePaginator
    {
        $campaign = Campaign::where('platform_id', $platformId)->find($campaignId);
        if (!$campaign) {
            throw new InvalidArgumentException("Campanha não encontrada para a plataforma atual.");
        }

        return MessageEvent::join('messages', 'message_events.message_id', '=', 'messages.id')
            ->where('messages.campaign_id', $campaignId)
            ->where('messages.platform_id', $platformId)
            ->select(
                'message_events.id',
                'message_events.message_id',
                'message_events.event_type',
                'message_events.provider_event_id',
                'message_events.occurred_at',
                'message_events.created_at',
                'messages.recipient',
                'messages.channel'
            )
            ->with(['message.provider:id,name,driver'])
            ->orderBy('message_events.occurred_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Incrementa ou atualiza o registro agregado de métricas quando um novo evento é processado.
     */
    public function recordEvent(int $campaignId, MessageEventType $eventType, int $messageId): void
    {
        $campaign = Campaign::withoutGlobalScopes()->find($campaignId);
        if (!$campaign) {
            return;
        }

        $metric = CampaignMetric::firstOrCreate(
            ['campaign_id' => $campaignId],
            ['platform_id' => $campaign->platform_id]
        );

        // Atualiza contadores específicos
        switch ($eventType) {
            case MessageEventType::SENT:
                $metric->increment('sent_count');
                break;
            case MessageEventType::DELIVERED:
                $metric->increment('delivered_count');
                break;
            case MessageEventType::FAILED:
            case MessageEventType::REJECTED:
                $metric->increment('failed_count');
                break;
            case MessageEventType::BOUNCED:
                $metric->increment('bounced_count');
                break;
            case MessageEventType::OPENED:
                $metric->increment('opened_count');
                // Verifica se é abertura única para este destinatário
                $openCount = MessageEvent::where('message_id', $messageId)
                    ->where('event_type', 'OPENED')
                    ->count();
                if ($openCount <= 1) {
                    $metric->increment('unique_openers_count');
                }
                break;
            case MessageEventType::CLICKED:
                $metric->increment('clicked_count');
                // Verifica se é clique único para este destinatário
                $clickCount = MessageEvent::where('message_id', $messageId)
                    ->where('event_type', 'CLICKED')
                    ->count();
                if ($clickCount <= 1) {
                    $metric->increment('unique_clickers_count');
                }
                break;
            case MessageEventType::UNSUBSCRIBED:
                $unsubCount = MessageEvent::where('message_id', $messageId)
                    ->where('event_type', 'UNSUBSCRIBED')
                    ->count();
                if ($unsubCount <= 1) {
                    $metric->increment('unsubscribed_count');
                }
                break;
            default:
                break;
        }

        // Recalcula taxas com segurança contra divisão por zero
        $this->refreshRates($metric);
    }

    /**
     * Reconstitui integralmente as métricas de uma campanha a partir dos dados brutos (Event Sourcing simplificado).
     */
    public function rebuildForCampaign(int $campaignId, ?int $platformId = null): CampaignMetric
    {
        $campaignQuery = Campaign::withoutGlobalScopes()->where('id', $campaignId);
        if ($platformId) {
            $campaignQuery->where('platform_id', $platformId);
        }
        $campaign = $campaignQuery->first();
        if (!$campaign) {
            throw new InvalidArgumentException("Campanha #{$campaignId} não encontrada.");
        }

        // 1. Destinatários e Audiência
        $audienceCount = CampaignRecipient::where('campaign_id', $campaignId)->count();
        $eligibleCount = CampaignRecipient::where('campaign_id', $campaignId)
            ->where('status', '!=', 'SKIPPED')
            ->count();

        // 2. Mensagens
        $messagesQuery = Message::where('campaign_id', $campaignId);
        $queuedCount = (clone $messagesQuery)->whereIn('status', ['PENDING', 'QUEUED', 'SENDING'])->count();
        $sentCount = (clone $messagesQuery)->whereIn('status', ['SENT', 'DELIVERED'])->count();
        $deliveredCount = (clone $messagesQuery)->where('status', 'DELIVERED')->count();
        $failedCount = (clone $messagesQuery)->where('status', 'FAILED')->count();
        $bouncedCount = (clone $messagesQuery)->where('status', 'BOUNCED')->count();

        // 3. Eventos detalhados
        $eventsBase = MessageEvent::join('messages', 'message_events.message_id', '=', 'messages.id')
            ->where('messages.campaign_id', $campaignId);

        $openedCount = (clone $eventsBase)->where('message_events.event_type', 'OPENED')->count();
        $uniqueOpenersCount = (clone $eventsBase)
            ->where('message_events.event_type', 'OPENED')
            ->distinct('messages.id')
            ->count('messages.id');

        $clickedCount = (clone $eventsBase)->where('message_events.event_type', 'CLICKED')->count();
        $uniqueClickersCount = (clone $eventsBase)
            ->where('message_events.event_type', 'CLICKED')
            ->distinct('messages.id')
            ->count('messages.id');

        $unsubscribedCount = (clone $eventsBase)
            ->where('message_events.event_type', 'UNSUBSCRIBED')
            ->distinct('messages.id')
            ->count('messages.id');

        // Se mensagens foram abertas ou clicadas, elas certamente foram entregues
        if ($deliveredCount < $uniqueOpenersCount) {
            $deliveredCount = $uniqueOpenersCount;
        }

        // 4. Taxas com proteção estrita de divisão por zero
        $deliveryRate = $sentCount > 0 ? round(($deliveredCount / $sentCount) * 100, 2) : 0.00;
        $openRate = $deliveredCount > 0 ? round(($uniqueOpenersCount / $deliveredCount) * 100, 2) : 0.00;
        $clickRate = $deliveredCount > 0 ? round(($uniqueClickersCount / $deliveredCount) * 100, 2) : 0.00;
        $bounceRate = $sentCount > 0 ? round(($bouncedCount / $sentCount) * 100, 2) : 0.00;
        $failureRate = $sentCount > 0 ? round(($failedCount / $sentCount) * 100, 2) : 0.00;
        $unsubscribeRate = $deliveredCount > 0 ? round(($unsubscribedCount / $deliveredCount) * 100, 2) : 0.00;

        return CampaignMetric::updateOrCreate(
            ['campaign_id' => $campaignId],
            [
                'platform_id' => $campaign->platform_id,
                'audience_count' => $audienceCount,
                'eligible_count' => $eligibleCount,
                'queued_count' => $queuedCount,
                'sent_count' => $sentCount,
                'delivered_count' => $deliveredCount,
                'failed_count' => $failedCount,
                'bounced_count' => $bouncedCount,
                'opened_count' => $openedCount,
                'unique_openers_count' => $uniqueOpenersCount,
                'clicked_count' => $clickedCount,
                'unique_clickers_count' => $uniqueClickersCount,
                'unsubscribed_count' => $unsubscribedCount,
                'delivery_rate' => $deliveryRate,
                'open_rate' => $openRate,
                'click_rate' => $clickRate,
                'bounce_rate' => $bounceRate,
                'failure_rate' => $failureRate,
                'unsubscribe_rate' => $unsubscribeRate,
            ]
        );
    }

    /**
     * Recalcula métricas para todas as campanhas da plataforma especificada (ou de todas as plataformas).
     */
    public function rebuildAll(?int $platformId = null): int
    {
        $query = Campaign::withoutGlobalScopes();
        if ($platformId) {
            $query->where('platform_id', $platformId);
        }

        $campaignIds = $query->pluck('id');
        $rebuilt = 0;

        foreach ($campaignIds as $campaignId) {
            $this->rebuildForCampaign($campaignId);
            $rebuilt++;
        }

        return $rebuilt;
    }

    /**
     * Retorna panorama geral agregado de métricas de campanhas na plataforma.
     */
    public function getOverview(int $platformId, array $filters = []): array
    {
        $campaignsQuery = Campaign::where('platform_id', $platformId);
        if (!empty($filters['date_from'])) {
            $campaignsQuery->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $campaignsQuery->whereDate('created_at', '<=', $filters['date_to']);
        }

        $campaignIds = $campaignsQuery->pluck('id');

        $metrics = CampaignMetric::where('platform_id', $platformId)
            ->whereIn('campaign_id', $campaignIds)
            ->selectRaw('
                COUNT(campaign_id) as total_campaigns,
                COALESCE(SUM(audience_count), 0) as total_audience,
                COALESCE(SUM(queued_count), 0) as total_queued,
                COALESCE(SUM(sent_count), 0) as total_sent,
                COALESCE(SUM(delivered_count), 0) as total_delivered,
                COALESCE(SUM(failed_count), 0) as total_failed,
                COALESCE(SUM(bounced_count), 0) as total_bounced,
                COALESCE(SUM(opened_count), 0) as total_opened,
                COALESCE(SUM(unique_openers_count), 0) as total_unique_openers,
                COALESCE(SUM(clicked_count), 0) as total_clicked,
                COALESCE(SUM(unique_clickers_count), 0) as total_unique_clickers,
                COALESCE(SUM(unsubscribed_count), 0) as total_unsubscribed
            ')
            ->first();

        $sent = (int) ($metrics->total_sent ?? 0);
        $delivered = (int) ($metrics->total_delivered ?? 0);
        $uniqueOpeners = (int) ($metrics->total_unique_openers ?? 0);
        $uniqueClickers = (int) ($metrics->total_unique_clickers ?? 0);
        $bounced = (int) ($metrics->total_bounced ?? 0);
        $failed = (int) ($metrics->total_failed ?? 0);
        $unsubscribed = (int) ($metrics->total_unsubscribed ?? 0);

        return [
            'total_campaigns' => (int) ($metrics->total_campaigns ?? 0),
            'total_audience' => (int) ($metrics->total_audience ?? 0),
            'total_messages' => $sent + (int) ($metrics->total_queued ?? 0),
            'total_sent' => $sent,
            'total_delivered' => $delivered,
            'total_failed' => $failed,
            'total_bounced' => $bounced,
            'total_opened' => (int) ($metrics->total_opened ?? 0),
            'total_unique_openers' => $uniqueOpeners,
            'total_clicked' => (int) ($metrics->total_clicked ?? 0),
            'total_unique_clickers' => $uniqueClickers,
            'total_unsubscribed' => $unsubscribed,
            'delivery_rate' => $sent > 0 ? round(($delivered / $sent) * 100, 2) : 0.00,
            'open_rate' => $delivered > 0 ? round(($uniqueOpeners / $delivered) * 100, 2) : 0.00,
            'click_rate' => $delivered > 0 ? round(($uniqueClickers / $delivered) * 100, 2) : 0.00,
            'bounce_rate' => $sent > 0 ? round(($bounced / $sent) * 100, 2) : 0.00,
            'failure_rate' => $sent > 0 ? round(($failed / $sent) * 100, 2) : 0.00,
            'unsubscribe_rate' => $delivered > 0 ? round(($unsubscribed / $delivered) * 100, 2) : 0.00,
        ];
    }

    /**
     * Lista campanhas com suas métricas agregadas e filtros avançados.
     */
    public function listCampaignsAnalytics(int $platformId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Campaign::where('platform_id', $platformId)
            ->with(['metric', 'provider:id,name,channel,driver', 'segment:id,name']);

        if (!empty($filters['channel']) && $filters['channel'] !== 'ALL') {
            $query->where('channel', strtoupper($filters['channel']));
        }

        if (!empty($filters['status']) && $filters['status'] !== 'ALL') {
            $query->where('status', strtoupper($filters['status']));
        }

        if (!empty($filters['provider_id'])) {
            $query->where('provider_id', (int) $filters['provider_id']);
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Gera conteúdo em formato CSV com dados analíticos higienizados (sem CPF, secrets ou tokens).
     */
    public function exportCsv(int $platformId, array $filters = []): string
    {
        $campaigns = $this->listCampaignsAnalytics($platformId, $filters, 1000);

        $output = fopen('php://temp', 'r+');
        fputcsv($output, [
            'ID',
            'Nome da Campanha',
            'Canal',
            'Status',
            'Data de Criacao',
            'Publico Alvo',
            'Elegiveis',
            'Enviados',
            'Entregues',
            'Falhas',
            'Bounces',
            'Aberturas Unicas',
            'Cliques Unicos',
            'Descadastros',
            'Taxa Entrega (%)',
            'Taxa Abertura (%)',
            'Taxa Cliques (%)',
            'Taxa Bounce (%)',
        ]);

        $sanitizer = app(\App\Services\Privacy\SensitiveDataSanitizer::class);

        foreach ($campaigns as $camp) {
            $m = $camp->metric;
            $row = [
                $camp->id,
                $camp->name,
                $camp->channel,
                $camp->status,
                $camp->created_at->format('d/m/Y H:i'),
                $m->audience_count ?? 0,
                $m->eligible_count ?? 0,
                $m->sent_count ?? 0,
                $m->delivered_count ?? 0,
                $m->failed_count ?? 0,
                $m->bounced_count ?? 0,
                $m->unique_openers_count ?? 0,
                $m->unique_clickers_count ?? 0,
                $m->unsubscribed_count ?? 0,
                $m->delivery_rate ?? 0.00,
                $m->open_rate ?? 0.00,
                $m->click_rate ?? 0.00,
                $m->bounce_rate ?? 0.00,
            ];
            fputcsv($output, $sanitizer->sanitizeCsvRow($row));
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return (string) $csv;
    }

    /**
     * Recalcula taxas percentuais a partir das contagens do modelo.
     */
    protected function refreshRates(CampaignMetric $metric): void
    {
        $sent = $metric->sent_count;
        $delivered = $metric->delivered_count;

        $metric->delivery_rate = $sent > 0 ? round(($delivered / $sent) * 100, 2) : 0.00;
        $metric->open_rate = $delivered > 0 ? round(($metric->unique_openers_count / $delivered) * 100, 2) : 0.00;
        $metric->click_rate = $delivered > 0 ? round(($metric->unique_clickers_count / $delivered) * 100, 2) : 0.00;
        $metric->bounce_rate = $sent > 0 ? round(($metric->bounced_count / $sent) * 100, 2) : 0.00;
        $metric->failure_rate = $sent > 0 ? round(($metric->failed_count / $sent) * 100, 2) : 0.00;
        $metric->unsubscribe_rate = $delivered > 0 ? round(($metric->unsubscribed_count / $delivered) * 100, 2) : 0.00;
        $metric->save();
    }
}
