@php
    $isAuthenticated = auth()->check();
    $homeUrl = $isAuthenticated ? route('dashboard', absolute: false) : route('login', absolute: false);
    $homeLabel = $isAuthenticated ? 'Volver al menu' : 'Iniciar sesion';
@endphp

<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>403 | Acceso restringido</title>
    <link rel="icon" href="{{ asset('assets/favicon.svg') }}" type="image/svg+xml">
    <link rel="stylesheet" href="{{ asset('assets/app.css') }}">
</head>
<body class="forbidden-page">
    <a class="skip-link" href="#content">Saltar al contenido</a>

    <main id="content" class="forbidden-scene" aria-labelledby="forbidden-title">
        <header class="forbidden-brand" aria-label="Series Seguros">
            <span class="forbidden-logo-frame" aria-hidden="true">
                <img src="{{ asset('img/series-logo-sinbg.png') }}" alt="">
            </span>
            <span>Portal de Cancelaciones</span>
        </header>

        <section class="forbidden-copy">
            <p class="guest-kicker">Acceso restringido</p>
            <h1 id="forbidden-title">403</h1>
            <p>No tienes permiso para entrar a esta seccion. Vuelve al menu principal para continuar desde tu panel.</p>
        </section>

        <section class="forbidden-illustration" aria-hidden="true">
            <span class="forbidden-sun"></span>
            <span class="forbidden-cloud cloud-one"></span>
            <span class="forbidden-cloud cloud-two"></span>
            <span class="forbidden-gate-roof roof-top"></span>
            <span class="forbidden-gate-roof roof-mid"></span>
            <span class="forbidden-gate-body"></span>
            <span class="forbidden-gate-doors"></span>
            <span class="forbidden-stairs"></span>
        </section>

        <section class="forbidden-card" aria-label="Accion disponible">
            <h2>Ruta sin autorizacion</h2>
            <p>La sesion esta activa, pero este recurso pertenece a otro perfil o requiere permisos adicionales.</p>
            <a class="btn btn-primary forbidden-action" href="{{ $homeUrl }}">
                <x-ui.icon name="dashboard" />
                {{ $homeLabel }}
            </a>
        </section>
    </main>
</body>
</html>
