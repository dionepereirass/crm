<?php

namespace App\Jobs;

use App\Services\Reports\ReportsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GenerateAnalyticsExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 180;

    public function __construct(
        public int $platformId,
        public string $reportType,
        public array $filters,
        public string $format = 'CSV',
        public ?int $userId = null,
        public ?string $exportId = null
    ) {
        $this->exportId = $this->exportId ?? Str::uuid()->toString();
    }

    public function handle(ReportsService $reportsService): void
    {
        Log::info("Iniciando geração assíncrona de relatório [{$this->reportType}] para plataforma #{$this->platformId}.", [
            'export_id' => $this->exportId,
            'format' => $this->format,
            'user_id' => $this->userId,
        ]);

        $data = $reportsService->generateReportData($this->platformId, $this->reportType, $this->filters, true);

        $filename = "exports/{$this->platformId}/{$this->reportType}_{$this->exportId}." . strtolower($this->format);
        $content = strtoupper($this->format) === 'JSON'
            ? json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            : $reportsService->exportToCsv($data);

        Storage::disk('local')->put($filename, $content);

        Log::info("Relatório [{$this->reportType}] gerado com sucesso em '{$filename}'.", [
            'export_id' => $this->exportId,
            'rows_count' => count($data),
        ]);
    }
}
