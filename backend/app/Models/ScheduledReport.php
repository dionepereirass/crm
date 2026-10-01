<?php

namespace App\Models;

use App\Models\Traits\BelongsToPlatform;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledReport extends Model
{
    use HasFactory, BelongsToPlatform;

    protected $table = 'scheduled_reports';

    protected $fillable = [
        'platform_id',
        'name',
        'report_type',
        'frequency',
        'recipients',
        'filters',
        'format',
        'active',
        'last_run_at',
        'next_run_at',
        'created_by',
    ];

    protected $casts = [
        'recipients' => 'array',
        'filters' => 'array',
        'active' => 'boolean',
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime',
    ];

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
