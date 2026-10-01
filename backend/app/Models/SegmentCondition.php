<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SegmentCondition extends Model
{
    use HasFactory;

    protected $fillable = [
        'segment_id',
        'group_id',
        'field',
        'operator',
        'value',
        'value_type',
        'event_type',
        'period_value',
        'period_unit',
        'position',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'period_value' => 'integer',
        'position' => 'integer',
    ];

    public function segment(): BelongsTo
    {
        return $this->belongsTo(Segment::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(SegmentGroup::class, 'group_id');
    }
}
