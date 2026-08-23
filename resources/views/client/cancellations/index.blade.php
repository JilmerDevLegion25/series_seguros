@extends('client.layout')

@section('title', 'Mis solicitudes')

@section('content')
    @php
        $currentSort = (string) request('sort', 'created_at_desc');
        $sortBaseQuery = collect(request()->except(['page', 'sort']))
            ->reject(fn (mixed $value): bool => $value === null || $value === '')
            ->all();
        $sortColumns = [
            'type' => ['asc' => 'cancellation_type_asc', 'desc' => 'cancellation_type_desc'],
            'radicado' => ['asc' => 'radicado_asc', 'desc' => 'radicado_desc'],
            'holder' => ['asc' => 'holder_name_asc', 'desc' => 'holder_name_desc'],
            'created' => ['asc' => 'created_at_asc', 'desc' => 'created_at_desc'],
            'status' => ['asc' => 'status_asc', 'desc' => 'status_desc'],
        ];
        $sortUrl = static function (string $column) use ($sortBaseQuery, $sortColumns, $currentSort): string {
            $options = $sortColumns[$column];
            $nextSort = $currentSort === $options['asc'] ? $options['desc'] : $options['asc'];

            return route('client.cancellations.index', array_merge($sortBaseQuery, ['sort' => $nextSort]));
        };
        $sortClass = static function (string $column) use ($sortColumns, $currentSort): string {
            $options = $sortColumns[$column];

            return in_array($currentSort, $options, true)
                ? 'table-sort is-active '.($currentSort === $options['asc'] ? 'is-asc' : 'is-desc')
                : 'table-sort';
        };
    @endphp

    <x-ui.page-header title="Mis solicitudes" :subtitle="$cancellations->total().' registradas'" />

    <x-ui.card class="table-panel workspace-table client-cancellations-table">
        @if ($cancellations->isEmpty())
            <x-ui.empty-state title="No hay solicitudes registradas." />
        @else
            <x-ui.table>
                <thead>
                    <tr>
                        <th><a class="{{ $sortClass('type') }}" href="{{ $sortUrl('type') }}" aria-label="Ordenar por tipo">Tipo<span class="sort-indicator" aria-hidden="true"></span></a></th>
                        <th><a class="{{ $sortClass('radicado') }}" href="{{ $sortUrl('radicado') }}" aria-label="Ordenar por radicado">Radicado<span class="sort-indicator" aria-hidden="true"></span></a></th>
                        <th><a class="{{ $sortClass('holder') }}" href="{{ $sortUrl('holder') }}" aria-label="Ordenar por titular">Titular<span class="sort-indicator" aria-hidden="true"></span></a></th>
                        <th><a class="{{ $sortClass('created') }}" href="{{ $sortUrl('created') }}" aria-label="Ordenar por fecha">Fecha<span class="sort-indicator" aria-hidden="true"></span></a></th>
                        <th><a class="{{ $sortClass('status') }}" href="{{ $sortUrl('status') }}" aria-label="Ordenar por estado">Estado<span class="sort-indicator" aria-hidden="true"></span></a></th>
                        <th><a class="{{ $sortClass('status') }}" href="{{ $sortUrl('status') }}" aria-label="Ordenar por progreso">Progreso<span class="sort-indicator" aria-hidden="true"></span></a></th>
                        <th>Detalle</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($cancellations as $cancellation)
                        @php
                            $isMoto = $cancellation->cancellation_type === \App\Enums\CancellationType::MOTO->value;
                            $detailRoute = $isMoto
                                ? route('client.cancellations.moto.show', $cancellation->id)
                                : route('client.cancellations.credit.show', $cancellation->id);
                            $isComplete = $cancellation->status === \App\Enums\CancellationStatus::RESPUESTA_OBTENIDA->value;
                            $progressPercent = $isComplete ? 100 : 50;
                        @endphp
                        <tr>
                            <td>
                                <span class="product-pill {{ $isMoto ? 'is-moto' : 'is-credit' }}">
                                    <x-ui.icon :name="$isMoto ? 'moto' : 'credit'" />
                                    {{ $isMoto ? 'Moto' : 'Credit' }}
                                </span>
                            </td>
                            <td><strong class="workspace-radicado">{{ $cancellation->radicado }}</strong></td>
                            <td>
                                <span class="workspace-person">
                                    <strong>{{ $cancellation->holder_name }}</strong>
                                    <span>Solicitud propia</span>
                                </span>
                            </td>
                            <td>{{ $cancellation->created_at ? \Carbon\CarbonImmutable::parse($cancellation->created_at)->format('Y-m-d') : '' }}</td>
                            <td><x-ui.status-badge :status="$cancellation->status" /></td>
                            <td>
                                <span class="client-progress-ring" aria-label="Progreso {{ $progressPercent }}%">
                                    <svg class="client-progress-ring-svg" viewBox="0 0 42 42" aria-hidden="true" focusable="false">
                                        <circle class="client-progress-ring-bg" cx="21" cy="21" r="17" pathLength="100" />
                                        <circle class="client-progress-ring-value" cx="21" cy="21" r="17" pathLength="100" stroke-dasharray="{{ $progressPercent }} 100" />
                                    </svg>
                                    <span>{{ $progressPercent }}%</span>
                                </span>
                            </td>
                            <td><a class="btn btn-outline btn-sm" href="{{ $detailRoute }}">Ver</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>

            <div class="mobile-card-list" aria-label="Solicitudes en vista movil">
                @foreach ($cancellations as $cancellation)
                    @php
                        $isMoto = $cancellation->cancellation_type === \App\Enums\CancellationType::MOTO->value;
                        $detailRoute = $isMoto
                            ? route('client.cancellations.moto.show', $cancellation->id)
                            : route('client.cancellations.credit.show', $cancellation->id);
                        $isComplete = $cancellation->status === \App\Enums\CancellationStatus::RESPUESTA_OBTENIDA->value;
                        $progressPercent = $isComplete ? 100 : 50;
                    @endphp
                    <article class="mobile-row-card">
                        <strong>{{ $isMoto ? 'Moto' : 'Credit' }} {{ $cancellation->radicado }}</strong>
                        <span>{{ $cancellation->holder_name }}</span>
                        <x-ui.status-badge :status="$cancellation->status" />
                        <span class="client-progress-ring" aria-label="Progreso {{ $progressPercent }}%">
                            <svg class="client-progress-ring-svg" viewBox="0 0 42 42" aria-hidden="true" focusable="false">
                                <circle class="client-progress-ring-bg" cx="21" cy="21" r="17" pathLength="100" />
                                <circle class="client-progress-ring-value" cx="21" cy="21" r="17" pathLength="100" stroke-dasharray="{{ $progressPercent }} 100" />
                            </svg>
                            <span>{{ $progressPercent }}%</span>
                        </span>
                        <a class="btn btn-outline" href="{{ $detailRoute }}">Ver</a>
                    </article>
                @endforeach
            </div>

            <x-ui.pagination :paginator="$cancellations" />
        @endif
    </x-ui.card>
@endsection
