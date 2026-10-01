<?php

namespace App\Models;

use App\Enums\DataSubjectRequestStatus;
use App\Enums\DataSubjectRequestType;
use App\Models\Traits\BelongsToPlatform;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataSubjectRequest extends Model
{
    use HasFactory, BelongsToPlatform;

    protected $table = 'data_subject_requests';

    protected $fillable = [
        'platform_id',
        'player_id',
        'type',
        'status',
        'requested_at',
        'due_at',
        'completed_at',
        'requested_by',
        'assigned_to',
        'reason',
        'resolution',
        'metadata',
    ];

    protected $casts = [
        'type' => DataSubjectRequestType::class,
        'status' => DataSubjectRequestStatus::class,
        'requested_at' => 'datetime',
        'due_at' => 'datetime',
        'completed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    /**
     * Define SLA padrão de 15 dias corridos (Art. 19 da LGPD).
     */
    protected static function booted(): void
    {
        static::creating(function (DataSubjectRequest $request) {
            if (!$request->requested_at) {
                $request->requested_at = now();
            }
            if (!$request->due_at) {
                $request->due_at = now()->addDays(15);
            }
        });
    }

    public function isExpired(): bool
    {
        if ($this->status === DataSubjectRequestStatus::COMPLETED || $this->status === DataSubjectRequestStatus::CANCELLED) {
            return false;
        }
        return $this->due_at && $this->due_at->isPast();
    }

    public function isNearSla(): bool
    {
        if ($this->status === DataSubjectRequestStatus::COMPLETED || $this->status === DataSubjectRequestStatus::CANCELLED) {
            return false;
        }
        return $this->due_at && $this->due_at->isFuture() && $this->due_at->diffInDays(now()) <= 3;
    }
}
