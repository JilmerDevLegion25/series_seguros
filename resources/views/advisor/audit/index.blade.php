@extends('layouts.advisor')

@section('title', 'Auditoria operacional')

@section('content')
    <x-ui.page-header title="Auditoria operacional" subtitle="Eventos sensibles y administrativos con metadata minimizada." />

    <x-ui.card class="table-panel">
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
                        <th>Target</th>
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
                                @if ($audit->moto_cancellation_id !== null)
                                    Moto
                                @elseif ($audit->credit_cancellation_id !== null)
                                    Credit
                                @else
                                    Global
                                @endif
                            </td>
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
