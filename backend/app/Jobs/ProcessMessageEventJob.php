<?php

namespace App\Jobs;

use App\Enums\MessageEventType;
use App\Services\Analytics\CampaignAnalyticsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessMessageEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $campaignId,
        public string $eventType,
        public int $messageId
    ) {
        $this->onQueue('analytics');
    }

    public function handle(CampaignAnalyticsService $analyticsService): void
    {
        try {
            $type = MessageEventType::fromRaw($this->eventType);
            $analyticsService->recordEvent($this->campaignId, $type, $this->messageId);
        } catch (\Throwable $e) {
            Log::error("ProcessMessageEventJob falhou para Campanha #{$this->campaignId}: {$e->getMessage()}");
            throw $e;
        }
    }
}
