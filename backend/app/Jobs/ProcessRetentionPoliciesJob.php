<?php

namespace App\Jobs;

use App\Models\Platform;
use App\Services\Privacy\RetentionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessRetentionPoliciesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 900;

    public function __construct(
        public ?int $platformId = null
    ) {
        $this->onQueue('default');
    }

    public function handle(RetentionService $retentionService): void
    {
        if ($this->platformId) {
            $platforms = Platform::where('id', $this->platformId)->get();
        } else {
            $platforms = Platform::where('status', 'ACTIVE')->get();
        }

        foreach ($platforms as $platform) {
            try {
                $summary = $retentionService->processPolicies($platform->id);
                Log::info("ProcessRetentionPoliciesJob: Políticas processadas para plataforma #{$platform->id}", $summary);
            } catch (\Throwable $e) {
                Log::error("ProcessRetentionPoliciesJob: Falha na plataforma #{$platform->id}: {$e->getMessage()}");
            }
        }
    }
}
