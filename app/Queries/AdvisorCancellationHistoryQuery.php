<?php

namespace App\Queries;

use App\Enums\PermissionKey;
use App\Models\Activity;
use App\Models\Audit;
use App\Models\CreditCancellation;
use App\Models\MotoCancellation;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final readonly class AdvisorCancellationHistoryQuery
{
    /**
     * @return LengthAwarePaginator<int, Activity>
     *
     * @throws AuthorizationException
     */
    public function motoActivities(User $advisor, MotoCancellation $moto, int $perPage = 15): LengthAwarePaginator
    {
        $this->assertAdvisorCanViewHistory($advisor);

        return Activity::query()
            ->with('actor')
            ->where('moto_cancellation_id', $moto->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'activity_page');
    }

    /**
     * @return LengthAwarePaginator<int, Audit>
     *
     * @throws AuthorizationException
     */
    public function motoAudits(User $advisor, MotoCancellation $moto, int $perPage = 15): LengthAwarePaginator
    {
        $this->assertAdvisorCanViewHistory($advisor);

        return Audit::query()
            ->with('actor')
            ->where('moto_cancellation_id', $moto->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'audit_page');
    }

    /**
     * @return LengthAwarePaginator<int, Activity>
     *
     * @throws AuthorizationException
     */
    public function creditActivities(User $advisor, CreditCancellation $credit, int $perPage = 15): LengthAwarePaginator
    {
        $this->assertAdvisorCanViewHistory($advisor);

        return Activity::query()
            ->with('actor')
            ->where('credit_cancellation_id', $credit->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'activity_page');
    }

    /**
     * @return LengthAwarePaginator<int, Audit>
     *
     * @throws AuthorizationException
     */
    public function creditAudits(User $advisor, CreditCancellation $credit, int $perPage = 15): LengthAwarePaginator
    {
        $this->assertAdvisorCanViewHistory($advisor);

        return Audit::query()
            ->with('actor')
            ->where('credit_cancellation_id', $credit->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'audit_page');
    }

    /**
     * @throws AuthorizationException
     */
    private function assertAdvisorCanViewHistory(User $advisor): void
    {
        if (! $advisor->isAdvisor() || ! $advisor->can(PermissionKey::CANCELLATIONS_ACTIVITY_VIEW->value)) {
            throw new AuthorizationException;
        }
    }
}
