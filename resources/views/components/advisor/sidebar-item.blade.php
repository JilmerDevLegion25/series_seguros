@props(['href' => null, 'icon' => 'chevron', 'active' => false, 'method' => 'GET', 'confirm' => null])

@if (strtoupper($method) === 'POST')
    <form method="post" action="{{ $href }}" @if($confirm) data-confirm="{{ $confirm }}" @endif>
        @csrf
        <button class="sidebar-action" type="submit">
            <x-ui.icon class="sidebar-icon" :name="$icon" />
            <span class="sidebar-label">{{ $slot }}</span>
        </button>
    </form>
@else
    <a class="sidebar-item @if($active) is-active @endif" href="{{ $href }}">
        <x-ui.icon class="sidebar-icon" :name="$icon" />
        <span class="sidebar-label">{{ $slot }}</span>
    </a>
@endif
