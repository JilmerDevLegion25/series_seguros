@props(['name', 'label', 'type' => 'text', 'value' => null, 'help' => null])

<label class="field">
    <span class="field-label">{{ $label }}</span>
    <input class="field-control" name="{{ $name }}" type="{{ $type }}" value="{{ $value }}" {{ $attributes }}>
    @if ($help)
        <span class="help-text">{{ $help }}</span>
    @endif
    @error($name)
        <span class="help-text" role="alert">{{ $message }}</span>
    @enderror
</label>
