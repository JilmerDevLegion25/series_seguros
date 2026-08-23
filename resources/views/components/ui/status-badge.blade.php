@props(['status'])

<span {{ $attributes->merge(['class' => 'status-badge', 'data-status' => (string) $status]) }}>
    {{ str_replace('_', ' ', (string) $status) }}
</span>
