@props(['items' => []])

@if ($items !== [])
    <nav {{ $attributes->merge(['aria-label' => 'Ruta de navegacion', 'class' => 'breadcrumb']) }}>
        @foreach ($items as $item)
            @if (! empty($item['href']))
                <a href="{{ $item['href'] }}">{{ $item['label'] }}</a>
            @else
                <span>{{ $item['label'] }}</span>
            @endif
            @if (! $loop->last)
                <span aria-hidden="true">/</span>
            @endif
        @endforeach
    </nav>
@endif
