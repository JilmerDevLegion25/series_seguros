@extends('layouts.guest')

@section('title', 'Ingresar')
@section('guest_page_class', 'guest-page-login')
@section('guest_shell_class', 'guest-shell-auth')

@section('content')
    <div class="grid">
        <div>
            <p class="eyebrow">Acceso seguro</p>
            <h1>Ingresar</h1>
            <p class="muted">Usa tu usuario Advisor o tu cedula si eres Client.</p>
        </div>

        @if ($errors->any())
            <x-ui.alert type="danger">
                <strong>No pudimos iniciar sesion.</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-ui.alert>
        @endif

        <form method="post" action="{{ route('login.store') }}" data-loading>
            @csrf
            <div class="form-grid">
                <x-ui.input class="span-2" name="username" label="Usuario" :value="old('username')" autocomplete="username" required autofocus />

                <label class="field span-2">
                    <span class="field-label">Contrasena</span>
                    <input id="login-password" class="field-control" name="password" type="password" autocomplete="current-password" required>
                </label>
            </div>

            <div class="auth-actions">
                <x-ui.button type="submit" icon="lock">Ingresar</x-ui.button>
                <x-ui.button variant="ghost" :href="route('password.recovery')">Recuperar contrasena</x-ui.button>
            </div>
        </form>

        <div class="auth-public-section" aria-label="Solicitudes publicas">
            <p class="muted">Si deseas solicitar cancelacion de polizas, por favor selecciona el tipo de seguros a revocar.</p>
            <div class="auth-public-actions">
                <x-ui.button variant="outline" icon="moto" :href="route('public.moto.create')" data-public-cancellation-trigger>Seguro de Moto</x-ui.button>
                <x-ui.button variant="outline" icon="credit" :href="route('public.credit.create')" data-public-cancellation-trigger>Seguro del Credito</x-ui.button>
            </div>
        </div>

        <div class="modal-backdrop public-cancellation-backdrop" data-public-cancellation-backdrop hidden></div>
        <section class="public-cancellation-modal" data-public-cancellation-modal hidden role="dialog" aria-modal="true" aria-labelledby="public-cancellation-title">
            <header class="public-cancellation-header">
                <div>
                    <p class="eyebrow">Solicitudes Seguros</p>
                    <h2 id="public-cancellation-title">Solicitud de cancelacion</h2>
                </div>
            </header>

            <div class="public-cancellation-body">
                <div class="public-cancellation-brand" aria-hidden="true">
                    <span>?</span>
                    <img src="{{ asset('img/progreser-icon.png') }}" alt="">
                </div>

                <div class="public-cancellation-copy">
                    <p class="public-cancellation-question"><strong>&iquest;Confirma que es usted el propietario del veh&iacute;culo y &uacute;nica persona con derecho a solicitar la cancelaci&oacute;n de la p&oacute;liza de motocicleta, que ampara, la motocicleta en Da&ntilde;os, Hurto y Responsabilidad civil extracontractual?</strong></p>
                    <p><strong>ARTICULO 296. FALSEDAD PERSONAL.</strong> El que con el fin de obtener un provecho para s&iacute; o para otro, o causar da&ntilde;o, sustituya o suplante a una persona o se atribuya nombre, edad, estado civil, o calidad que pueda tener efectos jur&iacute;dicos, incurrir&aacute; en multa, siempre que la conducta no constituya otro delito.</p>
                    <p>Recuerde que al enviar la solicitud de cancelaci&oacute;n no se podr&aacute; reversar en el sistema la cancelaci&oacute;n y perder&aacute; los beneficios y la tasa de la renovaci&oacute;n autom&aacute;tica de su seguro, si tiene una inquietud antes de darle enviar por favor contactarnos la numero en Cali 602- 6606446.</p>
                    <p>Colombia Art. 1045 Elementos esenciales</p>
                </div>
            </div>

            <footer class="public-cancellation-actions">
                <button class="btn btn-primary" type="button" data-public-cancellation-accept>SI</button>
                <button class="btn btn-outline" type="button" data-public-cancellation-cancel>NO</button>
                <p class="muted">Si su respuesta es NO, no puede continuar.</p>
            </footer>
        </section>
    </div>
@endsection
