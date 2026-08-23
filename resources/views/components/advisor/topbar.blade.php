@props(['title'])

<header class="advisor-topbar">
    <div class="topbar-left">
        <button class="btn btn-outline btn-icon" type="button" data-sidebar-toggle aria-label="Abrir o colapsar navegacion">
            <x-ui.icon name="menu" />
        </button>
        <div>
            <p class="eyebrow">Advisor</p>
            <strong>{{ $title }}</strong>
        </div>
    </div>

    <div class="topbar-actions">
        <x-ui.dropdown id="advisor-user-menu">
            <x-slot:trigger>
                <x-ui.icon name="user" />
                <span>{{ auth()->user()?->name }}</span>
            </x-slot:trigger>
            <a href="{{ route('password.change') }}">
                <x-ui.icon name="lock" />
                Mi cuenta
            </a>
            <form method="post" action="{{ route('logout') }}">
                @csrf
                <button type="submit">
                    <x-ui.icon name="logout" />
                    Salir
                </button>
            </form>
        </x-ui.dropdown>
    </div>
</header>
