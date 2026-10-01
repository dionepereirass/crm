<?php

namespace App\Services\Automations\Actions;

use App\DTOs\Providers\MessagePayload;
use App\Models\AutomationRun;
use App\Models\AutomationStep;
use App\Models\Template;
use App\Services\Messaging\MessageService;
use App\Services\Templates\TemplateRenderer;
use InvalidArgumentException;

class SendEmailAction implements ActionHandlerInterface
{
    public function __construct(
        protected MessageService $messageService,
        protected TemplateRenderer $renderer,
        protected ?\App\Services\Privacy\ConsentPolicyService $consentPolicyService = null
    ) {
        $this->consentPolicyService = $this->consentPolicyService ?? app(\App\Services\Privacy\ConsentPolicyService::class);
    }

    public function handle(AutomationRun $run, AutomationStep $step, array $config): array
    {
        $player = $run->player;
        if (!$player) {
            return [
                'status' => 'FAILED',
                'error' => 'Jogador não associado à execução da automação.',
            ];
        }

        // 1. Governança LGPD Obrigatória
        if (!$this->consentPolicyService->canSendMarketingEmail($player)) {
            return [
                'status' => 'SKIPPED',
                'reason' => 'MARKETING_CONSENT_MISSING',
                'message' => 'Disparo abortado: Jogador sem consentimento ativo para canal E-mail (LGPD).',
            ];
        }

        if (empty($player->email)) {
            return [
                'status' => 'SKIPPED',
                'reason' => 'NO_EMAIL_ADDRESS',
                'message' => 'Disparo abortado: Jogador não possui endereço de e-mail cadastrado.',
            ];
        }

        // 2. Resolução do Template com Isolamento de Plataforma
        $templateId = $config['template_id'] ?? null;
        if (!$templateId) {
            throw new InvalidArgumentException("ID do template não configurado na ação SEND_EMAIL.");
        }

        $template = Template::withoutGlobalScopes()
            ->where('platform_id', $run->platform_id)
            ->where('id', $templateId)
            ->first();

        if (!$template || $template->channel !== 'EMAIL') {
            throw new InvalidArgumentException("Template #{$templateId} não encontrado ou incompatível com canal E-mail.");
        }

        // 3. Resolução da Versão (usa versão publicada ou especificada)
        $version = null;
        if (!empty($config['template_version_id'])) {
            $version = $template->versions()->find($config['template_version_id']);
        } else {
            $version = $template->publishedVersion();
        }

        if (!$version) {
            throw new InvalidArgumentException("Nenhuma versão publicada encontrada para o template #{$templateId}.");
        }

        // 4. Renderização com Variáveis do Jogador
        $contextVariables = array_merge([
            'name' => $player->name,
            'first_name' => explode(' ', trim($player->name ?? ''))[0] ?? '',
            'email' => $player->email,
            'cpf' => $player->cpf,
            'external_id' => $player->external_id,
        ], $run->metadata['event_payload'] ?? [], $config['custom_variables'] ?? []);

        $rendered = $this->renderer->renderEmail(
            $version->subject ?? $template->name,
            $version->preheader ?? null,
            $version->html_content ?? $version->body_html ?? '',
            $version->text_content ?? $version->body_text ?? null,
            $contextVariables
        );

        $renderedSubject = $rendered['subject'];
        $renderedHtml = $rendered['html'];
        $renderedText = $rendered['text'];

        // 5. Construção do Payload e Idempotência Estrita
        $idempotencyKey = "automation:{$run->automation_id}:run:{$run->id}:step:{$step->id}:channel:email";

        $payload = new MessagePayload(
            recipient: $player->email,
            recipientName: $player->name,
            subject: $renderedSubject,
            html: $renderedHtml,
            text: $renderedText,
            templateId: $template->id,
            templateVersionId: $version->id,
            platformId: $run->platform_id,
            campaignId: null,
            metadata: [
                'automation_id' => $run->automation_id,
                'automation_run_id' => $run->id,
                'automation_step_id' => $step->id,
                'player_id' => $player->id,
                'action' => 'SEND_EMAIL',
            ],
            idempotencyKey: $idempotencyKey
        );

        $providerId = !empty($config['provider_id']) ? (int) $config['provider_id'] : null;

        $message = $this->messageService->send($run->platform_id, $payload, $providerId);

        return [
            'status' => 'COMPLETED',
            'message_id' => $message->id,
            'recipient' => $player->masked_email ?? $player->email,
            'template_id' => $template->id,
            'provider_id' => $message->provider_id,
        ];
    }
}
