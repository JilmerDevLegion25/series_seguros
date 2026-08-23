@props(['name', 'label', 'value', 'checked' => false])

<label class="checkbox-field">
    <input name="{{ $name }}" type="radio" value="{{ $value }}" @checked($checked) {{ $attributes }}>
    <span class="field-label">{{ $label }}</span>
</label>
