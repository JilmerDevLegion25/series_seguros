@extends('layouts.advisor')

@section('title', 'Editar Credit')

@section('content')
    <x-ui.page-header :title="'Editar Credit '.$credit->radicado" :subtitle="'Version '.$credit->version">
        <x-slot:actions>
            <!-- @can('cancellations.activity.view')
                <x-ui.button variant="outline" :href="route('advisor.credit.activity', $credit)" icon="audit">Historial operativo</x-ui.button>
            @endcan -->
            <!-- @can('cancellations.reassign')
                <x-ui.button variant="outline" :href="route('advisor.credit.reassign', $credit)" icon="users">Reasignar Advisor</x-ui.button>
            @endcan -->
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

    <form method="post" action="{{ route('advisor.credit.update', $credit) }}" data-loading>
        @csrf
        @method('PATCH')
        <input name="expected_version" type="hidden" value="{{ old('expected_version', $credit->version) }}">

        <x-ui.form-section title="Cambio operativo" description="">
            <x-ui.textarea name="reason" label="Razon del cambio" required>{{ old('reason') }}</x-ui.textarea>
        </x-ui.form-section>

        <x-ui.form-section title="Datos editables">
            <div class="form-grid">
                <label class="field">
                    <span class="field-label">Nombres y apellidos titular</span>
                    <input class="field-control" name="holder_name" value="{{ old('holder_name', $credit->holder_name) }}" maxlength="150" required>
                </label>
                <x-ui.phone-input name="holder_phone" label="Celular" :value="old('holder_phone', $credit->holder_phone)" required />
                <label class="field">
                    <span class="field-label">Correo electronico</span>
                    <input class="field-control" name="holder_email" type="email" value="{{ old('holder_email', $credit->holder_email) }}" maxlength="255" required>
                </label>
                <label class="field">
                    <span class="field-label">Numero de credito</span>
                    <input class="field-control" name="credit_number" value="{{ old('credit_number', $credit->credit_number) }}" maxlength="50" required>
                </label>
                <label class="field">
                    <span class="field-label">Motivo de cancelacion</span>
                    <select class="field-control" name="cancellation_reason" required>
                        @foreach ($reasons as $value => $label)
                            <option value="{{ $value }}" @selected(old('cancellation_reason', $credit->cancellation_reason->value) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="field">
                    <span class="field-label">Quien brindo la informacion de cancelacion</span>
                    <select class="field-control" name="cancellation_information_source" required>
                        @foreach ($sources as $value => $label)
                            <option value="{{ $value }}" @selected(old('cancellation_information_source', $credit->cancellation_information_source->value) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="checkbox-field">
                    <input name="cancel_personal_accidents" type="hidden" value="0">
                    <input name="cancel_personal_accidents" type="checkbox" value="1" @checked((string) old('cancel_personal_accidents', $credit->cancel_personal_accidents ? '1' : '0') === '1')>
                    <span class="field-label">Accidentes Personales</span>
                </label>
                <label class="checkbox-field">
                    <input name="cancel_unemployment_insurance" type="hidden" value="0">
                    <input name="cancel_unemployment_insurance" type="checkbox" value="1" @checked((string) old('cancel_unemployment_insurance', $credit->cancel_unemployment_insurance ? '1' : '0') === '1')>
                    <span class="field-label">Seguro de Desempleo</span>
                </label>
            </div>
        </x-ui.form-section>

        <div class="button-row">
            <x-ui.button type="submit" icon="edit">Guardar</x-ui.button>
        </div>
    </form>

    <!-- @can('radicado_sms.retry')
        <x-ui.card>
            <h2>SMS de radicado</h2>
            <p class="muted">El reintento usa el celular vigente de la solicitud.</p>
            <form method="post" action="{{ route('advisor.credit.sms.retry', $credit) }}" data-loading data-confirm="Se registrara un nuevo intento SMS para este radicado.">
                @csrf
                <x-ui.button variant="outline" type="submit">Reintentar SMS radicado</x-ui.button>
            </form>
        </x-ui.card>
    @endcan -->
@endsection
