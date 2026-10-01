<?php

namespace App\Models;

use App\Models\Traits\BelongsToPlatform;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Provider extends Model
{
    use HasFactory, BelongsToPlatform, SoftDeletes;

    protected $fillable = [
        'uuid',
        'platform_id',
        'name',
        'channel',
        'driver',
        'status',
        'is_default',
        'priority',
        'is_fallback',
        'rate_limit_per_minute',
        'configuration',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_fallback' => 'boolean',
        'priority' => 'integer',
        'rate_limit_per_minute' => 'integer',
        'configuration' => 'array',
    ];

    protected $appends = [
        'credentials_configured',
    ];

    protected $hidden = [
        'credentials',
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

    public function credentials(): HasOne
    {
        return $this->hasOne(ProviderCredential::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ProviderLog::class)->orderBy('created_at', 'desc');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function getCredentialsConfiguredAttribute(): bool
    {
        if ($this->driver === 'fake_email' || $this->driver === 'fake_sms') {
            return true;
        }

        return $this->relationLoaded('credentials')
            ? $this->credentials !== null
            : $this->credentials()->exists();
    }

    public function isActive(): bool
    {
        return $this->status === 'ACTIVE';
    }

    public function isEmail(): bool
    {
        return strtoupper($this->channel) === 'EMAIL';
    }

    public function isSms(): bool
    {
        return strtoupper($this->channel) === 'SMS';
    }
}
