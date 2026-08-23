@extends('layouts.advisor')

@section('title', 'Importar respuestas')

@section('content')
    <x-ui.page-header title="Importar respuestas" subtitle="Carga un XLSX contractual para cerrar solicitudes Moto o Credit.">
        <x-slot:actions>
            <x-ui.button :href="route('advisor.responses.import.template')" variant="outline" icon="download">Descargar plantilla</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.alert type="info">
        <strong>Cabeceras esperadas por el importador.</strong>
        <ul>
            <li>Radicado: requerido, numerico y existente en el producto seleccionado.</li>
            <li>Placa, Nombre y Cedula: columnas informativas legacy; no se usan para matching.</li>
            <li>Fecha cancelacion: requerida, no futura. Formatos aceptados: YYYY-MM-DD, DD/MM/YYYY o DD-MM-YYYY.</li>
            <li>Observaciones: requerida, maximo 2000 caracteres.</li>
        </ul>
    </x-ui.alert>

    @if ($errors->any())
        <x-ui.alert type="danger">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-ui.alert>
    @endif

    <form method="post" action="{{ route('advisor.responses.import.store') }}" enctype="multipart/form-data" data-loading>
        @csrf
        <x-ui.form-section title="Archivo de respuesta" description="Selecciona el producto antes de cargar el archivo. El matching no se infiere por fila.">
            <div class="form-grid">
                <label class="field">
                    <span class="field-label">Producto</span>
                    <select class="field-control" name="type" required>
                        @foreach ($types as $value => $label)
                            <option value="{{ $value }}" @selected(old('type') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <x-ui.file-upload name="file" label="Archivo XLSX" accept=".xlsx" required help="Maximo contractual vigente para importacion." />
            </div>
        </x-ui.form-section>

        <div class="button-row">
            <x-ui.button type="submit" icon="upload">Importar</x-ui.button>
        </div>
    </form>

    @if ($batch)
        <x-ui.card>
            <h2>Resumen</h2>
            <dl class="description-list">
                <dt>Estado</dt>
                <dd><x-ui.status-badge :status="$batch->status->value" /></dd>
                <dt>Procesadas</dt>
                <dd>{{ $batch->total_rows }}</dd>
                <dt>Exitosas</dt>
                <dd>{{ $batch->successful_rows }}</dd>
                <dt>Fallidas</dt>
                <dd>{{ $batch->rejected_rows }}</dd>
                @if ($batch->failure_reason)
                    <dt>Fallo</dt>
                    <dd>{{ $batch->failure_reason }}</dd>
                @endif
            </dl>
        </x-ui.card>

        @if ($batch->rowResults->isNotEmpty())
            <x-ui.card class="table-panel">
                <h2>Detalle por fila</h2>
                <x-ui.table>
                    <thead>
                        <tr>
                            <th>Fila</th>
                            <th>Radicado</th>
                            <th>Estado</th>
                            <th>Razon</th>
                            <th>Mensaje</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($batch->rowResults as $row)
                            <tr>
                                <td>{{ $row->row_number }}</td>
                                <td>{{ $row->radicado }}</td>
                                <td>{{ $row->status->value }}</td>
                                <td>{{ $row->reason?->value }}</td>
                                <td>{{ $row->message }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.table>
            </x-ui.card>
        @endif
    @endif
@endsection
