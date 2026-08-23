@props(['title', 'description' => null])

<section {{ $attributes->merge(['class' => 'form-section']) }}>
    <div class="form-section-header">
        <h2>{{ $title }}</h2>
        @if ($description)
            <p class="form-section-description">{{ $description }}</p>
        @endif
    </div>
    {{ $slot }}
</section>
