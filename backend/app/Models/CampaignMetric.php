<?php

namespace App\Models;

use App\Models\Traits\BelongsToPlatform;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignMetric extends Model
{
    use HasFactory, BelongsToPlatform;

    protected $table = 'campaign_metrics';

    protected $fillable = [
        'campaign_id',
        'platform_id',
        'audience_count',
        'eligible_count',
        'queued_count',
        'sent_count',
        'delivered_count',
        'failed_count',
        'bounced_count',
        'opened_count',
        'unique_openers_count',
        'clicked_count',
        'unique_clickers_count',
        'unsubscribed_count',
        'delivery_rate',
        'open_rate',
        'click_rate',
        'bounce_rate',
        'failure_rate',
        'unsubscribe_rate',
    ];

    protected $casts = [
        'audience_count' => 'integer',
        'eligible_count' => 'integer',
        'queued_count' => 'integer',
        'sent_count' => 'integer',
        'delivered_count' => 'integer',
        'failed_count' => 'integer',
        'bounced_count' => 'integer',
        'opened_count' => 'integer',
        'unique_openers_count' => 'integer',
        'clicked_count' => 'integer',
        'unique_clickers_count' => 'integer',
        'unsubscribed_count' => 'integer',
        'delivery_rate' => 'float',
        'open_rate' => 'float',
        'click_rate' => 'float',
        'bounce_rate' => 'float',
        'failure_rate' => 'float',
        'unsubscribe_rate' => 'float',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }
}
