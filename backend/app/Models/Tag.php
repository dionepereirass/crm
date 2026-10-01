<?php

namespace App\Models;

use App\Models\Traits\BelongsToPlatform;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    use HasFactory, BelongsToPlatform;

    protected $table = 'tags';

    protected $fillable = [
        'platform_id',
        'name',
        'color',
        'description',
    ];

    public function players(): BelongsToMany
    {
        return $this->belongsToMany(Player::class, 'player_tags')
            ->withPivot('created_at');
    }
}
