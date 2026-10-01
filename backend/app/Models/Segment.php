<?php

namespace App\Models;

use App\Models\Traits\BelongsToPlatform;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Segment extends Model
{
    use HasFactory, BelongsToPlatform, SoftDeletes;

    protected $fillable = [
        'uuid',
        'platform_id',
        'name',
        'slug',
        'description',
        'status',
        'rules_tree',
        'cached_count',
        'cached_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'rules_tree' => 'array',
        'cached_count' => 'integer',
        'cached_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->slug)) {
                $model->slug = Str::slug($model->name) . '-' . Str::random(6);
            }
        });
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    public function groups(): HasMany
    {
        return $this->hasMany(SegmentGroup::class);
    }

    public function conditions(): HasMany
    {
        return $this->hasMany(SegmentCondition::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function isActive(): bool
    {
        return $this->status === 'ACTIVE';
    }
}
