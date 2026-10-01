<?php

namespace App\Models;

use App\Models\Traits\BelongsToPlatform;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AlertRule extends Model
{
    use HasFactory, BelongsToPlatform;

    protected $table = 'alert_rules';

    protected $fillable = [
        'platform_id',
        'name',
        'metric',
        'operator',
        'threshold',
        'severity',
        'cooldown_minutes',
        'active',
        'last_evaluated_at',
        'last_triggered_at',
        'metadata',
    ];

    protected $casts = [
        'threshold' => 'float',
        'cooldown_minutes' => 'integer',
        'active' => 'boolean',
        'last_evaluated_at' => 'datetime',
        'last_triggered_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(OperationalAlert::class, 'alert_rule_id')->orderBy('triggered_at', 'desc');
    }

    /**
     * Verifica se a regra está dentro da janela de cooldown.
     */
    public function isInCooldown(): bool
    {
        if (!$this->last_triggered_at) {
            return false;
        }

        return $this->last_triggered_at->addMinutes($this->cooldown_minutes)->isFuture();
    }
}
