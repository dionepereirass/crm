<?php

namespace App\Console\Commands;

use App\Services\Analytics\CampaignAnalyticsService;
use Illuminate\Console\Command;

class RebuildCampaignAnalyticsCommand extends Command
{
    /**
     * O nome e a assinatura do comando de console.
     */
    protected $signature = 'campaigns:rebuild-analytics 
                            {--campaign= : ID específico da campanha a recalcular}
                            {--platform= : ID da plataforma}';

    /**
     * A descrição do comando de console.
     */
    protected $description = 'Recalcula integralmente as métricas de performance das campanhas a partir dos eventos gravados';

    /**
     * Executa o comando de console.
     */
    public function handle(CampaignAnalyticsService $analyticsService): int
    {
        $campaignId = $this->option('campaign');
        $platformId = $this->option('platform');

        if ($campaignId) {
            $this->info("Recalculando métricas para a Campanha #{$campaignId}...");
            try {
                $metric = $analyticsService->rebuildForCampaign((int) $campaignId, $platformId ? (int) $platformId : null);
                $this->table(
                    ['Métrica', 'Valor'],
                    [
                        ['Campanha ID', $metric->campaign_id],
                        ['Público Alvo', $metric->audience_count],
                        ['Elegíveis', $metric->eligible_count],
                        ['Enviados', $metric->sent_count],
                        ['Entregues', $metric->delivered_count],
                        ['Falhas', $metric->failed_count],
                        ['Bounces', $metric->bounced_count],
                        ['Aberturas Únicas', $metric->unique_openers_count],
                        ['Cliques Únicos', $metric->unique_clickers_count],
                        ['Descadastros', $metric->unsubscribed_count],
                        ['Taxa de Entrega', "{$metric->delivery_rate}%"],
                        ['Taxa de Abertura', "{$metric->open_rate}%"],
                        ['Taxa de Cliques', "{$metric->click_rate}%"],
                        ['Taxa de Bounce', "{$metric->bounce_rate}%"],
                    ]
                );
                $this->info("Métricas da Campanha #{$campaignId} reconstruídas com sucesso!");
                return self::SUCCESS;
            } catch (\Throwable $e) {
                $this->error("Erro ao reconstruir métricas da Campanha #{$campaignId}: {$e->getMessage()}");
                return self::FAILURE;
            }
        }

        $this->info("Recalculando métricas para todas as campanhas...");
        $count = $analyticsService->rebuildAll($platformId ? (int) $platformId : null);
        $this->info("Concluído! {$count} campanhas tiveram suas métricas recalculadas.");

        return self::SUCCESS;
    }
}
