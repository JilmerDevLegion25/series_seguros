@extends('layouts.advisor')

@section('title', 'Exportar cancelaciones')

@section('content')
    @php
        $selectedType = old('type', request('type', \App\Enums\CancellationType::MOTO->value));

        if (! in_array($selectedType, [\App\Enums\CancellationType::MOTO->value, \App\Enums\CancellationType::CREDIT->value], true)) {
            $selectedType = \App\Enums\CancellationType::MOTO->value;
        }

        $selectedTypeLabel = $selectedType === \App\Enums\CancellationType::MOTO->value ? 'Moto' : 'Credito';
    @endphp

    <x-ui.page-header title="Exportar cancelaciones" subtitle="Descarga un XLSX por tipo de seguro filtrado por fecha de creacion.">
        <x-slot:actions>
            <x-ui.button :href="route('advisor.cancellations.index')" variant="outline" icon="list">Volver al workspace</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($errors->any())
        <x-ui.alert type="danger">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-ui.alert>
    @endif

    <form method="post" action="{{ route('advisor.cancellations.export') }}" data-loading data-download data-loading-reset-ms="3500">
        @csrf
        <input name="type" type="hidden" value="{{ $selectedType }}" data-export-type-input>

        <section class="form-section export-download-panel" data-export-type-panel>
            <div class="export-panel-overlay" data-export-panel-overlay hidden>
                <div class="export-panel-loader" role="status" aria-live="polite">
                    <span class="export-build-animation" aria-hidden="true">
                        <span></span>
                        <span></span>
                        <span></span>
                    </span>
                    <span>Cambiando tipo reporte</span>
                </div>
            </div>

            <div class="export-type-tabs" role="tablist" aria-label="Tipo de seguro para exportar">
                <button
                    class="export-type-tab @if ($selectedType === \App\Enums\CancellationType::MOTO->value) is-active @endif"
                    type="button"
                    role="tab"
                    aria-selected="{{ $selectedType === \App\Enums\CancellationType::MOTO->value ? 'true' : 'false' }}"
                    data-export-type-tab
                    data-export-type="{{ \App\Enums\CancellationType::MOTO->value }}"
                    data-export-type-label="Moto"
                    data-export-type-icon="moto"
                >
                    <x-ui.icon name="moto" />
                    <span>Moto</span>
                </button>
                <button
                    class="export-type-tab @if ($selectedType === \App\Enums\CancellationType::CREDIT->value) is-active @endif"
                    type="button"
                    role="tab"
                    aria-selected="{{ $selectedType === \App\Enums\CancellationType::CREDIT->value ? 'true' : 'false' }}"
                    data-export-type-tab
                    data-export-type="{{ \App\Enums\CancellationType::CREDIT->value }}"
                    data-export-type-label="Credito"
                    data-export-type-icon="credit"
                >
                    <x-ui.icon name="credit" />
                    <span>Credito</span>
                </button>
            </div>

            <header class="form-section-header export-panel-header">
                <div>
                    <h2>Reporte <span data-export-current-label>{{ $selectedTypeLabel }}</span></h2>
                    <p class="form-section-description">El archivo se genera con una sola hoja para el tipo de seguro seleccionado.</p>
                </div>
                <span class="dashboard-card-icon export-panel-icon" aria-hidden="true">
                    <span data-export-current-icon="{{ \App\Enums\CancellationType::MOTO->value }}" @if ($selectedType !== \App\Enums\CancellationType::MOTO->value) hidden @endif>
                        <x-ui.icon name="moto" />
                    </span>
                    <span data-export-current-icon="{{ \App\Enums\CancellationType::CREDIT->value }}" @if ($selectedType !== \App\Enums\CancellationType::CREDIT->value) hidden @endif>
                        <x-ui.icon name="credit" />
                    </span>
                </span>
            </header>

            <div class="export-panel-body">
                <div class="form-grid">
                    <label class="field">
                        <span class="field-label">Fecha inicio</span>
                        <input class="field-control" name="created_from" type="date" value="{{ old('created_from', request('created_from')) }}" required>
                    </label>
                    <label class="field">
                        <span class="field-label">Fecha fin</span>
                        <input class="field-control" name="created_to" type="date" value="{{ old('created_to', request('created_to')) }}" required>
                    </label>
                </div>

                <x-ui.alert type="info">
                    <strong>El rango se aplica sobre la fecha de creacion de la cancelacion.</strong>
                    <ul>
                        <li>La fecha inicio y fin son inclusivas.</li>
                        <li>Si la fecha fin es anterior a la fecha inicio, la descarga se rechaza.</li>
                    </ul>
                </x-ui.alert>

                <div class="button-row">
                    <x-ui.button type="submit" icon="download">Descargar XLSX</x-ui.button>
                    <x-ui.button :href="route('advisor.cancellations.index')" variant="outline">Cancelar</x-ui.button>
                </div>
            </div>
        </section>

        <x-ui.alert type="info">
            <strong>El archivo conserva el contrato de exportacion.</strong>
            <ul>
                <li>Genera una sola hoja para el tipo de seguro seleccionado.</li>
                <!-- <li>Respeta permisos y genera Audit, sin Activity.</li> -->
                <li>El boton descarga solo el reporte del tab activo.</li>
            </ul>
        </x-ui.alert>
    </form>
@endsection
