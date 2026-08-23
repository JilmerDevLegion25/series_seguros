<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Panel Advisor')</title>
    <link rel="icon" href="{{ asset('assets/favicon.svg') }}" type="image/svg+xml">
    <link rel="stylesheet" href="{{ asset('assets/app.css') }}">
    <script src="{{ asset('assets/app.js') }}" defer></script>
</head>
<body>
    <a class="skip-link" href="#content">Saltar al contenido</a>
    <div class="sidebar-overlay" data-sidebar-overlay hidden></div>
    <div class="advisor-shell">
        <x-advisor.sidebar :permissions="$advisorNavigationPermissions ?? []" />
        <div class="advisor-content">
            <x-advisor.topbar :title="$pageTitle ?? trim($__env->yieldContent('title', 'Panel Advisor'))" />
            <main id="content" class="advisor-main">
                <div class="advisor-main-inner @yield('advisor_main_class')">
                    @yield('content')
                </div>
            </main>
        </div>
    </div>

    <x-ui.toast />
    <x-ui.modal />
</body>
</html>
