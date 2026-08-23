@extends('layouts.guest')

@section('title', config('app.name'))

@section('content')
    <div class="grid">
        <div>
            <p class="eyebrow">Inicio</p>
            <h1>{{ config('app.name') }}</h1>
            <p class="muted">Selecciona el flujo que necesitas iniciar.</p>
        </div>

        <div class="public-links">
            <a class="link-card" href="{{ route('public.moto.create') }}">
                <span>Cancelacion Moto</span>
                <x-ui.icon name="moto" />
            </a>
            <a class="link-card" href="{{ route('public.credit.create') }}">
                <span>Cancelacion Credito</span>
                <x-ui.icon name="credit" />
            </a>
            <a class="link-card" href="{{ route('login') }}">
                <span>Ingresar</span>
                <x-ui.icon name="lock" />
            </a>
        </div>
    </div>
@endsection
