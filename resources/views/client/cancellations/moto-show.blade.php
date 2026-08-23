@extends('client.layout')

@section('title', 'Moto '.$moto->radicado)

@section('content')
    @php
        $isComplete = $moto->response !== null;
        $progressPercent = $isComplete ? 100 : 50;
        $statusLabel = $isComplete ? 'Respuesta obtenida' : 'En gestion';
        $responseIcon = $isComplete ? 'shield' : 'refresh';
        $steps = [
            ['label' => 'Radicada', 'description' => 'Solicitud recibida', 'complete' => true, 'active' => false],
            ['label' => 'En gestion', 'description' => 'Validacion operativa', 'complete' => true, 'active' => ! $isComplete],
            ['label' => 'Respuesta', 'description' => 'Resultado final', 'complete' => $isComplete, 'active' => $isComplete],
        ];
    @endphp

    <section class="client-detail-shell moto-detail-shell">
        <section class="client-detail-hero moto-detail-hero" aria-labelledby="moto-detail-title">
            <span class="detail-bubble bubble-one" aria-hidden="true"></span>
            <span class="detail-bubble bubble-two" aria-hidden="true"></span>
            <span class="detail-bubble bubble-three" aria-hidden="true"></span>

            <div class="client-detail-hero-copy">
                <p class="guest-kicker">Seguro de Moto</p>
                <h1 id="moto-detail-title">Solicitud {{ $moto->radicado }}</h1>
                <p>Consulta el avance de tu cancelacion y conserva el radicado para seguimiento.</p>

                <div class="client-detail-hero-meta">
                    <span class="client-detail-chip">
                        <x-ui.icon name="moto" />
                        Placa {{ $moto->plate }}
                    </span>
                    <x-ui.status-badge :status="$moto->status->value" />
                </div>
            </div>

            <div class="client-detail-progress-card">
                <span class="client-progress-ring client-detail-progress-ring" aria-label="Progreso {{ $progressPercent }}%">
                    <svg class="client-progress-ring-svg" viewBox="0 0 42 42" aria-hidden="true" focusable="false">
                        <circle class="client-progress-ring-bg" cx="21" cy="21" r="17" pathLength="100" />
                        <circle class="client-progress-ring-value" cx="21" cy="21" r="17" pathLength="100" stroke-dasharray="{{ $progressPercent }} 100" />
                    </svg>
                    <span>{{ $progressPercent }}%</span>
                </span>
                <div>
                    <strong>{{ $statusLabel }}</strong>
                    <span>Estado actual</span>
                </div>
            </div>

            <x-ui.button class="client-detail-back" variant="outline" :href="route('client.cancellations.index')" icon="list">Volver</x-ui.button>
        </section>

        <section class="client-detail-stepper" aria-label="Progreso de solicitud">
            @foreach ($steps as $step)
                <article class="client-detail-step @if ($step['complete']) is-complete @endif @if ($step['active']) is-active @endif">
                    <span class="client-detail-step-dot" aria-hidden="true"></span>
                    <div>
                        <strong>{{ $step['label'] }}</strong>
                        <span>{{ $step['description'] }}</span>
                    </div>
                </article>
            @endforeach
        </section>

        <section class="client-detail-grid">
            <x-ui.card class="client-detail-card">
                <header class="client-detail-card-header">
                    <span class="client-detail-card-icon"><x-ui.icon name="user" /></span>
                    <div>
                        <h2>Datos del titular</h2>
                        <p>Informacion registrada al momento de radicar.</p>
                    </div>
                </header>

                <dl class="client-detail-list">
                    <dt>Titular</dt>
                    <dd>{{ $moto->holder_name }}</dd>
                    <dt>Cedula</dt>
                    <dd>{{ $moto->holder_cedula }}</dd>
                    <dt>Celular</dt>
                    <dd>{{ $moto->holder_phone }}</dd>
                    <dt>Correo</dt>
                    <dd>{{ $moto->holder_email }}</dd>
                </dl>
            </x-ui.card>

            <x-ui.card class="client-detail-card">
                <header class="client-detail-card-header">
                    <span class="client-detail-card-icon"><x-ui.icon name="moto" /></span>
                    <div>
                        <h2>Datos de la moto</h2>
                        <p>Detalle operacional de la solicitud.</p>
                    </div>
                </header>

                <dl class="client-detail-list">
                    <dt>Placa</dt>
                    <dd><strong>{{ $moto->plate }}</strong></dd>
                    <dt>Limitacion ADEINCO</dt>
                    <dd>{{ $moto->property_lien_adeinco ? 'Si' : 'No' }}</dd>
                    <dt>Motivo</dt>
                    <dd>{{ $reasons[$moto->cancellation_reason->value] ?? $moto->cancellation_reason->value }}</dd>
                    <dt>Fuente</dt>
                    <dd>{{ $sources[$moto->cancellation_information_source->value] ?? $moto->cancellation_information_source->value }}</dd>
                    <dt>Titular del credito</dt>
                    <dd>{{ $moto->is_credit_holder ? 'Si' : 'No' }}</dd>
                    @if (! $moto->is_credit_holder)
                        <dt>Dueño del credito</dt>
                        <dd>{{ $moto->credit_owner_name }}</dd>
                        <dt>Cedula dueño credito</dt>
                        <dd>{{ $moto->credit_owner_cedula }}</dd>
                    @endif
                </dl>
            </x-ui.card>
        </section>

        <x-ui.card class="client-detail-card client-response-card {{ $isComplete ? 'is-complete' : 'is-pending' }}">
            <header class="client-detail-card-header">
                <span class="client-detail-card-icon"><x-ui.icon :name="$responseIcon" /></span>
                <div>
                    <h2>Respuesta final</h2>
                    <p>{{ $isComplete ? 'La respuesta ya fue registrada para esta solicitud.' : 'Tu solicitud sigue en revision operativa.' }}</p>
                </div>
            </header>

            @if ($moto->response)
                <dl class="client-detail-list client-response-list">
                    <dt>Fecha cancelacion</dt>
                    <dd>{{ $moto->response->cancellation_date->format('Y-m-d') }}</dd>
                    <dt>Observacion</dt>
                    <dd>{{ $moto->response->observation }}</dd>
                </dl>
            @else
                <div class="client-detail-pending">
                    <span class="client-detail-pending-loader" aria-hidden="true"></span>
                    <div>
                        <strong>En gestion</strong>
                        <p>Cuando la respuesta este disponible, aparecera en esta seccion.</p>
                    </div>
                </div>
            @endif
        </x-ui.card>
    </section>
@endsection
