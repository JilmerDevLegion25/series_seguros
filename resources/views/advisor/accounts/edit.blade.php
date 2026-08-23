@extends('layouts.advisor')

@section('title', 'Editar Asesor')

@section('content')
    <x-ui.page-header title="Editar Asesor" :subtitle="$advisor->username">
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

    <form method="post" action="{{ route('advisor.accounts.update', $advisor) }}" data-loading>
        @csrf
        @method('PATCH')
        <x-ui.form-section title="Datos del Advisor">
            <div class="form-grid">
                <label class="field">
                    <span class="field-label">Nombre</span>
                    <input class="field-control" name="name" value="{{ old('name', $advisor->name) }}" required>
                </label>
                <label class="field">
                    <span class="field-label">Email</span>
                    <input class="field-control" name="email" value="{{ old('email', $advisor->email) }}">
                </label>
                <label class="field">
                    <span class="field-label">Telefono</span>
                    <input class="field-control" name="phone" value="{{ old('phone', $advisor->phone) }}">
                </label>
                <label class="field">
                    <span class="field-label">Estado</span>
                    <select class="field-control" name="status" required>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(old('status', $advisor->status->value) === $status->value)>
                                {{ $status->value }}
                            </option>
                        @endforeach
                    </select>
                </label>
            </div>
        </x-ui.form-section>
        <div class="button-row">
            <x-ui.button type="submit">Guardar</x-ui.button>
        </div>
    </form>
@endsection
