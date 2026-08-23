@props(['name' => 'plate', 'label' => 'Placa', 'value' => null, 'help' => null])

@php
    $displayValue = is_scalar($value) ? strtoupper(trim((string) $value)) : '';
@endphp

<label class="field">
    <span class="field-label">{{ $label }}</span>
    <span class="input-prefix-group">
        <span class="input-prefix plate-input-prefix" aria-hidden="true">
            <x-ui.icon name="moto" />
        </span>
        <input
            class="field-control"
            name="{{ $name }}"
            value="{{ $displayValue }}"
            maxlength="6"
            minlength="6"
            pattern="[A-Za-z0-9]{6}"
            inputmode="text"
            autocomplete="off"
            autocapitalize="characters"
            data-alnum-uppercase
            {{ $attributes }}
        >
    </span>
    @if ($help)
        <span class="help-text">{{ $help }}</span>
    @endif
    @error($name)
        <span class="help-text" role="alert">{{ $message }}</span>
    @enderror
</label>
