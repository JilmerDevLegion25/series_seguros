@props(['label', 'icon', 'type' => 'button'])

<button {{ $attributes->merge(['class' => 'btn btn-outline btn-icon', 'type' => $type, 'aria-label' => $label, 'title' => $label]) }}>
    <x-ui.icon :name="$icon" />
</button>
