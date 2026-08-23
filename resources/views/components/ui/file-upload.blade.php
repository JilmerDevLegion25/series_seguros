@props(['name', 'label', 'help' => null])

<label class="file-upload">
    <span class="field-label">{{ $label }}</span>
    <input name="{{ $name }}" type="file" {{ $attributes }}>
    @if ($help)
        <span class="help-text">{{ $help }}</span>
    @endif
    @error($name)
        <span class="help-text" role="alert">{{ $message }}</span>
    @enderror
</label>
