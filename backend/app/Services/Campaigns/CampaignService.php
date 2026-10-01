<?php

namespace App\Services\Campaigns;

use App\DTOs\Providers\MessagePayload;
use App\Jobs\CreateCampaignMessagesJob;
use App\Jobs\DispatchCampaignJob;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Message;
use App\Models\Player;
use App\Models\Segment;
use App\Models\Template;
use App\Models\TemplateVersion;
use App\Services\Messaging\MessageService;
use App\Services\Templates\TemplateRenderer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CampaignService
{
    public function __construct(
        protected CampaignStateService $stateService,
        protected CampaignValidator $validator,
        protected CampaignAudienceService $audienceService,
        protected TemplateRenderer $templateRenderer,
        protected MessageService $messageService
    ) {}

    /**
     * Lista campanhas da plataforma com filtros e paginação.
     */
    public function list(int $platformId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Campaign::withoutGlobalScopes()
            ->where('platform_id', $platformId)
            ->with(['segment:id,name,slug', 'template:id,name,channel', 'templateVersion:id,version,status', 'provider:id,name,driver']);

        if (!empty($filters['channel']) && $filters['channel'] !== 'ALL') {
            $query->where('channel', strtoupper($filters['channel']));
        }

        if (!empty($filters['status']) && $filters['status'] !== 'ALL') {
            $query->where('status', strtoupper($filters['status']));
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Busca uma campanha por ID ou UUID garantindo isolamento por plataforma.
     */
    public function getById(int|string $id, int $platformId): Campaign
    {
        $query = Campaign::withoutGlobalScopes()
            ->where('platform_id', $platformId)
            ->with([
                'segment',
                'template',
                'templateVersion',
                'provider',
                'creator:id,name,email',
                'updater:id,name,email',
            ]);

        if (is_numeric($id)) {
            $campaign = $query->where('id', (int) $id)->first();
        } else {
            $campaign = $query->where('uuid', $id)->first();
        }

        if (!$campaign) {
            throw new InvalidArgumentException("Campanha não encontrada para a plataforma atual.");
        }

        return $campaign;
    }

    /**
     * Cria uma nova campanha em estado DRAFT.
     */
    public function create(int $platformId, array $data, ?int $userId = null): Campaign
    {
        $channel = strtoupper($data['channel'] ?? 'EMAIL');

        // Valida que o segmento pertence à plataforma
        $segment = Segment::where('platform_id', $platformId)->findOrFail($data['segment_id']);

        // Valida que o template pertence à plataforma
        $template = Template::where('platform_id', $platformId)->findOrFail($data['template_id']);

        // Se template_version_id não for informado, assume a versão publicada atual
        $versionId = $data['template_version_id'] ?? $template->current_version_id;
        $version = TemplateVersion::where('template_id', $template->id)->findOrFail($versionId);

        return DB::transaction(function () use ($platformId, $data, $channel, $segment, $template, $version, $userId) {
            $campaign = Campaign::create([
                'uuid' => (string) Str::uuid(),
                'platform_id' => $platformId,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'channel' => $channel,
                'status' => 'DRAFT',
                'segment_id' => $segment->id,
                'template_id' => $template->id,
                'template_version_id' => $version->id,
                'provider_id' => $data['provider_id'] ?? null,
                'from_name' => $data['from_name'] ?? null,
                'from_email' => $data['from_email'] ?? null,
                'reply_to' => $data['reply_to'] ?? null,
                'sms_sender' => $data['sms_sender'] ?? null,
                'scheduled_at' => !empty($data['scheduled_at']) ? $data['scheduled_at'] : null,
                'metadata' => $data['metadata'] ?? [],
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            // Calcula audiência inicial estimada
            $audience = $this->audienceService->calculate($campaign);
            $campaign->update([
                'audience_count' => $audience['total_segment'],
                'eligible_count' => $audience['eligible'],
            ]);

            Log::info("Campanha criada com sucesso.", [
                'campaign_id' => $campaign->id,
                'platform_id' => $platformId,
                'user_id' => $userId,
            ]);

            return $campaign->fresh(['segment', 'template', 'templateVersion', 'provider']);
        });
    }

    /**
     * Atualiza metadados ou configuração de uma campanha.
     */
    public function update(int|string $id, int $platformId, array $data, ?int $userId = null): Campaign
    {
        $campaign = $this->getById($id, $platformId);

        if (!$campaign->canBeEdited()) {
            throw new InvalidArgumentException("Campanhas com status '{$campaign->status}' não podem ser editadas.");
        }

        return DB::transaction(function () use ($campaign, $data, $platformId, $userId) {
            $fields = [
                'name' => $data['name'] ?? $campaign->name,
                'description' => array_key_exists('description', $data) ? $data['description'] : $campaign->description,
                'channel' => !empty($data['channel']) ? strtoupper($data['channel']) : $campaign->channel,
                'from_name' => array_key_exists('from_name', $data) ? $data['from_name'] : $campaign->from_name,
                'from_email' => array_key_exists('from_email', $data) ? $data['from_email'] : $campaign->from_email,
                'reply_to' => array_key_exists('reply_to', $data) ? $data['reply_to'] : $campaign->reply_to,
                'sms_sender' => array_key_exists('sms_sender', $data) ? $data['sms_sender'] : $campaign->sms_sender,
                'provider_id' => array_key_exists('provider_id', $data) ? $data['provider_id'] : $campaign->provider_id,
                'scheduled_at' => array_key_exists('scheduled_at', $data) ? $data['scheduled_at'] : $campaign->scheduled_at,
                'updated_by' => $userId,
            ];

            if (!empty($data['segment_id'])) {
                $segment = Segment::where('platform_id', $platformId)->findOrFail($data['segment_id']);
                $fields['segment_id'] = $segment->id;
            }

            if (!empty($data['template_id'])) {
                $template = Template::where('platform_id', $platformId)->findOrFail($data['template_id']);
                $fields['template_id'] = $template->id;

                if (!empty($data['template_version_id'])) {
                    $version = TemplateVersion::where('template_id', $template->id)->findOrFail($data['template_version_id']);
                    $fields['template_version_id'] = $version->id;
                } else {
                    $fields['template_version_id'] = $template->current_version_id;
                }
            } elseif (!empty($data['template_version_id'])) {
                $version = TemplateVersion::where('template_id', $campaign->template_id)->findOrFail($data['template_version_id']);
                $fields['template_version_id'] = $version->id;
            }

            $campaign->update($fields);

            // Se alterou segmento ou canal, recalcula audiência
            $audience = $this->audienceService->calculate($campaign);
            $campaign->update([
                'audience_count' => $audience['total_segment'],
                'eligible_count' => $audience['eligible'],
            ]);

            return $campaign->fresh(['segment', 'template', 'templateVersion', 'provider']);
        });
    }

    /**
     * Exclusão lógica (soft delete) da campanha.
     */
    public function delete(int|string $id, int $platformId, ?int $userId = null): bool
    {
        $campaign = $this->getById($id, $platformId);

        if ($campaign->isProcessing()) {
            throw new InvalidArgumentException("Não é possível excluir uma campanha em processamento.");
        }

        $campaign->update(['updated_by' => $userId]);

        return (bool) $campaign->delete();
    }

    /**
     * Valida integralmente os pré-requisitos de disparo da campanha.
     */
    public function validateCampaign(int|string $id, int $platformId): array
    {
        $campaign = $this->getById($id, $platformId);
        $result = $this->validator->validate($campaign);

        if ($result['is_valid'] && $campaign->isDraft()) {
            $this->stateService->transition($campaign, 'READY');
        } elseif (!$result['is_valid'] && $campaign->isReady()) {
            $this->stateService->transition($campaign, 'DRAFT');
        }

        return $result;
    }

    /**
     * Gera uma pré-visualização renderizada com dados de um jogador de amostra ou simulação segura.
     */
    public function preview(int|string $id, int $platformId, ?int $samplePlayerId = null): array
    {
        $campaign = $this->getById($id, $platformId);
        $version = $campaign->templateVersion;

        if (!$version) {
            throw new InvalidArgumentException("Versão do template não encontrada para preview.");
        }

        $player = null;
        if ($samplePlayerId) {
            $player = Player::where('platform_id', $platformId)->find($samplePlayerId);
        }
        if (!$player) {
            // Busca o primeiro jogador do segmento
            $player = $this->audienceService->getEligibleQuery($campaign)->first();
        }

        $context = [
            'player' => [
                'name' => $player->name ?? 'Carlos Eduardo Santos',
                'first_name' => $player ? explode(' ', trim($player->name))[0] : 'Carlos',
                'email' => $player->email ?? 'carlos.santos@email.com',
                'phone' => $player->phone ?? '5531998877661',
                'city' => $player->city ?? 'Belo Horizonte',
                'state' => $player->state ?? 'MG',
                'status' => $player->status ?? 'ACTIVE',
                'external_id' => $player->external_id ?? 'PLY-1001',
                'created_at' => $player?->created_at ? $player->created_at->format('d/m/Y') : date('d/m/Y'),
            ],
            'platform' => [
                'name' => $campaign->platform->name ?? 'BET CRM',
            ],
        ];

        $channel = strtoupper($campaign->channel);

        if ($channel === 'EMAIL') {
            $rendered = $this->templateRenderer->renderEmail(
                $version->subject ?? '',
                $version->preheader,
                $version->html_content,
                $version->text_content,
                $context
            );

            return [
                'campaign_id' => $campaign->id,
                'channel' => 'EMAIL',
                'subject' => $rendered['subject'],
                'preheader' => $rendered['preheader'],
                'html' => $rendered['html'],
                'text' => $rendered['text'],
                'sample_recipient' => $player ? $player->masked_email : 'ca***@email.com',
                'sample_context' => $context,
            ];
        }

        $rendered = $this->templateRenderer->renderSms(
            $version->sms_content ?? '',
            $context
        );

        return [
            'campaign_id' => $campaign->id,
            'channel' => 'SMS',
            'sms' => $rendered['sms'],
            'metrics' => $rendered['metrics'],
            'sample_recipient' => $player ? $player->masked_phone : '55319****7661',
            'sample_context' => $context,
        ];
    }

    /**
     * Envia uma mensagem de teste administrativo com o conteúdo renderizado da campanha.
     */
    public function testSend(int|string $id, int $platformId, string $recipient, ?int $userId = null): array
    {
        $campaign = $this->getById($id, $platformId);
        $preview = $this->preview($id, $platformId);

        $channel = strtoupper($campaign->channel);

        $payload = new MessagePayload(
            recipient: $recipient,
            subject: $preview['subject'] ?? 'Teste de Campanha BET CRM',
            html: $preview['html'] ?? null,
            text: $preview['text'] ?? null,
            smsContent: $preview['sms'] ?? null,
            templateId: $campaign->template_id,
            templateVersionId: $campaign->template_version_id,
            platformId: $platformId,
            campaignId: $campaign->id,
            metadata: [
                'is_test' => true,
                'tested_by_user_id' => $userId,
                'campaign_id' => $campaign->id,
            ],
            idempotencyKey: 'test_campaign_' . $campaign->id . '_' . Str::random(12)
        );

        $message = $this->messageService->send($platformId, $payload, $campaign->provider_id);

        Log::info("Disparo de teste da Campanha #{$campaign->id} executado para '{$recipient}'.", [
            'campaign_id' => $campaign->id,
            'platform_id' => $platformId,
            'message_id' => $message->id,
            'user_id' => $userId,
        ]);

        return [
            'success' => true,
            'message' => "Mensagem de teste enviada com sucesso para {$recipient}.",
            'message_id' => $message->id,
            'status' => $message->status,
        ];
    }

    /**
     * Dispara ou agenda a campanha após validação rigorosa e bloqueio atômico de concorrência.
     */
    public function launch(int|string $id, int $platformId, bool $confirmation, ?int $userId = null): Campaign
    {
        if (!$confirmation) {
            throw new InvalidArgumentException("É necessária a confirmação explícita para iniciar o disparo da campanha.");
        }

        $campaign = $this->getById($id, $platformId);

        // Bloqueio atômico de concorrência no Redis contra cliques duplos simultâneos
        $lockKey = "betcrm:campaign:lock:launch:{$campaign->id}";
        $lockAcquired = false;

        try {
            $lockAcquired = (bool) Redis::set($lockKey, 1, 'EX', 30, 'NX');
            if (!$lockAcquired) {
                throw new InvalidArgumentException("A campanha já está sendo iniciada por outro processo ou operador.");
            }
        } catch (\Throwable $e) {
            if (!$lockAcquired && str_contains($e->getMessage(), 'sendo iniciada')) {
                throw $e;
            }
        }

        try {
            // Executa validação pré-disparo
            $validation = $this->validator->validate($campaign);
            if (!$validation['is_valid']) {
                $errorMsg = implode('; ', $validation['errors']);
                throw new InvalidArgumentException("Campanha inválida para disparo: {$errorMsg}");
            }

            // Agendada ou Imediata?
            if ($campaign->scheduled_at && $campaign->scheduled_at->isFuture()) {
                $this->stateService->transition($campaign, 'SCHEDULED', [
                    'updated_by' => $userId,
                ]);

                Log::info("Campanha #{$campaign->id} agendada para {$campaign->scheduled_at->toIso8601String()}.", [
                    'campaign_id' => $campaign->id,
                    'scheduled_at' => $campaign->scheduled_at,
                ]);

                return $campaign->fresh();
            }

            // Transição para PROCESSING
            $this->stateService->transition($campaign, 'PROCESSING', [
                'started_at' => now(),
                'updated_by' => $userId,
            ]);

            // Dispara job de geração de snapshot e envio
            DispatchCampaignJob::dispatch($campaign->id);

            Log::info("Campanha #{$campaign->id} iniciada com sucesso na fila de processamento.", [
                'campaign_id' => $campaign->id,
                'platform_id' => $platformId,
                'user_id' => $userId,
            ]);

            return $campaign->fresh();
        } finally {
            try {
                Redis::del($lockKey);
            } catch (\Throwable) {}
        }
    }

    /**
     * Pausa uma campanha em processamento.
     */
    public function pause(int|string $id, int $platformId, ?int $userId = null): Campaign
    {
        $campaign = $this->getById($id, $platformId);

        if (!$campaign->canBePaused()) {
            throw new InvalidArgumentException("A campanha não pode ser pausada no status atual '{$campaign->status}'.");
        }

        $this->stateService->transition($campaign, 'PAUSED', [
            'updated_by' => $userId,
        ]);

        return $campaign->fresh();
    }

    /**
     * Retoma uma campanha pausada.
     */
    public function resume(int|string $id, int $platformId, ?int $userId = null): Campaign
    {
        $campaign = $this->getById($id, $platformId);

        if (!$campaign->canBeResumed()) {
            throw new InvalidArgumentException("Somente campanhas pausadas podem ser retomadas.");
        }

        $this->stateService->transition($campaign, 'PROCESSING', [
            'updated_by' => $userId,
        ]);

        // Retoma o job de criação de mensagens para os destinatários pendentes
        CreateCampaignMessagesJob::dispatch($campaign->id);

        return $campaign->fresh();
    }

    /**
     * Cancela uma campanha e marca todos os destinatários pendentes como CANCELLED.
     */
    public function cancel(int|string $id, int $platformId, ?int $userId = null): Campaign
    {
        $campaign = $this->getById($id, $platformId);

        if (!$campaign->canBeCancelled()) {
            throw new InvalidArgumentException("A campanha não pode ser cancelada no status atual '{$campaign->status}'.");
        }

        return DB::transaction(function () use ($campaign, $userId) {
            $this->stateService->transition($campaign, 'CANCELLED', [
                'updated_by' => $userId,
            ]);

            // Cancela destinatários que ainda não tiveram mensagens criadas
            CampaignRecipient::where('campaign_id', $campaign->id)
                ->where('status', 'PENDING')
                ->update([
                    'status' => 'CANCELLED',
                    'reason' => 'CAMPAIGN_CANCELLED_BY_OPERATOR',
                ]);

            return $campaign->fresh();
        });
    }

    /**
     * Retorna estatísticas de entrega e progresso em tempo real da campanha.
     */
    public function getStats(int|string $id, int $platformId): array
    {
        $campaign = $this->getById($id, $platformId);

        $totalRecipients = CampaignRecipient::where('campaign_id', $campaign->id)->count();
        $skipped = CampaignRecipient::where('campaign_id', $campaign->id)->where('status', 'SKIPPED')->count();
        $pending = CampaignRecipient::where('campaign_id', $campaign->id)->where('status', 'PENDING')->count();
        $queued = CampaignRecipient::where('campaign_id', $campaign->id)->where('status', 'QUEUED')->count();
        $cancelled = CampaignRecipient::where('campaign_id', $campaign->id)->where('status', 'CANCELLED')->count();

        // Métricas de mensagens
        $messagesQuery = Message::where('campaign_id', $campaign->id);
        $messagesCreated = (clone $messagesQuery)->count();
        $messagesSent = (clone $messagesQuery)->whereIn('status', ['SENT', 'DELIVERED'])->count();
        $messagesDelivered = (clone $messagesQuery)->where('status', 'DELIVERED')->count();
        $messagesFailed = (clone $messagesQuery)->whereIn('status', ['FAILED', 'RATE_LIMITED'])->count();

        $eligibleCount = $campaign->eligible_count > 0 ? $campaign->eligible_count : ($totalRecipients - $skipped);
        $progress = 0.0;
        if ($eligibleCount > 0) {
            $progress = round(min(100.0, ($messagesCreated / $eligibleCount) * 100), 1);
        } elseif ($campaign->isCompleted()) {
            $progress = 100.0;
        }

        return [
            'campaign_id' => $campaign->id,
            'status' => $campaign->status,
            'audience_count' => $totalRecipients,
            'eligible_count' => $eligibleCount,
            'skipped_count' => $skipped,
            'pending_count' => $pending,
            'queued_count' => $queued,
            'messages_created' => $messagesCreated,
            'sent_count' => $messagesSent,
            'delivered_count' => $messagesDelivered,
            'failed_count' => $messagesFailed,
            'cancelled_count' => $cancelled,
            'progress_percentage' => $progress,
            'scheduled_at' => $campaign->scheduled_at?->toIso8601String(),
            'started_at' => $campaign->started_at?->toIso8601String(),
            'completed_at' => $campaign->completed_at?->toIso8601String(),
        ];
    }

    /**
     * Retorna a lista paginada de destinatários do snapshot da campanha (LGPD masked).
     */
    public function getRecipients(int|string $id, int $platformId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $campaign = $this->getById($id, $platformId);

        $query = CampaignRecipient::where('campaign_id', $campaign->id)
            ->with(['player:id,name,email,phone,status']);

        if (!empty($filters['status']) && $filters['status'] !== 'ALL') {
            $query->where('status', strtoupper($filters['status']));
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('recipient', 'LIKE', "%{$search}%")
                  ->orWhere('reason', 'LIKE', "%{$search}%");
            });
        }

        return $query->orderBy('id', 'asc')->paginate($perPage);
    }

    /**
     * Retorna a lista paginada de mensagens geradas pela campanha.
     */
    public function getMessages(int|string $id, int $platformId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $campaign = $this->getById($id, $platformId);

        $query = Message::where('campaign_id', $campaign->id)
            ->with(['provider:id,name,driver']);

        if (!empty($filters['status']) && $filters['status'] !== 'ALL') {
            $query->where('status', strtoupper($filters['status']));
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }
}
