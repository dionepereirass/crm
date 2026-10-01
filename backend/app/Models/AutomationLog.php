<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'automation_logs';

    protected $fillable = [
        'automation_id',
        'automation_run_id',
        'automation_step_id',
        'level',
        'event',
        'message',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function automation(): BelongsTo
    {
        return $this->belongsTo(Automation::class);
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(AutomationRun::class, 'automation_run_id');
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(AutomationStep::class, 'automation_step_id');
    }

    public static function log(
        int $automationId,
        string $event,
        string $message,
        string $level = 'INFO',
        ?int $runId = null,
        ?int $stepId = null,
        ?array $metadata = null
    ): self {
        // Sanitizar metadata contra senhas, secrets, headers sensíveis
        $sanitizedMetadata = self::sanitizeMetadata($metadata);

        return self::create([
            'automation_id' => $automationId,
            'automation_run_id' => $runId,
            'automation_step_id' => $stepId,
            'level' => strtoupper($level),
            'event' => $event,
            'message' => $message,
            'metadata' => $sanitizedMetadata,
            'created_at' => now(),
        ]);
    }

    protected static function sanitizeMetadata(?array $metadata): ?array
    {
        if (empty($metadata)) {
            return null;
        }

        $sensitiveKeys = ['password', 'secret', 'token', 'authorization', 'api_key', 'apikey', 'key', 'auth'];

        array_walk_recursive($metadata, function (&$value, $key) use ($sensitiveKeys) {
            if (in_array(strtolower((string) $key), $sensitiveKeys, true)) {
                $value = '***REDACTED***';
            }
        });

        return $metadata;
    }
}
