@props(['variant' => null])

<span {{ $attributes->merge(['class' => trim('badge '.($variant ? 'badge-'.$variant : ''))]) }}>
    {{ $slot }}
</span>
