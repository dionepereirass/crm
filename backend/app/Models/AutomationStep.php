<?php

namespace App\Models;

use App\Enums\AutomationStepStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AutomationStep extends Model
{
    use HasFactory;

    protected $table = 'automation_steps';

    protected $fillable = [
        'automation_run_id',
        'node_id',
        'status',
        'input',
        'output',
        'error',
        'started_at',
        'completed_at',
        'retry_count',
    ];

    protected $casts = [
        'status' => AutomationStepStatus::class,
        'input' => 'array',
        'output' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(AutomationRun::class, 'automation_run_id');
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(AutomationNode::class, 'node_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(AutomationLog::class, 'automation_step_id');
    }
}
