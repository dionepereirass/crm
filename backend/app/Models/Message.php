<?php

namespace App\Models;

use App\Models\Traits\BelongsToPlatform;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Message extends Model
{
    use HasFactory, BelongsToPlatform;

    protected $fillable = [
        'uuid',
        'platform_id',
        'campaign_id',
        'player_id',
        'provider_id',
        'channel',
        'recipient',
        'recipient_name',
        'subject',
        'template_id',
        'template_version_id',
        'status',
        'provider_message_id',
        'idempotency_key',
        'open_tracking_token',
        'unsubscribe_token',
        'scheduled_at',
        'sent_at',
        'failed_at',
        'opened_at',
        'clicked_at',
        'error_code',
        'error_message',
        'metadata',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
        'opened_at' => 'datetime',
        'clicked_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected $appends = [
        'masked_recipient',
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

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(TemplateVersion::class);
    }

    public function links(): HasMany
    {
        return $this->hasMany(MessageLink::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(MessageEvent::class)->orderBy('created_at', 'asc');
    }

    /**
     * Retorna o destinatário mascarado para conformidade com a LGPD.
     * Ex: jo***@gmail.com ou 5511******9999
     */
    public function getMaskedRecipientAttribute(): string
    {
        $recipient = $this->recipient ?? '';

        if (filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $parts = explode('@', $recipient);
            $name = $parts[0];
            $domain = $parts[1] ?? '';
            $len = mb_strlen($name);
            if ($len <= 2) {
                return $name . '***@' . $domain;
            }
            return mb_substr($name, 0, 2) . str_repeat('*', min(4, $len - 2)) . '@' . $domain;
        }

        // Telefone
        $len = mb_strlen($recipient);
        if ($len >= 8) {
            $prefix = mb_substr($recipient, 0, 4);
            $suffix = mb_substr($recipient, -4);
            return $prefix . str_repeat('*', max(2, $len - 8)) . $suffix;
        }

        return $recipient;
    }

    public function isSent(): bool
    {
        return $this->status === 'SENT';
    }

    public function isFailed(): bool
    {
        return $this->status === 'FAILED';
    }

    public function isPending(): bool
    {
        return in_array($this->status, ['PENDING', 'QUEUED', 'SENDING'], true);
    }
}
