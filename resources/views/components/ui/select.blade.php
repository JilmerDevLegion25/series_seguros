@props(['name', 'label', 'help' => null])

<label class="field">
    <span class="field-label">{{ $label }}</span>
    <select class="field-control" name="{{ $name }}" {{ $attributes }}>
        {{ $slot }}
    </select>
    @if ($help)
        <span class="help-text">{{ $help }}</span>
    @endif
    @error($name)
        <span class="help-text" role="alert">{{ $message }}</span>
    @enderror
</label>
