<?php

namespace App\Models;

use App\Models\Traits\BelongsToPlatform;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Campaign extends Model
{
    use HasFactory, BelongsToPlatform, SoftDeletes;

    protected $table = 'campaigns';

    protected $fillable = [
        'uuid',
        'platform_id',
        'name',
        'description',
        'channel',
        'status',
        'segment_id',
        'template_id',
        'template_version_id',
        'provider_id',
        'from_name',
        'from_email',
        'reply_to',
        'sms_sender',
        'audience_count',
        'eligible_count',
        'messages_created',
        'messages_sent',
        'messages_failed',
        'scheduled_at',
        'started_at',
        'paused_at',
        'completed_at',
        'cancelled_at',
        'failed_at',
        'failure_reason',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'audience_count' => 'integer',
        'eligible_count' => 'integer',
        'messages_created' => 'integer',
        'messages_sent' => 'integer',
        'messages_failed' => 'integer',
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'paused_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'failed_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->status)) {
                $model->status = 'DRAFT';
            }
        });
    }

    // Relationships
    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    public function segment(): BelongsTo
    {
        return $this->belongsTo(Segment::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(TemplateVersion::class, 'template_version_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(CampaignRecipient::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function metric(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(CampaignMetric::class);
    }

    // State Helpers
    public function isDraft(): bool
    {
        return $this->status === 'DRAFT';
    }

    public function isReady(): bool
    {
        return $this->status === 'READY';
    }

    public function isScheduled(): bool
    {
        return $this->status === 'SCHEDULED';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'PROCESSING';
    }

    public function isPaused(): bool
    {
        return $this->status === 'PAUSED';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'COMPLETED';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'CANCELLED';
    }

    public function isFailed(): bool
    {
        return $this->status === 'FAILED';
    }

    public function canBeEdited(): bool
    {
        return in_array($this->status, ['DRAFT', 'READY']);
    }

    public function canBeLaunched(): bool
    {
        return in_array($this->status, ['READY', 'SCHEDULED']);
    }

    public function canBePaused(): bool
    {
        return $this->status === 'PROCESSING';
    }

    public function canBeResumed(): bool
    {
        return $this->status === 'PAUSED';
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['DRAFT', 'VALIDATING', 'READY', 'SCHEDULED', 'PROCESSING', 'PAUSED']);
    }

    public function getProgressPercentageAttribute(): float
    {
        if ($this->eligible_count <= 0) {
            return $this->isCompleted() ? 100.0 : 0.0;
        }

        $processed = $this->messages_created;
        $pct = ($processed / $this->eligible_count) * 100;
        return round(min(100.0, max(0.0, $pct)), 1);
    }
}
