@extends('layouts.guest')

@section('title', 'Ingresar')
@section('guest_page_class', 'guest-page-login')
@section('guest_shell_class', 'guest-shell-auth')
@section('auth_card_class', 'auth-card-portal')

@section('content')
    <div class="portal-home">
        <header class="portal-intro">
            <p class="eyebrow">Cancelacion Series</p>
            <h1>Bienvenido al portal de solicitud de seguros</h1>
            <p>Aqui podras radicar solicitudes referentes a los seguros contratados.</p>
        </header>

        <section class="portal-request-section" aria-labelledby="portal-request-title">
            <div class="portal-section-heading">
                <p class="portal-section-label">Nueva solicitud</p>
                <h2 id="portal-request-title">Elige el seguro que deseas cancelar</h2>
            </div>

            <div class="portal-request-grid">
                <a class="portal-request-option portal-request-option-moto" href="{{ route('public.moto.create') }}" data-public-cancellation-trigger>
                    <span class="portal-option-icon" aria-hidden="true"><x-ui.icon name="moto" /></span>
                    <span class="portal-option-content">
                        <span class="portal-option-title">Seguro para moto</span>
                        <span class="portal-option-copy">Inicia la cancelacion de tu poliza.</span>
                    </span>
                    <span class="portal-option-action">Iniciar <x-ui.icon name="chevron" /></span>
                </a>

                <a class="portal-request-option portal-request-option-credit" href="{{ route('public.credit.create') }}" data-public-cancellation-trigger>
                    <span class="portal-option-icon" aria-hidden="true"><x-ui.icon name="credit" /></span>
                    <span class="portal-option-content">
                        <span class="portal-option-title">Seguro de credito</span>
                        <span class="portal-option-copy">Radica una solicitud asociada a tu credito.</span>
                    </span>
                    <span class="portal-option-action">Iniciar <x-ui.icon name="chevron" /></span>
                </a>
            </div>
        </section>

        <aside class="portal-factura-note" aria-label="Informacion sobre factura electronica">
            <span class="portal-factura-icon" aria-hidden="true"><x-ui.icon name="credit" /></span>
            <p><strong>Factura electronica.</strong> Para compras de contado de polizas para moto, estara disponible 5 dias despues de la compra. Las polizas emitidas entre el 25 y el 5 del siguiente mes se veran reflejadas 10 dias habiles despues de la emision.</p>
        </aside>

        <section class="portal-access" aria-labelledby="portal-access-title">
            <div>
                <p id="portal-access-title">&iquest;Eres asesor o deseas hacer seguimiento a una solicitud?</p>
                <span>Ingresa al portal con tus credenciales.</span>
            </div>
            <button class="portal-access-trigger" type="button" data-login-panel-toggle aria-controls="portal-login-panel" aria-expanded="{{ $errors->any() ? 'true' : 'false' }}">
                <x-ui.icon name="lock" />
                Acceder
                <x-ui.icon class="portal-access-chevron" name="chevron" />
            </button>
        </section>

        <section id="portal-login-panel" class="portal-login-panel" data-login-panel @if (! $errors->any()) hidden @endif aria-label="Acceso al portal">
            <div class="portal-login-building" aria-hidden="true">
                <span class="portal-auth-frame"></span>
                <x-ui.icon class="portal-auth-shield" name="shield" />
                <span class="portal-auth-scan"></span>
                <x-ui.icon class="portal-auth-lock" name="lock" />
            </div>

            <div class="portal-login-stage">
                <div class="portal-login-surface">
                    <div class="portal-login-heading">
                        <div>
                            <p class="eyebrow">Acceso al portal</p>
                            <h2>Ingresa con tus credenciales</h2>
                        </div>
                        <button class="portal-login-close" type="button" data-login-panel-toggle aria-controls="portal-login-panel" aria-expanded="true" aria-label="Cerrar acceso">
                            <x-ui.icon name="x" />
                        </button>
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
                            <x-ui.input class="span-2" name="username" label="Usuario" :value="old('username')" autocomplete="username" required />

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
                </div>

                <aside class="portal-login-brand">
                    <img src="{{ asset('img/series-logo-sinbg.png') }}" alt="Cancelacion Series">
                    <p>Cancelacion Series</p>
                </aside>
            </div>
        </section>

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
                    <img src="{{ asset('img/series-logo-sinbg.png') }}" alt="">
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
