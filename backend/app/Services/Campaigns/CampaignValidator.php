<?php

namespace App\Services\Campaigns;

use App\Models\Campaign;
use App\Models\Provider;
use App\Models\Segment;
use App\Models\Template;
use App\Models\TemplateVersion;

class CampaignValidator
{
    public function __construct(
        protected CampaignAudienceService $audienceService
    ) {}

    /**
     * Executa a bateria completa de validações pré-disparo da campanha.
     */
    public function validate(Campaign $campaign): array
    {
        $errors = [];
        $warnings = [];

        // 1. Validação de Canal
        $channel = strtoupper($campaign->channel);
        if (!in_array($channel, ['EMAIL', 'SMS'])) {
            $errors[] = "Canal de envio '{$campaign->channel}' não é suportado. Utilize EMAIL ou SMS.";
        }

        // 2. Validação do Segmento
        $segment = $campaign->segment;
        if (!$segment) {
            $errors[] = "Segmento de público não selecionado ou inexistente.";
        } else {
            if ($segment->platform_id !== $campaign->platform_id) {
                $errors[] = "Violação de isolamento multi-plataforma: o segmento pertence a outra plataforma.";
            }
            if ($segment->status !== 'ACTIVE') {
                $errors[] = "O segmento '{$segment->name}' não está ativo (Status: {$segment->status}).";
            }
        }

        // 3. Validação do Template
        $template = $campaign->template;
        if (!$template) {
            $errors[] = "Template de comunicação não selecionado ou inexistente.";
        } else {
            if ($template->platform_id !== $campaign->platform_id) {
                $errors[] = "Violação de isolamento multi-plataforma: o template pertence a outra plataforma.";
            }
            if ($template->status !== 'ACTIVE') {
                $errors[] = "O template '{$template->name}' não está ativo (Status: {$template->status}).";
            }
            if (strtoupper($template->channel) !== $channel) {
                $errors[] = "Incompatibilidade de canal: a campanha é {$channel}, mas o template é {$template->channel}.";
            }
        }

        // 4. Validação da Versão do Template
        $version = $campaign->templateVersion;
        if (!$version) {
            $errors[] = "Versão publicada do template não vinculada à campanha.";
        } else {
            if ($version->template_id !== $campaign->template_id) {
                $errors[] = "A versão vinculada não pertence ao template selecionado.";
            }
            if (!$version->isPublished()) {
                $errors[] = "A versão do template vinculada não está publicada (Status: {$version->status}).";
            }

            // Verifica se a versão tem conteúdo para o canal
            if ($channel === 'EMAIL' && empty($version->subject) && empty($version->html_content)) {
                $errors[] = "A versão do template não possui assunto ou conteúdo HTML configurado.";
            }
            if ($channel === 'SMS' && empty($version->sms_content)) {
                $errors[] = "A versão do template não possui conteúdo de SMS configurado.";
            }
        }

        // 5. Validação do Provedor de Mensageria
        if ($campaign->provider_id) {
            $provider = $campaign->provider;
            if (!$provider) {
                $errors[] = "Provedor selecionado não foi encontrado.";
            } else {
                if ($provider->platform_id !== $campaign->platform_id) {
                    $errors[] = "Violação de isolamento: o provedor pertence a outra plataforma.";
                }
                if ($provider->status !== 'ACTIVE') {
                    $errors[] = "O provedor '{$provider->name}' está inativo ou com falha (Status: {$provider->status}).";
                }
                if (strtoupper($provider->channel) !== $channel) {
                    $errors[] = "Incompatibilidade de canal: a campanha é {$channel}, mas o provedor suporta {$provider->channel}.";
                }
            }
        } else {
            // Se nenhum provedor for especificado explicitamente, verifica se há pelo menos 1 provedor ativo para o canal na plataforma
            $hasActiveProvider = Provider::where('platform_id', $campaign->platform_id)
                ->where('channel', $channel)
                ->where('status', 'ACTIVE')
                ->exists();

            if (!$hasActiveProvider) {
                $errors[] = "Nenhum provedor ativo cadastrado na plataforma para o canal {$channel}.";
            }
        }

        // 6. Validação de Audiência e Consentimento
        $audience = $this->audienceService->calculate($campaign);

        if ($audience['total_segment'] === 0) {
            $errors[] = "O segmento selecionado não possui nenhum jogador.";
        } elseif ($audience['eligible'] === 0) {
            $errors[] = "Nenhum jogador do segmento possui consentimento de marketing ativo e contato válido para o canal {$channel}.";
        } elseif ($audience['eligible'] < $audience['total_segment']) {
            $skippedCount = $audience['total_segment'] - $audience['eligible'];
            $warnings[] = "Atenção: {$skippedCount} jogador(es) do segmento serão ignorados por falta de consentimento ou contato inválido.";
        }

        return [
            'is_valid' => count($errors) === 0,
            'errors' => $errors,
            'warnings' => $warnings,
            'audience' => $audience,
        ];
    }
}
