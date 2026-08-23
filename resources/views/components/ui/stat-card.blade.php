@props(['label', 'value'])

<article {{ $attributes->merge(['class' => 'stat-card']) }}>
    <p class="stat-label">{{ $label }}</p>
    <p class="stat-value">{{ $value }}</p>
    {{ $slot }}
</article>
