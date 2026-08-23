@props(['name', 'label', 'value' => null, 'help' => 'Ingresa solo el celular colombiano de 10 digitos.'])

@php
    $displayValue = is_scalar($value) ? trim((string) $value) : '';
    $digits = preg_replace('/\D+/', '', $displayValue) ?? '';

    if (str_starts_with($digits, '57') && strlen($digits) === 12) {
        $digits = substr($digits, 2);
    }

    if ($digits !== '') {
        $displayValue = $digits;
    }
@endphp

<label class="field">
    <span class="field-label">{{ $label }}</span>
    <span class="input-prefix-group">
        <span class="input-prefix" aria-label="Colombia">
            <span class="flag-colombia" aria-hidden="true"></span>
        </span>
        <input
            class="field-control"
            name="{{ $name }}"
            value="{{ $displayValue }}"
            inputmode="numeric"
            autocomplete="tel-national"
            pattern="3[0-9]{9}"
            maxlength="10"
            placeholder="3001234567"
            data-digits-only
            {{ $attributes }}
        >
    </span>
    <!-- @if ($help)
        <span class="help-text">{{ $help }}</span>
    @endif -->
    @error($name)
        <span class="help-text" role="alert">{{ $message }}</span>
    @enderror
</label>
