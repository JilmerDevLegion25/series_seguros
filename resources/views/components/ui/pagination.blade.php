@props(['paginator'])

@if ($paginator->total() > 0)
    <nav {{ $attributes->merge(['class' => 'pager', 'aria-label' => 'Paginacion']) }}>
        <p class="pager-summary">
            Mostrando {{ $paginator->firstItem() }}-{{ $paginator->lastItem() }} de {{ $paginator->total() }} registros
        </p>
        <div class="pager-controls">
            @if ($paginator->previousPageUrl())
                <a class="btn btn-outline" href="{{ $paginator->previousPageUrl() }}">Anterior</a>
            @endif
            <span class="muted">Pagina {{ $paginator->currentPage() }} de {{ $paginator->lastPage() }}</span>
            @if ($paginator->nextPageUrl())
                <a class="btn btn-outline" href="{{ $paginator->nextPageUrl() }}">Siguiente</a>
            @endif
        </div>
    </nav>
@endif
