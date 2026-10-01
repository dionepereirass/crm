<?php

namespace App\Policies;

use App\Models\Message;
use App\Models\User;

class MessagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission('messages.view');
    }

    public function view(User $user, Message $message): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasPermission('messages.view') && $user->hasAccessToPlatform($message->platform_id);
    }

    public function test(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission('messages.test');
    }

    public function retry(User $user, Message $message): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasPermission('messages.retry') && $user->hasAccessToPlatform($message->platform_id);
    }

    public function cancel(User $user, Message $message): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasPermission('messages.cancel') && $user->hasAccessToPlatform($message->platform_id);
    }
}
