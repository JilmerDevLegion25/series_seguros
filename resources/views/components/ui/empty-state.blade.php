@props(['title', 'message' => null])

<div {{ $attributes->merge(['class' => 'empty-state']) }}>
    <strong>{{ $title }}</strong>
    @if ($message)
        <span class="muted">{{ $message }}</span>
    @endif
    {{ $slot }}
</div>
