@extends('layouts.advisor')

@section('title', 'Editar Moto')

@section('content')
    <x-ui.page-header :title="'Editar Moto '.$moto->radicado" :subtitle="'Version '.$moto->version">
        <x-slot:actions>
            <!-- @can('cancellations.activity.view')
                <x-ui.button variant="outline" :href="route('advisor.moto.activity', $moto)" icon="audit">Historial operativo</x-ui.button>
            @endcan -->
            <!-- @can('cancellations.reassign')
                <x-ui.button variant="outline" :href="route('advisor.moto.reassign', $moto)" icon="users">Reasignar Advisor</x-ui.button>
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

    <form method="post" action="{{ route('advisor.moto.update', $moto) }}" data-loading>
        @csrf
        @method('PATCH')
        <input name="expected_version" type="hidden" value="{{ old('expected_version', $moto->version) }}">

        <x-ui.form-section title="Cambio operativo" description="">
            <x-ui.textarea name="reason" label="Razon del cambio" required>{{ old('reason') }}</x-ui.textarea>
        </x-ui.form-section>

        <x-ui.form-section title="Datos editables">
            <div class="form-grid">
                <label class="field">
                    <span class="field-label">Nombre y apellidos (Tarjeta de Propiedad)</span>
                    <input class="field-control" name="holder_name" value="{{ old('holder_name', $moto->holder_name) }}" maxlength="150" required>
                </label>
                <x-ui.phone-input name="holder_phone" label="Celular" :value="old('holder_phone', $moto->holder_phone)" required />
                <label class="field">
                    <span class="field-label">Correo electronico</span>
                    <input class="field-control" name="holder_email" type="email" value="{{ old('holder_email', $moto->holder_email) }}" maxlength="255" required>
                </label>
                <x-ui.plate-input name="plate" :value="old('plate', $moto->plate)" required />
                <label class="field">
                    <span class="field-label">Motivo de cancelacion</span>
                    <select class="field-control" name="cancellation_reason" required>
                        @foreach ($reasons as $value => $label)
                            <option value="{{ $value }}" @selected(old('cancellation_reason', $moto->cancellation_reason->value) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="field">
                    <span class="field-label">Quien brindo la informacion de cancelacion</span>
                    <select class="field-control" name="cancellation_information_source" required>
                        @foreach ($sources as $value => $label)
                            <option value="{{ $value }}" @selected(old('cancellation_information_source', $moto->cancellation_information_source->value) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="checkbox-field">
                    <input name="property_lien_adeinco" type="hidden" value="0">
                    <input name="property_lien_adeinco" type="checkbox" value="1" @checked((string) old('property_lien_adeinco', $moto->property_lien_adeinco ? '1' : '0') === '1')>
                    <span class="field-label">Declaro que soy el propietario de la moto, unica persona con derecho a solicitar la cancelacion de los seguros referentes</span>
                </label>
                <label class="checkbox-field">
                    <input name="is_credit_holder" type="hidden" value="0">
                    <input name="is_credit_holder" type="checkbox" value="1" @checked((string) old('is_credit_holder', $moto->is_credit_holder ? '1' : '0') === '1')>
                    <span class="field-label">Usted es el titular del credito</span>
                </label>
                <label class="field">
                    <span class="field-label">Nombres y apellidos del dueño del credito</span>
                    <input class="field-control" name="credit_owner_name" value="{{ old('credit_owner_name', $moto->credit_owner_name) }}" maxlength="150">
                </label>
                <label class="field">
                    <span class="field-label">Cedula del dueño del credito</span>
                    <input class="field-control" name="credit_owner_cedula" value="{{ old('credit_owner_cedula', $moto->credit_owner_cedula) }}">
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
            <form method="post" action="{{ route('advisor.moto.sms.retry', $moto) }}" data-loading data-confirm="Se registrara un nuevo intento SMS para este radicado.">
                @csrf
                <x-ui.button variant="outline" type="submit">Reintentar SMS radicado</x-ui.button>
            </form>
        </x-ui.card>
    @endcan -->
@endsection
