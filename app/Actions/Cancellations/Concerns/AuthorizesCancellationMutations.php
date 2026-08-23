<?php

namespace App\Actions\Cancellations\Concerns;

use App\Enums\PermissionKey;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

trait AuthorizesCancellationMutations
{
    /**
     * @throws AuthorizationException
     */
    private function assertAdvisorCan(User $actor, PermissionKey $permissionKey): void
    {
        if (! $actor->isAdvisor() || ! $actor->can($permissionKey->value)) {
            throw new AuthorizationException;
        }
    }
}
