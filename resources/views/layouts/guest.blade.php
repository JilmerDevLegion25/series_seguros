<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name'))</title>
    <link rel="icon" href="{{ asset('assets/favicon.svg') }}" type="image/svg+xml">
    <link rel="stylesheet" href="{{ asset('assets/app.css') }}">
    <script src="{{ asset('assets/app.js') }}" defer></script>
</head>
<body class="guest-page @yield('guest_page_class')">
    <a class="skip-link" href="#content">Saltar al contenido</a>
    <main id="content" class="guest-shell @yield('guest_shell_class')">
        <section class="guest-brand" aria-label="Portal de cancelaciones">
            <span class="brand-logo-frame" aria-hidden="true">
                <img class="brand-logo" src="{{ asset('img/series-logo-sinbg.png') }}" alt="">
            </span>
            <div>
                <p class="guest-kicker">Cancelacion Series</p>
                <p class="guest-title">Portal de solicitudes</p>
                <p class="guest-copy">Gestiona tus solicitudes de cancelacion de seguros de forma simple y segura.</p>
            </div>
        </section>

        <section class="auth-card @yield('auth_card_class')">
            @yield('content')
        </section>
    </main>

    <x-ui.toast />
    <x-ui.modal />
</body>
</html>
