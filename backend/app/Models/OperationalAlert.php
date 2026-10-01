<?php

namespace App\Models;

use App\Models\Traits\BelongsToPlatform;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationalAlert extends Model
{
    use HasFactory, BelongsToPlatform;

    protected $table = 'operational_alerts';

    protected $fillable = [
        'platform_id',
        'alert_rule_id',
        'metric',
        'severity',
        'status',
        'title',
        'message',
        'current_value',
        'threshold_value',
        'triggered_at',
        'acknowledged_at',
        'acknowledged_by',
        'resolved_at',
        'resolved_by',
        'resolution_notes',
        'metadata',
    ];

    protected $casts = [
        'current_value' => 'float',
        'threshold_value' => 'float',
        'triggered_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'resolved_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(AlertRule::class, 'alert_rule_id');
    }

    public function acknowledgedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function resolvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
