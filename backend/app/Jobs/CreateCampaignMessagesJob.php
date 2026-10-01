<?php

namespace App\Jobs;

use App\DTOs\Providers\MessagePayload;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Services\Messaging\MessageService;
use App\Services\Templates\TemplateRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CreateCampaignMessagesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 600;

    public function __construct(
        public int $campaignId,
        public int $chunkSize = 250
    ) {
        $this->onQueue('campaign_messages');
    }

    public function handle(MessageService $messageService, TemplateRenderer $renderer): void
    {
        $campaign = Campaign::withoutGlobalScopes()
            ->with(['templateVersion', 'platform'])
            ->find($this->campaignId);

        if (!$campaign) {
            Log::warning("CreateCampaignMessagesJob: Campanha #{$this->campaignId} não encontrada.");
            return;
        }

        // Valida se ainda está em processamento (respeita pause e cancel)
        if ($campaign->status !== 'PROCESSING') {
            Log::info("CreateCampaignMessagesJob: Campanha #{$campaign->id} está com status '{$campaign->status}'. Interrompendo envio de mensagens.");
            return;
        }

        $version = $campaign->templateVersion;
        if (!$version) {
            Log::error("CreateCampaignMessagesJob: Versão do template não encontrada para Campanha #{$campaign->id}.");
            $campaign->update(['status' => 'FAILED', 'failure_reason' => 'Versão do template inexistente.']);
            return;
        }

        // Busca próximo lote de destinatários pendentes
        $recipients = CampaignRecipient::where('campaign_id', $campaign->id)
            ->where('status', 'PENDING')
            ->with('player')
            ->limit($this->chunkSize)
            ->get();

        if ($recipients->isEmpty()) {
            // Verifica se ainda restam pendentes
            $remaining = CampaignRecipient::where('campaign_id', $campaign->id)
                ->where('status', 'PENDING')
                ->count();

            if ($remaining === 0) {
                $campaign->update([
                    'status' => 'COMPLETED',
                    'completed_at' => now(),
                ]);
                Log::info("Campanha #{$campaign->id} finalizou a criação de todas as mensagens com sucesso.");
            }
            return;
        }

        $channel = strtoupper($campaign->channel);

        foreach ($recipients as $recipient) {
            // Re-verifica se o operador pausou ou cancelou no meio do lote
            $currentStatus = Campaign::withoutGlobalScopes()
                ->where('id', $campaign->id)
                ->value('status');

            if ($currentStatus !== 'PROCESSING') {
                Log::info("CreateCampaignMessagesJob: Interrompendo loop pois a campanha #{$campaign->id} mudou para '{$currentStatus}'.");
                return;
            }

            $player = $recipient->player;
            if (!$player) {
                $recipient->update(['status' => 'FAILED', 'reason' => 'PLAYER_NOT_FOUND']);
                continue;
            }

            // Constrói contexto dinâmico do jogador para substituição de variáveis
            $firstName = explode(' ', trim($player->name ?? ''))[0] ?? 'Amigo';
            $context = [
                'player' => [
                    'name' => $player->name,
                    'first_name' => $firstName,
                    'email' => $player->email,
                    'phone' => $player->phone,
                    'city' => $player->city,
                    'state' => $player->state,
                    'status' => $player->status,
                    'external_id' => $player->external_id,
                    'created_at' => $player->created_at ? $player->created_at->format('d/m/Y') : date('d/m/Y'),
                ],
                'platform' => [
                    'name' => $campaign->platform->name ?? 'BET CRM',
                ],
            ];

            // Renderiza o template de acordo com o canal
            if ($channel === 'EMAIL') {
                $rendered = $renderer->renderEmail(
                    $version->subject ?? '',
                    $version->preheader,
                    $version->html_content,
                    $version->text_content,
                    $context
                );

                $payload = new MessagePayload(
                    recipient: $recipient->recipient,
                    recipientName: $player->name,
                    subject: $rendered['subject'],
                    html: $rendered['html'],
                    text: $rendered['text'],
                    templateId: $campaign->template_id,
                    templateVersionId: $campaign->template_version_id,
                    platformId: $campaign->platform_id,
                    campaignId: $campaign->id,
                    metadata: [
                        'campaign_id' => $campaign->id,
                        'campaign_name' => $campaign->name,
                        'recipient_id' => $recipient->id,
                        'player_id' => $player->id,
                    ],
                    idempotencyKey: "campaign:{$campaign->id}:player:{$player->id}:channel:EMAIL"
                );
            } else {
                $rendered = $renderer->renderSms(
                    $version->sms_content ?? '',
                    $context
                );

                $payload = new MessagePayload(
                    recipient: $recipient->recipient,
                    recipientName: $player->name,
                    smsContent: $rendered['sms'],
                    templateId: $campaign->template_id,
                    templateVersionId: $campaign->template_version_id,
                    platformId: $campaign->platform_id,
                    campaignId: $campaign->id,
                    metadata: [
                        'campaign_id' => $campaign->id,
                        'campaign_name' => $campaign->name,
                        'recipient_id' => $recipient->id,
                        'player_id' => $player->id,
                    ],
                    idempotencyKey: "campaign:{$campaign->id}:player:{$player->id}:channel:SMS"
                );
            }

            try {
                // Envia para o MessageService da Fase 7 (idempotência, persistência na fila e dispatch de SendMessageJob)
                $message = $messageService->send($campaign->platform_id, $payload, $campaign->provider_id);

                $recipient->update([
                    'status' => 'QUEUED',
                    'message_id' => $message->id,
                ]);

                $campaign->increment('messages_created');
            } catch (\Throwable $e) {
                Log::error("Falha ao criar mensagem para destinatário #{$recipient->id} na Campanha #{$campaign->id}: {$e->getMessage()}");
                $recipient->update([
                    'status' => 'FAILED',
                    'reason' => 'MESSAGE_CREATION_FAILED',
                ]);
                $campaign->increment('messages_failed');
            }
        }

        // Se ainda restarem mensagens pendentes e a campanha continuar ativa, auto-dispara o próximo chunk
        $remainingCount = CampaignRecipient::where('campaign_id', $campaign->id)
            ->where('status', 'PENDING')
            ->count();

        $freshCampaign = Campaign::withoutGlobalScopes()->find($campaign->id);
        if ($remainingCount > 0 && $freshCampaign && $freshCampaign->status === 'PROCESSING') {
            CreateCampaignMessagesJob::dispatch($campaign->id, $this->chunkSize);
        } elseif ($remainingCount === 0 && $freshCampaign && $freshCampaign->status === 'PROCESSING') {
            $freshCampaign->update([
                'status' => 'COMPLETED',
                'completed_at' => now(),
            ]);
            Log::info("Campanha #{$campaign->id} concluída com sucesso.");
        }
    }
}
