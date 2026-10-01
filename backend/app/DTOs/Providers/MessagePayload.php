<?php

namespace App\DTOs\Providers;

class MessagePayload
{
    public function __construct(
        public string $recipient,
        public ?string $recipientName = null,
        public ?string $subject = null,
        public ?string $html = null,
        public ?string $text = null,
        public ?string $smsContent = null,
        public ?int $templateId = null,
        public ?int $templateVersionId = null,
        public ?int $platformId = null,
        public ?int $campaignId = null,
        public array $metadata = [],
        public ?string $idempotencyKey = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            recipient: $data['recipient'],
            recipientName: $data['recipient_name'] ?? null,
            subject: $data['subject'] ?? null,
            html: $data['html'] ?? $data['html_content'] ?? null,
            text: $data['text'] ?? $data['text_content'] ?? null,
            smsContent: $data['sms_content'] ?? $data['content'] ?? null,
            templateId: isset($data['template_id']) ? (int) $data['template_id'] : null,
            templateVersionId: isset($data['template_version_id']) ? (int) $data['template_version_id'] : null,
            platformId: isset($data['platform_id']) ? (int) $data['platform_id'] : null,
            campaignId: isset($data['campaign_id']) ? (int) $data['campaign_id'] : null,
            metadata: $data['metadata'] ?? [],
            idempotencyKey: $data['idempotency_key'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'recipient' => $this->recipient,
            'recipient_name' => $this->recipientName,
            'subject' => $this->subject,
            'html' => $this->html,
            'text' => $this->text,
            'sms_content' => $this->smsContent,
            'template_id' => $this->templateId,
            'template_version_id' => $this->templateVersionId,
            'platform_id' => $this->platformId,
            'campaign_id' => $this->campaignId,
            'metadata' => $this->metadata,
            'idempotency_key' => $this->idempotencyKey,
        ];
    }
}
