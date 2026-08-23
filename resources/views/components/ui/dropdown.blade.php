@props(['id' => 'dropdown-menu'])

<div {{ $attributes->merge(['class' => 'dropdown', 'data-dropdown' => true]) }}>
    <button class="btn btn-outline" type="button" data-dropdown-toggle aria-expanded="false" aria-controls="{{ $id }}">
        {{ $trigger }}
    </button>
    <div id="{{ $id }}" class="dropdown-menu" data-dropdown-menu hidden>
        {{ $slot }}
    </div>
</div>
