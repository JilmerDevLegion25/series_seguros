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
        <x-ui.form-section title="Datos del titular" description="">
            <div class="form-grid">
                <label class="field">
                    <span class="field-label">Nombre y apellidos (Tarjeta de Propiedad)</span>
                    <input class="field-control" name="holder_name" value="{{ old('holder_name') }}" maxlength="150" required>
                </label>
                <label class="field">
                    <span class="">Cedula</span>
                    <input class="field-control" name="holder_cedula" value="{{ old('holder_cedula') }}" required>
                </label>
                <x-ui.phone-input name="holder_phone" label="Celular" :value="old('holder_phone')" required />
                <label class="field">
                    <span class="field-label">Correo electronico</span>
                    <input class="field-control" name="holder_email" type="email" value="{{ old('holder_email') }}" maxlength="255" required>
                </label>
            </div>
        </x-ui.form-section>

        <x-ui.form-section title="Informacion Moto">
            <div class="form-grid">
                <label class="field">
                    <span class="field-label">¿La motocicleta tiene limitacion a la propiedad (prenda o pignoracion), co ADMINISTRACION E INVERSIONES ADEINCO?  (Revisar parte de atras de la tarjeta de propiedad)</span>
                    <select class="field-control" name="property_lien_adeinco" required>
                        <option value="">Selecciona una Opcion</option>
                        <option value="1" @selected(old('property_lien_adeinco') === '1')>SI</option>
                        <option value="0" @selected(old('property_lien_adeinco') === '0')>NO</option>
                    </select>
                </label>
                <x-ui.plate-input name="plate" :value="old('plate')" required />
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

        <x-ui.form-section title="Informacion del credito">
            <div class="form-grid">
                <label class="field">
                    <span class="field-label">Usted es el titular del credito</span>
                    <select class="field-control" name="is_credit_holder" required>
                        <option value="">Selecciona una Opcion</option>
                        <option value="1" @selected(old('is_credit_holder') === '1')>SI</option>
                        <option value="0" @selected(old('is_credit_holder') === '0')>NO</option>
                    </select>
                </label>
                <label class="field">
                    <span class="field-label">Nombres y apellidos (dueño del credito)</span>
                    <input class="field-control" name="credit_owner_name" value="{{ old('credit_owner_name') }}" maxlength="150">
                </label>
                <label class="field">
                    <span class="field-label">Cedula (dueño del credito)</span>
                    <input class="field-control" name="credit_owner_cedula" value="{{ old('credit_owner_cedula') }}">
                </label>
            </div>
        </x-ui.form-section>

        <x-ui.form-section title="Declaraciones">
            <div class="grid">
                <x-ui.checkbox name="ownership_declaration_accepted" label="Declaro que soy el propietario de la moto, unica persona con derecho a solicitar la cancelacion de los seguros referentes" :checked="(bool) old('ownership_declaration_accepted')" required />
                <x-ui.checkbox name="data_processing_accepted" label="He leido y acepto el tratamiento de datos personales y politica de datos de la empresa Corredores de seguros del valle S.A." :checked="(bool) old('data_processing_accepted')" required />
            </div>
        </x-ui.form-section>

        <div class="button-row">
            <x-ui.button type="submit">Continuar</x-ui.button>
        </div>
    </form>
@endsection
