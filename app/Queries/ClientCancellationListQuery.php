<?php

namespace App\Queries;

use App\Enums\CancellationType;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final readonly class ClientCancellationListQuery
{
    /**
     * @return LengthAwarePaginator<int, object>
     */
    public function paginateForClient(User $client, int $perPage = 15, ?string $sort = null): LengthAwarePaginator
    {
        $motoQuery = DB::table('moto_cancellations')
            ->where('owner_user_id', $client->id)
            ->select([
                'id',
                'radicado',
                'created_at',
                'status',
                'holder_name',
            ])
            ->selectRaw('? as cancellation_type', [CancellationType::MOTO->value]);

        $creditQuery = DB::table('credit_cancellations')
            ->where('owner_user_id', $client->id)
            ->select([
                'id',
                'radicado',
                'created_at',
                'status',
                'holder_name',
            ])
            ->selectRaw('? as cancellation_type', [CancellationType::CREDIT->value]);

        $query = DB::query()
            ->fromSub($motoQuery->unionAll($creditQuery), 'client_cancellations');

        $this->applySort($query, $this->normalizeSort($sort));

        return $query->paginate($perPage);
    }

    /**
     * @return array<string, string>
     */
    public static function sortOptions(): array
    {
        return [
            'created_at_desc' => 'Fecha descendente',
            'created_at_asc' => 'Fecha ascendente',
            'cancellation_type_asc' => 'Tipo ascendente',
            'cancellation_type_desc' => 'Tipo descendente',
            'radicado_asc' => 'Radicado ascendente',
            'radicado_desc' => 'Radicado descendente',
            'holder_name_asc' => 'Titular ascendente',
            'holder_name_desc' => 'Titular descendente',
            'status_asc' => 'Estado ascendente',
            'status_desc' => 'Estado descendente',
        ];
    }

    private function normalizeSort(?string $sort): string
    {
        return array_key_exists((string) $sort, self::sortOptions()) ? (string) $sort : 'created_at_desc';
    }

    private function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'created_at_asc' => $query->orderBy('created_at')->orderBy('id'),
            'cancellation_type_asc' => $query->orderBy('cancellation_type')->orderByDesc('created_at')->orderByDesc('id'),
            'cancellation_type_desc' => $query->orderByDesc('cancellation_type')->orderByDesc('created_at')->orderByDesc('id'),
            'radicado_asc' => $query->orderBy('radicado')->orderByDesc('created_at')->orderByDesc('id'),
            'radicado_desc' => $query->orderByDesc('radicado')->orderByDesc('created_at')->orderByDesc('id'),
            'holder_name_asc' => $query->orderBy('holder_name')->orderByDesc('created_at')->orderByDesc('id'),
            'holder_name_desc' => $query->orderByDesc('holder_name')->orderByDesc('created_at')->orderByDesc('id'),
            'status_asc' => $query->orderBy('status')->orderByDesc('created_at')->orderByDesc('id'),
            'status_desc' => $query->orderByDesc('status')->orderByDesc('created_at')->orderByDesc('id'),
            default => $query->orderByDesc('created_at')->orderByDesc('id'),
        };
    }
}
