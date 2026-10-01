<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SegmentGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'segment_id',
        'parent_id',
        'logical_operator',
        'position',
    ];

    public function segment(): BelongsTo
    {
        return $this->belongsTo(Segment::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(SegmentGroup::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(SegmentGroup::class, 'parent_id');
    }

    public function conditions(): HasMany
    {
        return $this->hasMany(SegmentCondition::class, 'group_id');
    }
}
