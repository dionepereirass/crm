<?php

namespace App\Models;

use App\Models\Traits\BelongsToPlatform;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CampaignRecipient extends Model
{
    use HasFactory, BelongsToPlatform;

    protected $table = 'campaign_recipients';

    protected $fillable = [
        'uuid',
        'campaign_id',
        'platform_id',
        'player_id',
        'channel',
        'recipient',
        'status',
        'message_id',
        'reason',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->status)) {
                $model->status = 'PENDING';
            }
        });
    }

    // Relationships
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    /**
     * LGPD Masking do destinatário (e-mail ou telefone).
     */
    public function getMaskedRecipientAttribute(): string
    {
        $val = $this->recipient ?? '';

        if (str_contains($val, '@')) {
            $parts = explode('@', $val);
            $user = $parts[0];
            $domain = $parts[1] ?? '';
            $maskedUser = mb_substr($user, 0, min(2, mb_strlen($user))) . '***';
            return $maskedUser . '@' . $domain;
        }

        // Telefone / celular
        $clean = preg_replace('/\D/', '', $val);
        $len = strlen($clean);
        if ($len > 8) {
            $start = substr($clean, 0, 4);
            $end = substr($clean, -4);
            return $start . '******' . $end;
        }

        return '******';
    }
}
