<?php

namespace App\Queries;

use App\DTOs\Cancellations\CancellationSearchFilters;
use App\Enums\CancellationType;
use App\Enums\PermissionKey;
use App\Enums\SmsPurpose;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final readonly class AdvisorCancellationSearchQuery
{
    /**
     * @return LengthAwarePaginator<int, object>
     *
     * @throws AuthorizationException
     */
    public function paginateForAdvisor(User $advisor, CancellationSearchFilters $filters, int $perPage = 15): LengthAwarePaginator
    {
        if (! $advisor->isAdvisor() || ! $advisor->can(PermissionKey::CANCELLATIONS_VIEW->value)) {
            throw new AuthorizationException;
        }

        $branches = [];

        if ($this->shouldIncludeMoto($filters)) {
            $branches[] = $this->motoBranch($filters);
        }

        if ($this->shouldIncludeCredit($filters)) {
            $branches[] = $this->creditBranch($filters);
        }

        /** @var Builder $union */
        $union = array_shift($branches) ?? $this->emptyBranch();

        foreach ($branches as $branch) {
            $union->unionAll($branch);
        }

        $query = DB::query()->fromSub($union, 'advisor_cancellations');
        $this->applySort($query, $filters->sort);

        return $query->paginate($perPage);
    }

    private function shouldIncludeMoto(CancellationSearchFilters $filters): bool
    {
        if ($filters->type === CancellationType::CREDIT) {
            return false;
        }

        if ($filters->creditNumber !== null) {
            return false;
        }

        return true;
    }

    private function shouldIncludeCredit(CancellationSearchFilters $filters): bool
    {
        if ($filters->type === CancellationType::MOTO) {
            return false;
        }

        if ($filters->plate !== null) {
            return false;
        }

        return true;
    }

    private function motoBranch(CancellationSearchFilters $filters): Builder
    {
        $query = DB::table('moto_cancellations')
            ->leftJoin('users as assigned_advisors', 'assigned_advisors.id', '=', 'moto_cancellations.assigned_advisor_user_id')
            ->leftJoin('responses as moto_responses', 'moto_responses.moto_cancellation_id', '=', 'moto_cancellations.id')
            ->select([
                'moto_cancellations.id',
                'moto_cancellations.radicado',
                'moto_cancellations.holder_name',
                'moto_cancellations.holder_cedula',
                'moto_cancellations.holder_email',
                'moto_cancellations.holder_phone',
                'moto_cancellations.plate',
                'moto_cancellations.property_lien_adeinco',
                DB::raw('NULL as credit_number'),
                'moto_cancellations.status',
                'moto_cancellations.assigned_advisor_user_id',
                'assigned_advisors.name as assigned_advisor_name',
                'moto_cancellations.created_at',
                'moto_cancellations.updated_at',
            ])
            ->selectRaw('? as cancellation_type', [CancellationType::MOTO->value])
            ->selectRaw('CASE WHEN moto_responses.id IS NULL THEN 0 ELSE 1 END as has_response')
            ->selectRaw(
                '(SELECT sms_attempts.status
                    FROM sms_attempts
                    WHERE sms_attempts.moto_cancellation_id = moto_cancellations.id
                        AND sms_attempts.purpose = ?
                    ORDER BY sms_attempts.created_at DESC, sms_attempts.id DESC
                    LIMIT 1) as latest_radicado_sms_status',
                [SmsPurpose::RADICADO->value],
            );

        $this->applySharedFilters($query, 'moto_cancellations', $filters);

        if ($filters->plate !== null) {
            $query->where('moto_cancellations.plate', $filters->plate);
        }

        return $query;
    }

    private function creditBranch(CancellationSearchFilters $filters): Builder
    {
        $query = DB::table('credit_cancellations')
            ->leftJoin('users as assigned_advisors', 'assigned_advisors.id', '=', 'credit_cancellations.assigned_advisor_user_id')
            ->leftJoin('responses as credit_responses', 'credit_responses.credit_cancellation_id', '=', 'credit_cancellations.id')
            ->select([
                'credit_cancellations.id',
                'credit_cancellations.radicado',
                'credit_cancellations.holder_name',
                'credit_cancellations.holder_cedula',
                'credit_cancellations.holder_email',
                'credit_cancellations.holder_phone',
                DB::raw('NULL as plate'),
                DB::raw('0 as property_lien_adeinco'),
                'credit_cancellations.credit_number',
                'credit_cancellations.status',
                'credit_cancellations.assigned_advisor_user_id',
                'assigned_advisors.name as assigned_advisor_name',
                'credit_cancellations.created_at',
                'credit_cancellations.updated_at',
            ])
            ->selectRaw('? as cancellation_type', [CancellationType::CREDIT->value])
            ->selectRaw('CASE WHEN credit_responses.id IS NULL THEN 0 ELSE 1 END as has_response')
            ->selectRaw(
                '(SELECT sms_attempts.status
                    FROM sms_attempts
                    WHERE sms_attempts.credit_cancellation_id = credit_cancellations.id
                        AND sms_attempts.purpose = ?
                    ORDER BY sms_attempts.created_at DESC, sms_attempts.id DESC
                    LIMIT 1) as latest_radicado_sms_status',
                [SmsPurpose::RADICADO->value],
            );

        $this->applySharedFilters($query, 'credit_cancellations', $filters);

        if ($filters->creditNumber !== null) {
            $query->where('credit_cancellations.credit_number', $filters->creditNumber);
        }

        return $query;
    }

    private function emptyBranch(): Builder
    {
        return DB::query()
            ->selectRaw('NULL as id')
            ->selectRaw('NULL as radicado')
            ->selectRaw('NULL as holder_name')
            ->selectRaw('NULL as holder_cedula')
            ->selectRaw('NULL as holder_email')
            ->selectRaw('NULL as holder_phone')
            ->selectRaw('NULL as plate')
            ->selectRaw('NULL as property_lien_adeinco')
            ->selectRaw('NULL as credit_number')
            ->selectRaw('NULL as status')
            ->selectRaw('NULL as assigned_advisor_user_id')
            ->selectRaw('NULL as assigned_advisor_name')
            ->selectRaw('NULL as created_at')
            ->selectRaw('NULL as updated_at')
            ->selectRaw('NULL as cancellation_type')
            ->selectRaw('NULL as has_response')
            ->selectRaw('NULL as latest_radicado_sms_status')
            ->whereRaw('1 = 0');
    }

    private function applySharedFilters(Builder $query, string $table, CancellationSearchFilters $filters): void
    {
        if ($filters->status !== null) {
            $query->where("{$table}.status", $filters->status->value);
        }

        if ($filters->radicado !== null) {
            $query->where("{$table}.radicado", $filters->radicado);
        }

        if ($filters->holderName !== null) {
            $query->where("{$table}.holder_name", 'like', '%'.$filters->holderName.'%');
        }

        if ($filters->holderCedula !== null) {
            $query->where("{$table}.holder_cedula", $filters->holderCedula);
        }

        if ($filters->holderEmail !== null) {
            $query->where("{$table}.holder_email", $filters->holderEmail);
        }

        if ($filters->holderPhone !== null) {
            $query->where("{$table}.holder_phone", $filters->holderPhone);
        }

        if ($filters->assignedAdvisorUserId !== null) {
            $query->where("{$table}.assigned_advisor_user_id", $filters->assignedAdvisorUserId);
        }

        if ($filters->createdFrom !== null) {
            $query->where("{$table}.created_at", '>=', $filters->createdFrom->toDateTimeString());
        }

        if ($filters->createdTo !== null) {
            $query->where("{$table}.created_at", '<=', $filters->createdTo->toDateTimeString());
        }
    }

    private function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'created_at_asc' => $query
                ->orderBy('created_at')
                ->orderBy('cancellation_type')
                ->orderBy('id'),
            'cancellation_type_asc' => $query
                ->orderBy('cancellation_type')
                ->orderByDesc('created_at')
                ->orderByDesc('id'),
            'cancellation_type_desc' => $query
                ->orderByDesc('cancellation_type')
                ->orderByDesc('created_at')
                ->orderByDesc('id'),
            'radicado_asc' => $query
                ->orderBy('radicado')
                ->orderBy('cancellation_type')
                ->orderBy('id'),
            'radicado_desc' => $query
                ->orderByDesc('radicado')
                ->orderBy('cancellation_type')
                ->orderByDesc('id'),
            'holder_name_asc' => $query
                ->orderBy('holder_name')
                ->orderByDesc('created_at')
                ->orderByDesc('id'),
            'holder_name_desc' => $query
                ->orderByDesc('holder_name')
                ->orderByDesc('created_at')
                ->orderByDesc('id'),
            'holder_email_asc' => $query
                ->orderBy('holder_email')
                ->orderBy('holder_phone')
                ->orderByDesc('created_at')
                ->orderByDesc('id'),
            'holder_email_desc' => $query
                ->orderByDesc('holder_email')
                ->orderByDesc('holder_phone')
                ->orderByDesc('created_at')
                ->orderByDesc('id'),
            'plate_asc' => $query
                ->orderBy('plate')
                ->orderByDesc('created_at')
                ->orderByDesc('id'),
            'plate_desc' => $query
                ->orderByDesc('plate')
                ->orderByDesc('created_at')
                ->orderByDesc('id'),
            'credit_number_asc' => $query
                ->orderBy('credit_number')
                ->orderByDesc('created_at')
                ->orderByDesc('id'),
            'credit_number_desc' => $query
                ->orderByDesc('credit_number')
                ->orderByDesc('created_at')
                ->orderByDesc('id'),
            'status_asc' => $query
                ->orderBy('status')
                ->orderByDesc('created_at')
                ->orderByDesc('id'),
            'status_desc' => $query
                ->orderByDesc('status')
                ->orderByDesc('created_at')
                ->orderByDesc('id'),
            default => $query
                ->orderByDesc('created_at')
                ->orderBy('cancellation_type')
                ->orderByDesc('id'),
        };
    }
}
