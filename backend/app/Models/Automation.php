<?php

namespace App\Models;

use App\Enums\AutomationStatus;
use App\Enums\AutomationTriggerType;
use App\Models\Traits\BelongsToPlatform;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Automation extends Model
{
    use HasFactory, SoftDeletes, BelongsToPlatform;

    protected $table = 'automations';

    protected $fillable = [
        'platform_id',
        'name',
        'description',
        'status',
        'trigger_type',
        'settings',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'status' => AutomationStatus::class,
        'trigger_type' => AutomationTriggerType::class,
        'settings' => 'array',
    ];

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function nodes(): HasMany
    {
        return $this->hasMany(AutomationNode::class);
    }

    public function edges(): HasMany
    {
        return $this->hasMany(AutomationEdge::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(AutomationRun::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(AutomationLog::class);
    }

    public function isActive(): bool
    {
        return $this->status === AutomationStatus::ACTIVE;
    }

    public function isPaused(): bool
    {
        return $this->status === AutomationStatus::PAUSED;
    }

    public function isDraft(): bool
    {
        return $this->status === AutomationStatus::DRAFT;
    }

    public function triggerNode(): ?AutomationNode
    {
        return $this->nodes()->where('node_type', 'TRIGGER')->first();
    }
}
