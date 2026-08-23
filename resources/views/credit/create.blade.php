@extends(auth()->check() ? 'layouts.advisor' : 'layouts.guest')

@section('title', $title)

@section('content')
    <x-ui.page-header :title="$title" subtitle="Completa la informacion requerida. La radicacion se crea solo despues de validar OTP." />

    @if ($errors->any())
        <x-ui.alert type="danger">
            <strong>Revisa los campos del formulario.</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-ui.alert>
    @endif

    <form method="post" action="{{ $action }}" data-loading>
        @csrf
        <x-ui.form-section title="Datos del titular" description="Estos datos quedan como snapshot historico de la solicitud.">
            <div class="form-grid">
                <label class="field">
                    <span class="field-label">Nombres y apellidos (Titular del credito)</span>
                    <input class="field-control" name="holder_name" value="{{ old('holder_name') }}" maxlength="150" required>
                </label>
                <label class="field">
                    <span class="field-label">Cedula</span>
                    <input class="field-control" name="holder_cedula" value="{{ old('holder_cedula') }}" required>
                </label>
                <x-ui.phone-input name="holder_phone" label="Celular" :value="old('holder_phone')" required />
                <label class="field">
                    <span class="field-label">Correo electronico</span>
                    <input class="field-control" name="holder_email" type="email" value="{{ old('holder_email') }}" maxlength="255" required>
                </label>
            </div>
        </x-ui.form-section>

        <x-ui.form-section title="Informacion Credit">
            <div class="form-grid">
                <label class="field">
                    <span class="field-label">Numero de credito</span>
                    <input class="field-control" name="credit_number" value="{{ old('credit_number') }}" maxlength="50" required>
                </label>
                <label class="field">
                    <span class="field-label">Motivo de cancelacion</span>
                    <select class="field-control" name="cancellation_reason" required>
                        <option value="">Selecciona una Opcion</option>
                        @foreach ($reasons as $value => $label)
                            <option value="{{ $value }}" @selected(old('cancellation_reason') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="field">
                    <span class="field-label">¿Quien brindo la informacion de cancelacion?</span>
                    <select class="field-control" name="cancellation_information_source" required>
                        <option value="">Selecciona una Opcion</option>
                        @foreach ($sources as $value => $label)
                            <option value="{{ $value }}" @selected(old('cancellation_information_source') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
        </x-ui.form-section>

        <x-ui.form-section title="¿Que seguros desea cancelar?">
            <div class="choice-grid">
                <x-ui.checkbox name="cancel_personal_accidents" label="Accidentes Personales" :checked="(bool) old('cancel_personal_accidents')" />
                <x-ui.checkbox name="cancel_unemployment_insurance" label="Seguro de Desempleo" :checked="(bool) old('cancel_unemployment_insurance')" />
            </div>
        </x-ui.form-section>

        <x-ui.form-section title="Declaraciones">
            <div class="grid">
                <x-ui.checkbox name="credit_holder_declaration_accepted" label="Declaro que soy el titula del credito, unica persona con derecho a solicitar la cancelacion de los seguros referentes" :checked="(bool) old('credit_holder_declaration_accepted')" required />
                <x-ui.checkbox name="data_processing_accepted" label="He leido y acepto el tratamiento de datos personales y politica de datos de la empresa Corredores de seguros del valle S.A." :checked="(bool) old('data_processing_accepted')" required />
            </div>
        </x-ui.form-section>

        <div class="button-row">
            <x-ui.button type="submit">Continuar</x-ui.button>
        </div>
    </form>
@endsection
