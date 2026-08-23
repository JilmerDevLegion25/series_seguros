@extends('layouts.advisor')

@section('title', 'Crear Asesor')

@section('content')
    <x-ui.page-header title="Crear Asesor">
        <x-slot:actions>
            <x-ui.button variant="outline" :href="route('advisor.accounts.index')">Volver</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($errors->any())
        <x-ui.alert type="danger">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-ui.alert>
    @endif

    <form method="post" action="{{ route('advisor.accounts.store') }}" data-loading>
        @csrf
        <x-ui.form-section title="Datos del Advisor">
            <div class="form-grid">
                <label class="field">
                    <span class="field-label">Usuario</span>
                    <input class="field-control" name="username" value="{{ old('username') }}" required>
                </label>
                <label class="field">
                    <span class="field-label">Nombre</span>
                    <input class="field-control" name="name" value="{{ old('name') }}" required>
                </label>
                <label class="field">
                    <span class="field-label">Email</span>
                    <input class="field-control" name="email" value="{{ old('email') }}">
                </label>
                <label class="field">
                    <span class="field-label">Telefono</span>
                    <input class="field-control" name="phone" value="{{ old('phone') }}">
                </label>
            </div>
        </x-ui.form-section>
        <div class="button-row">
            <x-ui.button type="submit">Crear</x-ui.button>
        </div>
    </form>
@endsection
