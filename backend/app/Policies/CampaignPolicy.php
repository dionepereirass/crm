<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\User;

class CampaignPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission('campaigns.view');
    }

    public function view(User $user, Campaign $campaign): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasPermission('campaigns.view') && $user->hasAccessToPlatform($campaign->platform_id);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission('campaigns.create');
    }

    public function update(User $user, Campaign $campaign): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasPermission('campaigns.update') && $user->hasAccessToPlatform($campaign->platform_id);
    }

    public function delete(User $user, Campaign $campaign): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasPermission('campaigns.delete') && $user->hasAccessToPlatform($campaign->platform_id);
    }

    public function validate(User $user, Campaign $campaign): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $canValidate = $user->hasPermission('campaigns.validate') || $user->hasPermission('campaigns.view');
        return $canValidate && $user->hasAccessToPlatform($campaign->platform_id);
    }

    public function preview(User $user, Campaign $campaign): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $canPreview = $user->hasPermission('campaigns.preview') || $user->hasPermission('campaigns.view');
        return $canPreview && $user->hasAccessToPlatform($campaign->platform_id);
    }

    public function test(User $user, Campaign $campaign): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $canTest = $user->hasPermission('campaigns.test') || $user->hasPermission('campaigns.send');
        return $canTest && $user->hasAccessToPlatform($campaign->platform_id);
    }

    public function launch(User $user, Campaign $campaign): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $canLaunch = $user->hasPermission('campaigns.launch') || $user->hasPermission('campaigns.send');
        return $canLaunch && $user->hasAccessToPlatform($campaign->platform_id);
    }

    public function pause(User $user, Campaign $campaign): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $canPause = $user->hasPermission('campaigns.pause') || $user->hasPermission('campaigns.send');
        return $canPause && $user->hasAccessToPlatform($campaign->platform_id);
    }

    public function resume(User $user, Campaign $campaign): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $canResume = $user->hasPermission('campaigns.resume') || $user->hasPermission('campaigns.send');
        return $canResume && $user->hasAccessToPlatform($campaign->platform_id);
    }

    public function cancel(User $user, Campaign $campaign): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $canCancel = $user->hasPermission('campaigns.cancel') || $user->hasPermission('campaigns.delete');
        return $canCancel && $user->hasAccessToPlatform($campaign->platform_id);
    }

    public function recipients(User $user, Campaign $campaign): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $canView = $user->hasPermission('campaigns.recipients') || $user->hasPermission('campaigns.view');
        return $canView && $user->hasAccessToPlatform($campaign->platform_id);
    }

    public function messages(User $user, Campaign $campaign): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $canView = $user->hasPermission('campaigns.messages') || $user->hasPermission('campaigns.view');
        return $canView && $user->hasAccessToPlatform($campaign->platform_id);
    }

    public function stats(User $user, Campaign $campaign): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $canView = $user->hasPermission('campaigns.stats') || $user->hasPermission('campaigns.view') || $user->hasPermission('analytics.view');
        return $canView && $user->hasAccessToPlatform($campaign->platform_id);
    }

    public function analytics(User $user, Campaign $campaign): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $canView = $user->hasPermission('analytics.view') || $user->hasPermission('campaigns.stats') || $user->hasPermission('campaigns.view');
        return $canView && $user->hasAccessToPlatform($campaign->platform_id);
    }

    public function events(User $user, Campaign $campaign): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $canView = $user->hasPermission('analytics.view') || $user->hasPermission('tracking.view') || $user->hasPermission('campaigns.messages');
        return $canView && $user->hasAccessToPlatform($campaign->platform_id);
    }
}
