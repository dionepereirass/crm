<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageLink extends Model
{
    use HasFactory;

    protected $table = 'message_links';

    protected $fillable = [
        'message_id',
        'tracking_token',
        'destination_url',
        'clicks_count',
        'first_clicked_at',
        'last_clicked_at',
    ];

    protected $casts = [
        'clicks_count' => 'integer',
        'first_clicked_at' => 'datetime',
        'last_clicked_at' => 'datetime',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    /**
     * Registra um clique no link de forma atômica.
     */
    public function recordClick(): void
    {
        $now = now();
        $this->increment('clicks_count');
        if (!$this->first_clicked_at) {
            $this->first_clicked_at = $now;
        }
        $this->last_clicked_at = $now;
        $this->save();
    }
}
