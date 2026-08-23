@extends('client.layout')

@section('title', 'Mis notificaciones')

@section('content')
    <x-ui.page-header title="Mis notificaciones" :subtitle="$unreadCount.' sin leer'" />

    <x-ui.card>
        @if ($notifications->isEmpty())
            <x-ui.empty-state title="No hay notificaciones." />
        @else
            <x-ui.table>
                <thead>
                    <tr>
                        <th>Estado</th>
                        <th>Tipo</th>
                        <th>Radicado</th>
                        <th>Fecha</th>
                        <th>Detalle</th>
                        <th>Lectura</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($notifications as $notification)
                        @php
                            $isMoto = $notification->moto_cancellation_id !== null;
                            $cancellation = $isMoto ? $notification->motoCancellation : $notification->creditCancellation;
                            $detailRoute = $isMoto && $cancellation
                                ? route('client.cancellations.moto.show', $cancellation)
                                : ($cancellation ? route('client.cancellations.credit.show', $cancellation) : null);
                            $readLabel = $notification->read_at ? 'Leida' : 'No leida';
                        @endphp
                        <tr>
                            <td><x-ui.status-badge :status="$readLabel" /></td>
                            <td>Respuesta obtenida</td>
                            <td>{{ $cancellation?->radicado }}</td>
                            <td>{{ $notification->created_at?->format('Y-m-d H:i') }}</td>
                            <td>
                                @if ($detailRoute)
                                    <a href="{{ $detailRoute }}">{{ $isMoto ? 'Moto' : 'Credit' }}</a>
                                @endif
                            </td>
                            <td>
                                @if ($notification->read_at === null)
                                    <form method="post" action="{{ route('client.notifications.read', $notification) }}" data-loading>
                                        @csrf
                                        <button class="btn btn-primary" type="submit">Marcar como leida</button>
                                    </form>
                                @else
                                    <span class="muted">Visto</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>

            <x-ui.pagination :paginator="$notifications" />
        @endif
    </x-ui.card>
@endsection
