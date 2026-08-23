<?php

namespace App\Queries;

use App\Enums\PermissionKey;
use App\Models\Audit;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final readonly class OperationalAuditQuery
{
    /**
     * @return LengthAwarePaginator<int, Audit>
     *
     * @throws AuthorizationException
     */
    public function paginateForAdvisor(User $advisor, int $perPage = 15): LengthAwarePaginator
    {
        if (! $advisor->isAdvisor() || ! $advisor->can(PermissionKey::CANCELLATIONS_ACTIVITY_VIEW->value)) {
            throw new AuthorizationException;
        }

        return Audit::query()
            ->with('actor')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }
}
