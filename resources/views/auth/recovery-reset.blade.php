@extends('layouts.guest')

@section('title', 'Codigo de recuperacion')
@section('guest_shell_class', 'guest-shell-auth')

@section('content')
    <div class="grid">
        <div>
            <p class="eyebrow">Validacion OTP</p>
            <h1>Codigo de recuperacion</h1>
            <p class="muted">Ingresa el codigo recibido y define tu nueva contrasena.</p>
        </div>

        @if ($status ?? null)
            <x-ui.alert type="info">{{ $status }}</x-ui.alert>
        @endif

        @if ($errors->any())
            <x-ui.alert type="danger">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-ui.alert>
        @endif

        <form method="post" action="{{ route('password.recovery.update') }}" data-loading>
            @csrf
            <input type="hidden" name="public_reference" value="{{ $publicReference }}">
            <div class="form-grid">
                <x-ui.input name="otp" label="Codigo" inputmode="numeric" autocomplete="one-time-code" required />
                <label class="field">
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

        <form method="post" action="{{ route('otp.resend', $publicReference) }}" data-loading>
            @csrf
            <x-ui.button variant="outline" type="submit">Reenviar codigo</x-ui.button>
        </form>
    </div>
@endsection
