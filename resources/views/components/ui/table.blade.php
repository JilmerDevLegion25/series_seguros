<div {{ $attributes->merge(['class' => 'table-wrap is-responsive-card']) }}>
    <table class="data-table">
        {{ $slot }}
    </table>
</div>
