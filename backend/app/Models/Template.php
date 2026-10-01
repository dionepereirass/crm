<?php

namespace App\Models;

use App\Models\Traits\BelongsToPlatform;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Template extends Model
{
    use HasFactory, BelongsToPlatform, SoftDeletes;

    protected $fillable = [
        'uuid',
        'platform_id',
        'name',
        'slug',
        'description',
        'channel',
        'status',
        'category',
        'current_version_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'current_version_id' => 'integer',
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

    public function versions(): HasMany
    {
        return $this->hasMany(TemplateVersion::class)->orderBy('version', 'desc');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(TemplateVersion::class, 'current_version_id');
    }

    public function publishedVersion(): ?TemplateVersion
    {
        return $this->versions()->where('status', 'PUBLISHED')->latest('version')->first()
            ?? $this->currentVersion;
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function isEmail(): bool
    {
        return strtoupper($this->channel) === 'EMAIL';
    }

    public function isSms(): bool
    {
        return strtoupper($this->channel) === 'SMS';
    }

    public function isActive(): bool
    {
        return $this->status === 'ACTIVE';
    }

    public function isDraft(): bool
    {
        return $this->status === 'DRAFT';
    }

    public function isArchived(): bool
    {
        return $this->status === 'ARCHIVED';
    }
}
