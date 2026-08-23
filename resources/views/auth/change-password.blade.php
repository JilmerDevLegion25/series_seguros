@extends('layouts.guest')

@section('title', 'Cambiar contrasena')

@section('content')
    <div class="grid">
        <div>
            <p class="eyebrow">Cuenta</p>
            <h1>Cambiar contrasena</h1>
            <p class="muted">Define una contrasena nueva para continuar usando el portal.</p>
        </div>

        @if ($errors->any())
            <x-ui.alert type="danger">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-ui.alert>
        @endif

        <form method="post" action="{{ route('password.update') }}" data-loading>
            @csrf
            <div class="form-grid">
                <label class="field span-2">
                    <span class="field-label">Nueva contrasena</span>
                    <input class="field-control" name="password" type="password" autocomplete="new-password" required>
                </label>
                <label class="field span-2">
                    <span class="field-label">Confirmar contrasena</span>
                    <input class="field-control" name="password_confirmation" type="password" autocomplete="new-password" required>
                </label>
            </div>

            <div class="auth-actions">
                <x-ui.button type="submit">Guardar</x-ui.button>
            </div>
        </form>

        <form method="post" action="{{ route('logout') }}">
            @csrf
            <x-ui.button variant="outline" type="submit" icon="logout">Salir</x-ui.button>
        </form>
    </div>
@endsection
