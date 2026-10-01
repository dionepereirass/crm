<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Player;
use App\Services\Campaigns\CampaignAudienceService;
use App\Services\Segments\SegmentQueryCompiler;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DispatchCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 600;

    public function __construct(
        public int $campaignId
    ) {
        $this->onQueue('campaigns');
    }

    public function handle(SegmentQueryCompiler $queryCompiler): void
    {
        $campaign = Campaign::withoutGlobalScopes()->find($this->campaignId);

        if (!$campaign) {
            Log::warning("DispatchCampaignJob: Campanha #{$this->campaignId} não encontrada.");
            return;
        }

        // Valida estado atual: somente campanhas em PROCESSING devem ser preparadas
        if ($campaign->status !== 'PROCESSING') {
            Log::info("DispatchCampaignJob: Campanha #{$campaign->id} está com status '{$campaign->status}'. Abortando preparação.");
            return;
        }

        $segment = $campaign->segment;
        if (!$segment) {
            Log::error("DispatchCampaignJob: Segmento não associado à campanha #{$campaign->id}.");
            $campaign->update(['status' => 'FAILED', 'failure_reason' => 'Segmento não associado à campanha.']);
            return;
        }

        $platformId = $campaign->platform_id;
        $channel = strtoupper($campaign->channel);

        Log::info("Iniciando geração de snapshot de destinatários para Campanha #{$campaign->id}.", [
            'platform_id' => $platformId,
            'channel' => $channel,
            'segment_id' => $segment->id,
        ]);

        // Compila a query do segmento
        $builder = $queryCompiler->compile($segment->rules_tree ?? [], $platformId);

        // Processa os jogadores em chunks pelo ID para economia de memória
        $builder->chunkById(500, function ($players) use ($campaign, $platformId, $channel) {
            // Re-verifica se a campanha foi pausada ou cancelada durante o chunking
            $fresh = Campaign::withoutGlobalScopes()->find($campaign->id);
            if (!$fresh || $fresh->status !== 'PROCESSING') {
                return false; // Interrompe chunks
            }

            foreach ($players as $player) {
                $contact = $channel === 'EMAIL' ? trim($player->email ?? '') : trim($player->phone ?? '');

                $status = 'PENDING';
                $reason = null;

                if ($player->status === 'BLOCKED') {
                    $status = 'SKIPPED';
                    $reason = 'PLAYER_BLOCKED';
                } elseif (empty($contact)) {
                    $status = 'SKIPPED';
                    $reason = 'INVALID_CONTACT';
                } elseif (!$player->hasMarketingConsent($channel)) {
                    $status = 'SKIPPED';
                    $reason = 'MARKETING_CONSENT_REQUIRED';
                }

                // Criação com deduplicação estrita por (campaign_id, player_id, channel)
                CampaignRecipient::firstOrCreate(
                    [
                        'campaign_id' => $campaign->id,
                        'player_id' => $player->id,
                        'channel' => $channel,
                    ],
                    [
                        'uuid' => (string) Str::uuid(),
                        'platform_id' => $platformId,
                        'recipient' => $contact ?: 'N/A',
                        'status' => $status,
                        'reason' => $reason,
                    ]
                );
            }
        });

        // Atualiza contadores reais do snapshot gravado
        $totalRecipients = CampaignRecipient::where('campaign_id', $campaign->id)->count();
        $eligibleRecipients = CampaignRecipient::where('campaign_id', $campaign->id)
            ->where('status', 'PENDING')
            ->count();

        $campaign->update([
            'audience_count' => $totalRecipients,
            'eligible_count' => $eligibleRecipients,
        ]);

        Log::info("Snapshot de audiência concluído para Campanha #{$campaign->id}.", [
            'total_recipients' => $totalRecipients,
            'eligible_recipients' => $eligibleRecipients,
        ]);

        if ($eligibleRecipients === 0) {
            // Sem destinatários pendentes elegíveis
            $campaign->update([
                'status' => 'COMPLETED',
                'completed_at' => now(),
            ]);
            Log::info("Campanha #{$campaign->id} concluída sem destinatários pendentes para disparo.");
            return;
        }

        // Dispara o job de geração e enfileiramento das mensagens individuais
        CreateCampaignMessagesJob::dispatch($campaign->id);
    }
}
