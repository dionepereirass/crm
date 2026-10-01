<?php

namespace App\Models;

use App\Models\Traits\BelongsToPlatform;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderLog extends Model
{
    use HasFactory, BelongsToPlatform;

    public $timestamps = false;

    protected $fillable = [
        'platform_id',
        'provider_id',
        'channel',
        'action',
        'status',
        'provider_message_id',
        'error_code',
        'error_message',
        'latency_ms',
        'request_metadata',
        'response_metadata',
        'created_at',
    ];

    protected $casts = [
        'latency_ms' => 'integer',
        'request_metadata' => 'array',
        'response_metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }
}
