<?php

namespace App\Models;

use App\Enums\EventProcessingStatus;
use App\Models\Traits\BelongsToPlatform;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class WebhookLog extends Model
{
    use HasFactory, BelongsToPlatform;

    protected $fillable = [
        'uuid',
        'platform_id',
        'event_id',
        'external_event_id',
        'endpoint',
        'signature_valid',
        'processing_status',
        'http_status',
        'payload',
        'headers',
        'error_message',
        'ip_address',
        'user_agent',
        'received_at',
        'processed_at',
    ];

    protected $casts = [
        'signature_valid' => 'boolean',
        'http_status' => 'integer',
        'payload' => 'array',
        'headers' => 'array',
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
        'processing_status' => EventProcessingStatus::class,
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

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
