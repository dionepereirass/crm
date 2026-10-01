<?php

namespace App\Services\Platforms;

use App\Models\Platform;

class PlatformContext
{
    protected ?Platform $currentPlatform = null;

    /**
     * Set the current active platform for the request lifecycle.
     */
    public function setPlatform(?Platform $platform): void
    {
        $this->currentPlatform = $platform;
    }

    /**
     * Get the currently active platform.
     */
    public function getPlatform(): ?Platform
    {
        return $this->currentPlatform;
    }

    /**
     * Get the active platform ID or null.
     */
    public function getPlatformId(): ?int
    {
        return $this->currentPlatform?->id;
    }

    /**
     * Check if a platform context is currently set.
     */
    public function hasPlatform(): bool
    {
        return $this->currentPlatform !== null;
    }
}
