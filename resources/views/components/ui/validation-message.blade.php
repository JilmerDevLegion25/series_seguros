@props(['name'])

@error($name)
    <span {{ $attributes->merge(['class' => 'help-text', 'role' => 'alert']) }}>{{ $message }}</span>
@enderror
