<?php

namespace App\Models;

use App\Enums\ConsentStatus;
use App\Enums\ConsentType;
use App\Models\Scopes\PlatformScope;
use App\Models\Traits\BelongsToPlatform;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Consent extends Model
{
    use HasFactory, BelongsToPlatform;

    protected $table = 'consents';

    protected $fillable = [
        'player_id',
        'platform_id',
        'channel',
        'type',
        'is_granted',
        'status',
        'consent_date',
        'granted_at',
        'consent_source',
        'consent_ip',
        'user_agent',
        'consent_version',
        'evidence',
        'evidence_hash',
        'revoked_at',
    ];

    protected $casts = [
        'is_granted' => 'boolean',
        'consent_date' => 'datetime',
        'granted_at' => 'datetime',
        'revoked_at' => 'datetime',
        'evidence' => 'array',
    ];

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(ConsentHistory::class, 'consent_id')->orderBy('performed_at', 'desc');
    }

    /**
     * Sincroniza canais e status para compatibilidade total com fases anteriores.
     */
    protected static function booted(): void
    {
        static::creating(function (Consent $consent) {
            if (empty($consent->platform_id) && !empty($consent->player_id)) {
                $consent->platform_id = \App\Models\Player::withoutGlobalScopes()->where('id', $consent->player_id)->value('platform_id');
            }
        });

        static::saving(function (Consent $consent) {
            // Garante preenchimento de platform_id a partir do player se ausente
            if (empty($consent->platform_id) && !empty($consent->player_id)) {
                $consent->platform_id = \App\Models\Player::withoutGlobalScopes()->where('id', $consent->player_id)->value('platform_id');
            }

            // Se channel estiver preenchido mas type não, deduz o type
            if (!empty($consent->channel) && empty($consent->type)) {
                $consent->type = ConsentType::fromChannel($consent->channel)->value;
            }

            // Se type estiver preenchido mas channel não, deduz o channel
            if (!empty($consent->type) && empty($consent->channel)) {
                $consentType = ConsentType::tryFrom($consent->type);
                $consent->channel = $consentType ? $consentType->toChannel() : $consent->type;
            }

            // Sincroniza is_granted e status com inteligência de atributos dirty
            if ($consent->isDirty('is_granted')) {
                if ($consent->is_granted) {
                    $consent->status = ConsentStatus::GRANTED->value;
                    $consent->granted_at = $consent->granted_at ?? now();
                    $consent->revoked_at = null;
                } else {
                    $consent->status = ConsentStatus::REVOKED->value;
                    $consent->revoked_at = $consent->revoked_at ?? now();
                }
            } elseif ($consent->isDirty('status')) {
                if ($consent->status === ConsentStatus::GRANTED->value || $consent->status === 'GRANTED') {
                    $consent->is_granted = true;
                    $consent->granted_at = $consent->granted_at ?? now();
                    $consent->revoked_at = null;
                } else {
                    $consent->is_granted = false;
                    $consent->revoked_at = $consent->revoked_at ?? now();
                }
            } else {
                // Caso ambos ou nenhum estejam dirty (ex: model novo)
                if ($consent->status === ConsentStatus::GRANTED->value || $consent->status === 'GRANTED' || $consent->is_granted) {
                    $consent->is_granted = true;
                    $consent->status = ConsentStatus::GRANTED->value;
                    $consent->granted_at = $consent->granted_at ?? now();
                } else {
                    $consent->is_granted = false;
                    $consent->status = ConsentStatus::REVOKED->value;
                    $consent->revoked_at = $consent->revoked_at ?? now();
                }
            }
        });

        $clearCache = function (Consent $consent) {
            $channels = ['EMAIL', 'SMS', 'WHATSAPP', 'PUSH', 'MARKETING_EMAIL', 'MARKETING_SMS', 'MARKETING_WHATSAPP', 'MARKETING_PUSH'];
            if (!empty($consent->type)) {
                $channels[] = strtoupper($consent->type);
            }
            if (!empty($consent->channel)) {
                $channels[] = strtoupper($consent->channel);
            }
            $platformId = $consent->platform_id;
            $playerId = $consent->player_id;
            foreach (array_unique($channels) as $ch) {
                \Illuminate\Support\Facades\Cache::forget("betcrm:consent:{$platformId}:{$playerId}:{$ch}");
            }
        };

        static::saved($clearCache);
        static::deleted($clearCache);
    }
}
