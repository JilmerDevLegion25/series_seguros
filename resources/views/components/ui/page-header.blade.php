@props(['title', 'subtitle' => null])

<header {{ $attributes->merge(['class' => 'page-header']) }}>
    <div>
        {{ $eyebrow ?? '' }}
        <h1 class="page-title">{{ $title }}</h1>
        @if ($subtitle)
            <p class="page-subtitle">{{ $subtitle }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="page-actions">
            {{ $actions }}
        </div>
    @endisset
</header>
