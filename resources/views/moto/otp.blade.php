@extends(auth()->check() ? 'layouts.advisor' : 'layouts.guest')

@section('title', 'Validar codigo')

@section('content')
    <x-ui.page-header title="Validar codigo" subtitle="Ingresa el OTP enviado al celular registrado para radicar la solicitud." />

    @if ($errors->any())
        <x-ui.alert type="danger">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-ui.alert>
    @endif

    <x-ui.form-section title="Codigo OTP">
        <form method="post" action="{{ $completeRoute }}" data-loading>
            @csrf
            <input type="hidden" name="challenge_reference" value="{{ $challenge->public_reference }}">
            <x-ui.input name="otp" label="Codigo OTP" inputmode="numeric" autocomplete="one-time-code" required />
            <div class="button-row">
                <x-ui.button type="submit">Radicar</x-ui.button>
            </div>
        </form>
    </x-ui.form-section>

    <form method="post" action="{{ $resendRoute }}" data-loading>
        @csrf
        <x-ui.button variant="outline" type="submit">Reenviar codigo</x-ui.button>
    </form>
@endsection
