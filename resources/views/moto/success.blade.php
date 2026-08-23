@extends(auth()->check() ? 'layouts.advisor' : 'layouts.guest')

@section('title', 'Solicitud recibida')
@section('guest_page_class', 'guest-page-receipt')
@section('guest_shell_class', 'guest-shell-receipt')
@section('auth_card_class', 'auth-card-receipt')

@section('content')
    <x-public.success-confirmation product="Moto" :radicado="$moto->radicado" :already-completed="$alreadyCompleted" />
@endsection
