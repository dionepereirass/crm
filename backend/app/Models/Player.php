<?php

namespace App\Models;

use App\Models\Traits\BelongsToPlatform;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Player extends Model
{
    use HasFactory, SoftDeletes, BelongsToPlatform;

    protected $table = 'players';

    protected $fillable = [
        'platform_id',
        'external_id',
        'name',
        'email',
        'phone',
        'whatsapp',
        'cpf',
        'birth_date',
        'gender',
        'city',
        'state',
        'zip_code',
        'status',
        'source',
        'affiliate',
        'promo_code',
        'custom_fields',
        'last_login_at',
        'last_activity_at',
        'registered_at',
    ];

    protected $casts = [
        'custom_fields' => 'array',
        'birth_date' => 'date',
        'last_login_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'registered_at' => 'datetime',
    ];

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'player_tags')
            ->withPivot('created_at');
    }

    public function consents(): HasMany
    {
        return $this->hasMany(Consent::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function automationRuns(): HasMany
    {
        return $this->hasMany(AutomationRun::class);
    }

    public function consentHistory(): HasMany
    {
        return $this->hasMany(ConsentHistory::class);
    }

    public function dataSubjectRequests(): HasMany
    {
        return $this->hasMany(DataSubjectRequest::class);
    }

    public function hasMarketingConsent(string $channel): bool
    {
        $upper = strtoupper($channel);
        $type = "MARKETING_{$upper}";

        return $this->consents()
            ->where(function ($q) use ($upper, $type) {
                $q->where('channel', $upper)
                  ->orWhere('type', $type)
                  ->orWhere('type', $upper);
            })
            ->where(function ($q) {
                $q->where('is_granted', true)
                  ->orWhere('status', 'GRANTED');
            })
            ->whereNull('revoked_at')
            ->exists();
    }

    /**
     * LGPD Masking: email
     * e.g. "dionisio@example.com" => "di***@example.com"
     */
    public function getMaskedEmailAttribute(): ?string
    {
        if (empty($this->email)) return null;

        $parts = explode('@', $this->email);
        if (count($parts) !== 2) return $this->email;

        $name = $parts[0];
        $domain = $parts[1];

        $visibleLen = min(2, mb_strlen($name));
        $prefix = mb_substr($name, 0, $visibleLen);

        return $prefix . '***@' . $domain;
    }

    /**
     * LGPD Masking: phone
     * e.g. "5531999991234" => "55319****1234"
     */
    public function getMaskedPhoneAttribute(): ?string
    {
        if (empty($this->phone)) return null;

        $len = mb_strlen($this->phone);
        if ($len <= 6) return '****';

        $prefix = mb_substr($this->phone, 0, 5);
        $suffix = mb_substr($this->phone, -4);

        return $prefix . '****' . $suffix;
    }

    /**
     * LGPD Masking: CPF
     * e.g. "12345678900" => "***.456.789-**"
     */
    public function getMaskedCpfAttribute(): ?string
    {
        if (empty($this->cpf)) return null;

        $clean = preg_replace('/\D/', '', $this->cpf);
        if (strlen($clean) !== 11) return '***.***.***-**';

        return '***.' . substr($clean, 3, 3) . '.' . substr($clean, 6, 3) . '-**';
    }
}
