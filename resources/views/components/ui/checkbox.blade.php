@props(['name', 'label', 'value' => '1', 'checked' => false, 'help' => null])

<label class="checkbox-field">
    <input name="{{ $name }}" type="checkbox" value="{{ $value }}" @checked($checked) {{ $attributes }}>
    <span>
        <span class="field-label">{{ $label }}</span>
        @if ($help)
            <span class="help-text">{{ $help }}</span>
        @endif
    </span>
</label>
