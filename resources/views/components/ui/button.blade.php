@props(['variant' => 'primary', 'type' => 'button', 'href' => null, 'icon' => null])

@if ($href)
    <a {{ $attributes->merge(['class' => 'btn btn-'.$variant, 'href' => $href]) }}>
        @if ($icon)
            <x-ui.icon :name="$icon" />
        @endif
        {{ $slot }}
    </a>
@else
    <button {{ $attributes->merge(['class' => 'btn btn-'.$variant, 'type' => $type]) }}>
        @if ($icon)
            <x-ui.icon :name="$icon" />
        @endif
        {{ $slot }}
    </button>
@endif
