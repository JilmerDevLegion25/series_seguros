<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Portal Client')</title>
    <link rel="icon" href="{{ asset('assets/favicon.svg') }}" type="image/svg+xml">
    <link rel="stylesheet" href="{{ asset('assets/app.css') }}">
    <script src="{{ asset('assets/app.js') }}" defer></script>
</head>
<body>
    <a class="skip-link" href="#content">Saltar al contenido</a>
    @php
        $topbarUnreadCount = isset($unreadCount) ? (int) $unreadCount : 0;
    @endphp
    <div class="client-shell">
        <header class="client-topbar">
            <div class="client-topbar-inner">
                <a class="client-brand" href="{{ route('dashboard') }}">
                    <span class="brand-logo-frame" aria-hidden="true">
                        <img class="brand-logo" src="{{ asset('img/series-logo-sinbg.png') }}" alt="">
                    </span>
                    <span>Portal Cliente</span>
                </a>
                <nav class="client-nav" aria-label="Navegacion principal">
                    <a class="btn btn-ghost" href="{{ route('dashboard') }}">Panel</a>
                    <a class="btn btn-ghost" href="{{ route('client.cancellations.index') }}">Solicitudes</a>
                    <a
                        class="btn btn-ghost client-notification-button @if ($topbarUnreadCount > 0) has-unread @endif"
                        href="{{ route('client.notifications.index') }}"
                        title="Notificaciones"
                        aria-label="{{ $topbarUnreadCount > 0 ? 'Notificaciones, '.$topbarUnreadCount.' sin leer' : 'Notificaciones' }}"
                    >
                        <x-ui.icon name="bell" />
                        <span class="sr-only">Notificaciones</span>
                        @if ($topbarUnreadCount > 0)
                            <span class="client-notification-badge">{{ $topbarUnreadCount }}</span>
                        @endif
                    </a>
                    <form method="post" action="{{ route('logout') }}">
                        @csrf
                        <button class="btn btn-outline" type="submit">Salir</button>
                    </form>
                </nav>
            </div>
        </header>

        <main id="content" class="client-main">
            <div class="grid">
                @yield('content')
            </div>
        </main>
    </div>

    <x-ui.toast />
    <x-ui.modal />
</body>
</html>
