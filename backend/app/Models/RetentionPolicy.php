<?php

namespace App\Models;

use App\Enums\RetentionAction;
use App\Models\Traits\BelongsToPlatform;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RetentionPolicy extends Model
{
    use HasFactory, BelongsToPlatform;

    protected $table = 'retention_policies';

    protected $fillable = [
        'platform_id',
        'data_category',
        'retention_days',
        'action',
        'active',
    ];

    protected $casts = [
        'retention_days' => 'integer',
        'action' => RetentionAction::class,
        'active' => 'boolean',
    ];

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }
}
