@props(['permissions' => []])

@php
    $canNavigate = static fn (\App\Enums\PermissionKey $permission): bool => isset($permissions[$permission->value]);
@endphp

<aside class="advisor-sidebar" aria-label="Navegacion Advisor">
    <div class="sidebar-header">
        <span class="brand-logo-frame sidebar-brand-logo-frame" aria-hidden="true">
            <img class="brand-logo" src="{{ asset('img/series-logo-sinbg.png') }}" alt="">
        </span>
        <div class="sidebar-brand">
            <strong>Cancelaciones</strong>
            <span>Series Seguros</span>
        </div>
    </div>

    <div class="sidebar-section">
        <div class="sidebar-section-title">General</div>
        <nav class="sidebar-nav" aria-label="General">
            <x-advisor.sidebar-item :href="route('dashboard')" icon="dashboard" :active="request()->routeIs('dashboard')">
                Dashboard
            </x-advisor.sidebar-item>
            @if ($canNavigate(\App\Enums\PermissionKey::CANCELLATIONS_VIEW))
                <x-advisor.sidebar-item :href="route('advisor.cancellations.index')" icon="list" :active="request()->routeIs('advisor.cancellations.index')">
                    Solicitudes
                </x-advisor.sidebar-item>
            @endif
        </nav>
    </div>

    <div class="sidebar-section">
        <div class="sidebar-section-title">Operacion</div>
        <nav class="sidebar-nav" aria-label="Operacion">
            @if ($canNavigate(\App\Enums\PermissionKey::CANCELLATIONS_CREATE))
                <x-advisor.sidebar-item :href="route('advisor.moto.create')" icon="moto" :active="request()->routeIs('advisor.moto.create')">
                    Formulario Cancelación Seguros Motos
                </x-advisor.sidebar-item>
                <x-advisor.sidebar-item :href="route('advisor.credit.create')" icon="credit" :active="request()->routeIs('advisor.credit.create')">
                    Formulario Cancelación Seguros del Crédito
                </x-advisor.sidebar-item>
            @endif
            @if ($canNavigate(\App\Enums\PermissionKey::RESPONSES_IMPORT))
                <x-advisor.sidebar-item :href="route('advisor.responses.import.create')" icon="upload" :active="request()->routeIs('advisor.responses.import.*')">
                    Importar respuestas
                </x-advisor.sidebar-item>
            @endif
        </nav>
    </div>

    <div class="sidebar-section">
        <div class="sidebar-section-title">Reportes</div>
        <nav class="sidebar-nav" aria-label="Reportes">
            @if ($canNavigate(\App\Enums\PermissionKey::CANCELLATIONS_EXPORT))
                <x-advisor.sidebar-item :href="route('advisor.cancellations.export.create')" icon="download" :active="request()->routeIs('advisor.cancellations.export.*')">
                    Reportes
                </x-advisor.sidebar-item>
            @endif
        </nav>
    </div>

    <div class="sidebar-section">
        <div class="sidebar-section-title">Administracion</div>
        <nav class="sidebar-nav" aria-label="Administracion">
            @if ($canNavigate(\App\Enums\PermissionKey::ADVISOR_ACCOUNTS_VIEW))
                <x-advisor.sidebar-item :href="route('advisor.accounts.index')" icon="users" :active="request()->routeIs('advisor.accounts.*')">
                    Asesores
                </x-advisor.sidebar-item>
            @endif
        </nav>
    </div>

    <div class="sidebar-footer">
        <div class="sidebar-section-title">Cuenta</div>
        <nav class="sidebar-nav" aria-label="Cuenta">
            <x-advisor.sidebar-item :href="route('password.change')" icon="user" :active="request()->routeIs('password.change')">
                Mi cuenta
            </x-advisor.sidebar-item>
            <x-advisor.sidebar-item :href="route('logout')" icon="logout" method="POST">
                Salir
            </x-advisor.sidebar-item>
        </nav>
    </div>
</aside>
