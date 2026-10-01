<?php

namespace App\Models\Traits;

use App\Models\Platform;
use App\Models\Scopes\PlatformScope;
use App\Services\Platforms\PlatformContext;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToPlatform
{
    /**
     * Boot the trait and apply the multi-platform global scope.
     */
    protected static function bootBelongsToPlatform(): void
    {
        static::addGlobalScope(new PlatformScope());

        static::creating(function ($model) {
            if (empty($model->platform_id)) {
                $context = app(PlatformContext::class);
                if ($context->hasPlatform()) {
                    $model->platform_id = $context->getPlatformId();
                }
            }
        });
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }
}
