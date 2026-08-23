@extends('client.layout')

@section('title', 'Credit '.$credit->radicado)

@section('content')
    @php
        $isComplete = $credit->response !== null;
        $progressPercent = $isComplete ? 100 : 50;
        $statusLabel = $isComplete ? 'Respuesta obtenida' : 'En gestion';
        $responseIcon = $isComplete ? 'shield' : 'refresh';
        $steps = [
            ['label' => 'Radicada', 'description' => 'Solicitud recibida', 'complete' => true, 'active' => false],
            ['label' => 'En gestion', 'description' => 'Validacion operativa', 'complete' => true, 'active' => ! $isComplete],
            ['label' => 'Respuesta', 'description' => 'Resultado final', 'complete' => $isComplete, 'active' => $isComplete],
        ];
    @endphp

    <section class="client-detail-shell credit-detail-shell">
        <section class="client-detail-hero credit-detail-hero" aria-labelledby="credit-detail-title">
            <span class="detail-bubble bubble-one" aria-hidden="true"></span>
            <span class="detail-bubble bubble-two" aria-hidden="true"></span>
            <span class="detail-bubble bubble-three" aria-hidden="true"></span>

            <div class="client-detail-hero-copy">
                <p class="guest-kicker">Seguro del Credito</p>
                <h1 id="credit-detail-title">Solicitud {{ $credit->radicado }}</h1>
                <p>Consulta el avance de tu cancelacion y conserva el radicado para seguimiento.</p>

                <div class="client-detail-hero-meta">
                    <span class="client-detail-chip credit-detail-chip">
                        <x-ui.icon name="credit" />
                        Credito {{ $credit->credit_number }}
                    </span>
                    <x-ui.status-badge :status="$credit->status->value" />
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
                    <dd>{{ $credit->holder_name }}</dd>
                    <dt>Cedula</dt>
                    <dd>{{ $credit->holder_cedula }}</dd>
                    <dt>Celular</dt>
                    <dd>{{ $credit->holder_phone }}</dd>
                    <dt>Correo</dt>
                    <dd>{{ $credit->holder_email }}</dd>
                </dl>
            </x-ui.card>

            <x-ui.card class="client-detail-card credit-detail-card">
                <header class="client-detail-card-header">
                    <span class="client-detail-card-icon"><x-ui.icon name="credit" /></span>
                    <div>
                        <h2>Datos del credito</h2>
                        <p>Detalle operacional de la solicitud.</p>
                    </div>
                </header>

                <dl class="client-detail-list">
                    <dt>Numero de credito</dt>
                    <dd><strong>{{ $credit->credit_number }}</strong></dd>
                    <dt>Accidentes Personales</dt>
                    <dd>{{ $credit->cancel_personal_accidents ? 'Si' : 'No' }}</dd>
                    <dt>Seguro de Desempleo</dt>
                    <dd>{{ $credit->cancel_unemployment_insurance ? 'Si' : 'No' }}</dd>
                    <dt>Motivo</dt>
                    <dd>{{ $reasons[$credit->cancellation_reason->value] ?? $credit->cancellation_reason->value }}</dd>
                    <dt>Fuente</dt>
                    <dd>{{ $sources[$credit->cancellation_information_source->value] ?? $credit->cancellation_information_source->value }}</dd>
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

            @if ($credit->response)
                <dl class="client-detail-list client-response-list">
                    <dt>Fecha cancelacion</dt>
                    <dd>{{ $credit->response->cancellation_date->format('Y-m-d') }}</dd>
                    <dt>Observacion</dt>
                    <dd>{{ $credit->response->observation }}</dd>
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
