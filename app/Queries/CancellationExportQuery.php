<?php

namespace App\Queries;

use App\DTOs\Cancellations\CancellationSearchFilters;
use App\Enums\CancellationType;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

final readonly class CancellationExportQuery
{
    public function count(CancellationSearchFilters $filters): int
    {
        $total = 0;

        if ($this->shouldIncludeMoto($filters)) {
            $total += $this->motoBranch($filters)->count();
        }

        if ($this->shouldIncludeCredit($filters)) {
            $total += $this->creditBranch($filters)->count();
        }

        return $total;
    }

    /**
     * @return LazyCollection<int, object>
     */
    public function motoRows(CancellationSearchFilters $filters): LazyCollection
    {
        if (! $this->shouldIncludeMoto($filters)) {
            return LazyCollection::make([]);
        }

        $query = $this->motoBranch($filters);
        $this->applySort($query, 'moto_cancellations', $filters->sort);

        return $query->cursor();
    }

    /**
     * @return LazyCollection<int, object>
     */
    public function creditRows(CancellationSearchFilters $filters): LazyCollection
    {
        if (! $this->shouldIncludeCredit($filters)) {
            return LazyCollection::make([]);
        }

        $query = $this->creditBranch($filters);
        $this->applySort($query, 'credit_cancellations', $filters->sort);

        return $query->cursor();
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
                'moto_cancellations.radicado',
                'moto_cancellations.status',
                'moto_cancellations.holder_name',
                'moto_cancellations.holder_cedula',
                'moto_cancellations.holder_email',
                'moto_cancellations.holder_phone',
                'moto_cancellations.property_lien_adeinco',
                'moto_cancellations.plate',
                'moto_cancellations.cancellation_reason',
                'moto_cancellations.cancellation_information_source',
                'moto_cancellations.is_credit_holder',
                'moto_cancellations.credit_owner_name',
                'moto_cancellations.credit_owner_cedula',
                'assigned_advisors.name as assigned_advisor_name',
                'moto_cancellations.created_at',
                'moto_cancellations.updated_at',
                'moto_responses.cancellation_date as response_cancellation_date',
                'moto_responses.observation as response_observation',
            ])
            ->selectRaw('? as cancellation_type', [CancellationType::MOTO->value]);

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
                'credit_cancellations.radicado',
                'credit_cancellations.status',
                'credit_cancellations.holder_name',
                'credit_cancellations.holder_cedula',
                'credit_cancellations.holder_email',
                'credit_cancellations.holder_phone',
                'credit_cancellations.credit_number',
                'credit_cancellations.cancel_personal_accidents',
                'credit_cancellations.cancel_unemployment_insurance',
                'credit_cancellations.cancellation_reason',
                'credit_cancellations.cancellation_information_source',
                'assigned_advisors.name as assigned_advisor_name',
                'credit_cancellations.created_at',
                'credit_cancellations.updated_at',
                'credit_responses.cancellation_date as response_cancellation_date',
                'credit_responses.observation as response_observation',
            ])
            ->selectRaw('? as cancellation_type', [CancellationType::CREDIT->value]);

        $this->applySharedFilters($query, 'credit_cancellations', $filters);

        if ($filters->creditNumber !== null) {
            $query->where('credit_cancellations.credit_number', $filters->creditNumber);
        }

        return $query;
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

    private function applySort(Builder $query, string $table, string $sort): void
    {
        match ($sort) {
            'created_at_asc' => $query
                ->orderBy("{$table}.created_at")
                ->orderBy("{$table}.id"),
            'radicado_asc' => $query
                ->orderBy("{$table}.radicado")
                ->orderBy("{$table}.id"),
            'radicado_desc' => $query
                ->orderByDesc("{$table}.radicado")
                ->orderByDesc("{$table}.id"),
            default => $query
                ->orderByDesc("{$table}.created_at")
                ->orderByDesc("{$table}.id"),
        };
    }
}
