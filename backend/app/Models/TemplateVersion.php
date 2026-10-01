<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TemplateVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'template_id',
        'version',
        'status',
        'subject',
        'preheader',
        'html_content',
        'text_content',
        'sms_content',
        'variables_schema',
        'metadata',
        'created_by',
    ];

    protected $casts = [
        'version' => 'integer',
        'variables_schema' => 'array',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isPublished(): bool
    {
        return $this->status === 'PUBLISHED';
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
