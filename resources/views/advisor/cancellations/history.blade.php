@extends('layouts.advisor')

@section('title', 'Historial '.$product.' '.$radicado)

@section('content')
    <x-ui.page-header :title="'Historial '.$product.' '.$radicado">
        <x-slot:actions>
            <x-ui.button variant="outline" :href="$backRoute">Volver</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card class="table-panel">
        <h2>Actividad funcional</h2>
        @if ($activities->isEmpty())
            <x-ui.empty-state title="No hay actividad funcional registrada." />
        @else
            <x-ui.table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Evento</th>
                        <th>Actor</th>
                        <th>Detalle</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($activities as $activity)
                        <tr>
                            <td>{{ $activity->created_at?->format('Y-m-d H:i:s') }}</td>
                            <td>{{ $formatter->activityLabel($activity->type) }}</td>
                            <td>{{ $activity->actor?->name ?? 'Sistema' }}</td>
                            <td>
                                <ul class="metadata">
                                    @foreach ($formatter->format($activity->metadata) as $item)
                                        <li>{{ $item['label'] }}: {{ $item['value'] }}</li>
                                    @endforeach
                                </ul>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
            <div class="pager">{{ $activities->links() }}</div>
        @endif
    </x-ui.card>

    <x-ui.card class="table-panel">
        <h2>Auditoria operacional del caso</h2>
        @if ($audits->isEmpty())
            <x-ui.empty-state title="No hay auditoria operacional registrada." />
        @else
            <x-ui.table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Evento</th>
                        <th>Actor</th>
                        <th>Request ID</th>
                        <th>Detalle</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($audits as $audit)
                        <tr>
                            <td>{{ $audit->created_at?->format('Y-m-d H:i:s') }}</td>
                            <td>{{ $formatter->auditLabel($audit->event_type) }}</td>
                            <td>{{ $audit->actor?->name ?? 'Sistema' }}</td>
                            <td>{{ $audit->request_id }}</td>
                            <td>
                                <ul class="metadata">
                                    @foreach ($formatter->format($audit->metadata) as $item)
                                        <li>{{ $item['label'] }}: {{ $item['value'] }}</li>
                                    @endforeach
                                </ul>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
            <div class="pager">{{ $audits->links() }}</div>
        @endif
    </x-ui.card>
@endsection
