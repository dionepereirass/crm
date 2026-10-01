<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Platform extends Model
{
    use HasFactory;

    protected $table = 'platforms';

    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'status',
        'api_key',
        'webhook_secret',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
    ];

    protected $hidden = [
        'webhook_secret',
        'api_key',
    ];

    protected static function booted(): void
    {
        static::creating(function (Platform $platform) {
            if (empty($platform->uuid)) {
                $platform->uuid = (string) Str::uuid();
            }
            if (empty($platform->api_key)) {
                $platform->api_key = 'pk_' . Str::random(32);
            }
            if (empty($platform->webhook_secret)) {
                $platform->webhook_secret = 'whsec_' . Str::random(32);
            }
        });

        static::saved(function (Platform $platform) {
            \Illuminate\Support\Facades\Cache::forget("betcrm:platform:id:{$platform->id}");
            \Illuminate\Support\Facades\Cache::forget("betcrm:platform:slug:{$platform->slug}");
        });

        static::deleted(function (Platform $platform) {
            \Illuminate\Support\Facades\Cache::forget("betcrm:platform:id:{$platform->id}");
            \Illuminate\Support\Facades\Cache::forget("betcrm:platform:slug:{$platform->slug}");
        });
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'platform_users')
            ->withPivot('created_at');
    }

    public function eventTypes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EventType::class);
    }

    public function events(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function webhookLogs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(WebhookLog::class);
    }

    public function segments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Segment::class);
    }

    public function templates(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Template::class);
    }

    public function providers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Provider::class);
    }

    public function messages(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function campaigns(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function campaignMetrics(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CampaignMetric::class);
    }

    public function automations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Automation::class);
    }

    public function automationRuns(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AutomationRun::class);
    }

    public function dataSubjectRequests(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(DataSubjectRequest::class);
    }

    public function retentionPolicies(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(RetentionPolicy::class);
    }

    public function auditLogs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'ACTIVE';
    }
}
