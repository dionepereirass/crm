<?php

namespace App\Models;

use App\Models\Traits\BelongsToPlatform;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class ConsentHistory extends Model
{
    use HasFactory, BelongsToPlatform;

    protected $table = 'consent_history';

    public $timestamps = false;

    protected $fillable = [
        'consent_id',
        'platform_id',
        'player_id',
        'previous_status',
        'new_status',
        'action',
        'source',
        'version',
        'ip_address',
        'user_agent',
        'evidence',
        'evidence_hash',
        'performed_at',
    ];

    protected $casts = [
        'evidence' => 'array',
        'performed_at' => 'datetime',
    ];

    public function consent(): BelongsTo
    {
        return $this->belongsTo(Consent::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    /**
     * Imutabilidade: Histórico de consentimento é estritamente append-only.
     */
    protected static function booted(): void
    {
        static::updating(function () {
            throw new RuntimeException("Violação de Imutabilidade LGPD: Registros de consent_history não podem ser atualizados.");
        });

        static::deleting(function () {
            throw new RuntimeException("Violação de Imutabilidade LGPD: Registros de consent_history não podem ser excluídos.");
        });
    }
}
