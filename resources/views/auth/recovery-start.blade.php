@extends('layouts.guest')

@section('title', 'Recuperar contrasena')
@section('guest_shell_class', 'guest-shell-auth')

@section('content')
    <div class="grid">
        <div>
            <p class="eyebrow">Recuperacion</p>
            <h1>Recuperar contrasena</h1>
            <p class="muted">Si los datos corresponden, enviaremos un codigo OTP al celular registrado.</p>
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

        <form method="post" action="{{ route('password.recovery.store') }}" data-loading>
            @csrf
            <x-ui.input name="username" label="Usuario o cedula" :value="old('username')" autocomplete="username" required />

            <div class="auth-actions">
                <x-ui.button type="submit">Continuar</x-ui.button>
                <x-ui.button variant="ghost" :href="route('login')">Ingresar</x-ui.button>
            </div>
        </form>
    </div>
@endsection
