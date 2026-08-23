@extends('layouts.advisor')

@section('title', 'Reasignar Moto')

@section('content')
    <x-ui.page-header :title="'Reasignar Advisor Moto '.$moto->radicado" :subtitle="'Version '.$moto->version" />

    @if ($errors->any())
        <x-ui.alert type="danger">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-ui.alert>
    @endif

    <form method="post" action="{{ route('advisor.moto.reassign.store', $moto) }}" data-loading>
        @csrf
        <input name="expected_version" type="hidden" value="{{ old('expected_version', $moto->version) }}">

        <x-ui.form-section title="Reasignacion" description="No modifica owner Client, creator historico ni radicado.">
            <div class="form-grid">
                <x-ui.textarea class="span-2" name="reason" label="Razon del cambio" required>{{ old('reason') }}</x-ui.textarea>
                <label class="field span-2">
                    <span class="field-label">Advisor asignado</span>
                    <select class="field-control" name="assigned_advisor_user_id" required>
                        @foreach ($advisors as $advisor)
                            <option value="{{ $advisor->id }}" @selected((int) old('assigned_advisor_user_id', $moto->assigned_advisor_user_id) === $advisor->id)>
                                {{ $advisor->username }} - {{ $advisor->name }}
                            </option>
                        @endforeach
                    </select>
                </label>
            </div>
        </x-ui.form-section>

        <div class="button-row">
            <x-ui.button type="submit" icon="users">Reasignar</x-ui.button>
        </div>
    </form>
@endsection
