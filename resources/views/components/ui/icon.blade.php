@props(['name'])

@php
    $paths = [
        'audit' => 'M4 5h16M4 12h16M4 19h10M8 8v8m8-8v4',
        'bell' => 'M18 8a6 6 0 1 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4',
        'chevron' => 'm9 6 6 6-6 6',
        'credit' => 'M3 7h18v10H3V7Zm0 3h18M7 15h4',
        'dashboard' => 'M4 13h7V4H4v9Zm9 7h7V4h-7v16ZM4 20h7v-5H4v5Z',
        'download' => 'M12 3v12m0 0 4-4m-4 4-4-4M4 21h16',
        'edit' => 'M4 20h4l11-11-4-4L4 16v4Zm12-15 3 3',
        'filter' => 'M4 6h16M7 12h10M10 18h4',
        'home' => 'M4 11 12 4l8 7v9h-5v-6H9v6H4v-9Z',
        'list' => 'M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01',
        'lock' => 'M7 10V8a5 5 0 0 1 10 0v2M6 10h12v10H6V10Z',
        'logout' => 'M14 8V5a2 2 0 0 0-2-2H5v18h7a2 2 0 0 0 2-2v-3M10 12h11m0 0-3-3m3 3-3 3',
        'menu' => 'M4 6h16M4 12h16M4 18h16',
        'moto' => 'M5 16a3 3 0 1 0 0 .1M19 16a3 3 0 1 0 0 .1M8 16h8l-2-5h-4l-2 5Zm2-5 2-4h3',
        'plus' => 'M12 5v14M5 12h14',
        'refresh' => 'M20 6v5h-5M4 18v-5h5M18.5 9A7 7 0 0 0 6.8 6.8L4 11m16 2-2.8 4.2A7 7 0 0 1 5.5 15',
        'search' => 'M10 18a8 8 0 1 1 5.66-2.34L21 21',
        'shield' => 'M12 3 5 6v6c0 4 3 7 7 9 4-2 7-5 7-9V6l-7-3Z',
        'upload' => 'M12 21V9m0 0-4 4m4-4 4 4M4 3h16',
        'user' => 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-7 9a7 7 0 0 1 14 0',
        'users' => 'M16 11a4 4 0 1 0-8 0 4 4 0 0 0 8 0ZM4 21a8 8 0 0 1 16 0M19 8a3 3 0 0 1 0 6',
        'x' => 'M6 6l12 12M18 6 6 18',
    ];
@endphp

<svg {{ $attributes->merge(['class' => 'icon', 'aria-hidden' => 'true', 'viewBox' => '0 0 24 24', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '2', 'stroke-linecap' => 'round', 'stroke-linejoin' => 'round']) }}>
    <path d="{{ $paths[$name] ?? $paths['chevron'] }}" />
</svg>
