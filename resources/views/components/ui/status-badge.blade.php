@props(['status'])

@php
    $label = match ((string) $status) {
        \App\Enums\CancellationStatus::EN_GESTION->value => 'En gestión',
        \App\Enums\CancellationStatus::PENDIENTE_RADICACION->value => 'Pendiente de radicación',
        \App\Enums\CancellationStatus::RESPUESTA_OBTENIDA->value => 'Respuesta obtenida',
        default => str_replace('_', ' ', (string) $status),
    };
@endphp

<span {{ $attributes->merge(['class' => 'status-badge', 'data-status' => (string) $status]) }}>
    {{ $label }}
</span>
