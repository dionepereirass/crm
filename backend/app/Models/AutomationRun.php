<?php

namespace App\Models;

use App\Enums\AutomationRunStatus;
use App\Models\Traits\BelongsToPlatform;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AutomationRun extends Model
{
    use HasFactory, BelongsToPlatform;

    protected $table = 'automation_runs';

    protected $fillable = [
        'automation_id',
        'platform_id',
        'player_id',
        'status',
        'current_node_id',
        'idempotency_key',
        'started_at',
        'completed_at',
        'last_error',
        'retry_count',
        'metadata',
    ];

    protected $casts = [
        'status' => AutomationRunStatus::class,
        'metadata' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function automation(): BelongsTo
    {
        return $this->belongsTo(Automation::class);
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function currentNode(): BelongsTo
    {
        return $this->belongsTo(AutomationNode::class, 'current_node_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(AutomationStep::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(AutomationLog::class);
    }

    public function isRunning(): bool
    {
        return $this->status === AutomationRunStatus::RUNNING;
    }

    public function isWaiting(): bool
    {
        return $this->status === AutomationRunStatus::WAITING;
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [
            AutomationRunStatus::COMPLETED,
            AutomationRunStatus::FAILED,
            AutomationRunStatus::CANCELLED,
            AutomationRunStatus::SKIPPED,
        ]);
    }
}
