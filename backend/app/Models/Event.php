<?php

namespace App\Models;

use App\Enums\EventProcessingStatus;
use App\Models\Traits\BelongsToPlatform;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Event extends Model
{
    use HasFactory, BelongsToPlatform;

    protected $fillable = [
        'uuid',
        'platform_id',
        'event_type_id',
        'external_event_id',
        'player_id',
        'occurred_at',
        'payload',
        'normalized_payload',
        'processing_status',
        'attempts',
        'error_message',
        'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'normalized_payload' => 'array',
        'occurred_at' => 'datetime',
        'processed_at' => 'datetime',
        'processing_status' => EventProcessingStatus::class,
        'attempts' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    public function eventType(): BelongsTo
    {
        return $this->belongsTo(EventType::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function webhookLogs(): HasMany
    {
        return $this->hasMany(WebhookLog::class);
    }
}
