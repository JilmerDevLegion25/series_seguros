@props(['type' => 'info'])

<div {{ $attributes->merge(['class' => 'alert alert-'.$type, 'role' => $type === 'danger' ? 'alert' : 'status']) }}>
    {{ $slot }}
</div>
