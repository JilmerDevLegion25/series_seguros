@extends('layouts.advisor')

@section('title', 'Workspace Asesor')
@section('advisor_main_class', 'advisor-main-inner-workspace')

@section('content')
    @php
        $currentType = (string) request('type', 'ALL');
        $currentSort = (string) request('sort', 'created_at_desc');
        $currentPerPage = (int) request('per_page', 10);
        $perPageOptions = [10, 20, 30, 50];
        $primaryFilterKeys = ['radicado', 'holder_cedula', 'plate'];
        $advancedFilterKeys = [
            'status',
            'holder_name',
            'holder_email',
            'holder_phone',
            'credit_number',
            'assigned_advisor_user_id',
            'created_from',
            'created_to',
        ];
        $hasPrimaryFilters = collect($primaryFilterKeys)->contains(fn (string $key): bool => filled(request($key)));
        $hasAdvancedFilters = collect($advancedFilterKeys)->contains(fn (string $key): bool => filled(request($key)));
        $perPageBaseQuery = collect(request()->except(['page', 'per_page']))
            ->reject(fn (mixed $value): bool => $value === null || $value === '')
            ->all();
        $filtersOpen = $errors->any() || $hasPrimaryFilters || $hasAdvancedFilters;
        $moreFiltersOpen = $errors->any() || $hasAdvancedFilters;
        $typeTabs = [
            ['label' => 'Todos', 'value' => 'ALL', 'icon' => 'list'],
            ['label' => 'Motos', 'value' => \App\Enums\CancellationType::MOTO->value, 'icon' => 'moto'],
            ['label' => 'Creditos', 'value' => \App\Enums\CancellationType::CREDIT->value, 'icon' => 'credit'],
        ];
        $tabBaseQuery = collect(request()->except(['page', 'type']))
            ->reject(fn (mixed $value): bool => $value === null || $value === '')
            ->all();
        $sortBaseQuery = collect(request()->except(['page', 'sort']))
            ->reject(fn (mixed $value): bool => $value === null || $value === '')
            ->all();
        $sortColumns = [
            'type' => ['asc' => 'cancellation_type_asc', 'desc' => 'cancellation_type_desc'],
            'radicado' => ['asc' => 'radicado_asc', 'desc' => 'radicado_desc'],
            'holder' => ['asc' => 'holder_name_asc', 'desc' => 'holder_name_desc'],
            'contact' => ['asc' => 'holder_email_asc', 'desc' => 'holder_email_desc'],
            'plate' => ['asc' => 'plate_asc', 'desc' => 'plate_desc'],
            'credit' => ['asc' => 'credit_number_asc', 'desc' => 'credit_number_desc'],
            'status' => ['asc' => 'status_asc', 'desc' => 'status_desc'],
            'created' => ['asc' => 'created_at_asc', 'desc' => 'created_at_desc'],
        ];
        $sortUrl = static function (string $column) use ($sortBaseQuery, $sortColumns, $currentSort): string {
            $options = $sortColumns[$column];
            $nextSort = $currentSort === $options['asc'] ? $options['desc'] : $options['asc'];

            return route('advisor.cancellations.index', array_merge($sortBaseQuery, ['sort' => $nextSort]));
        };
        $sortClass = static function (string $column) use ($sortColumns, $currentSort): string {
            $options = $sortColumns[$column];

            return in_array($currentSort, $options, true)
                ? 'table-sort is-active '.($currentSort === $options['asc'] ? 'is-asc' : 'is-desc')
                : 'table-sort';
        };
    @endphp

    <x-ui.page-header title="Workspace Asesor" :subtitle="$results->total().' solicitudes'">
        <x-slot:actions>
            <button class="btn btn-outline filter-toggle" type="button" data-filter-toggle aria-controls="workspace-filters" aria-expanded="{{ $filtersOpen ? 'true' : 'false' }}">
                <x-ui.icon name="filter" />
                Filtros
            </button>
            @if ($canImportResponses)
                <x-ui.button :href="route('advisor.responses.import.create')" icon="upload">Importar respuestas</x-ui.button>
            @endif
            @if ($canExport)
                <x-ui.button :href="route('advisor.cancellations.export.create', request()->only(['created_from', 'created_to', 'type']))" icon="download">Exportar Reportes</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    @if ($errors->any())
        <x-ui.alert type="danger">
            <strong>Los filtros necesitan ajuste.</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-ui.alert>
    @endif

    <x-ui.form-section id="workspace-filters" title="Filtros" description="Busca por los campos principales o despliega opciones adicionales." data-filter-panel class="workspace-filters" :hidden="! $filtersOpen">
        <form method="get" action="{{ route('advisor.cancellations.index') }}" class="workspace-filter-form" data-loading data-search-loading>
            <input type="hidden" name="type" value="{{ $currentType }}">
            <input type="hidden" name="sort" value="{{ $currentSort }}">
            <input type="hidden" name="per_page" value="{{ $currentPerPage }}">

            <div class="workspace-filter-grid workspace-filter-primary">
                <label class="field">
                    <span class="field-label">Radicado</span>
                    <span class="input-prefix-group filter-input-group">
                        <span class="input-prefix filter-input-prefix" aria-hidden="true">
                            <x-ui.icon name="search" />
                        </span>
                        <input class="field-control" name="radicado" inputmode="numeric" value="{{ request('radicado') }}">
                    </span>
                </label>
                <label class="field">
                    <span class="field-label">Cedula</span>
                    <span class="input-prefix-group filter-input-group">
                        <span class="input-prefix filter-input-prefix" aria-hidden="true">
                            <x-ui.icon name="user" />
                        </span>
                        <input class="field-control" name="holder_cedula" value="{{ request('holder_cedula') }}">
                    </span>
                </label>
                <label class="field">
                    <span class="field-label">Placa</span>
                    <span class="input-prefix-group filter-input-group">
                        <span class="input-prefix filter-input-prefix" aria-hidden="true">
                            <x-ui.icon name="moto" />
                        </span>
                        <input
                            class="field-control"
                            name="plate"
                            value="{{ request('plate') }}"
                            maxlength="6"
                            minlength="6"
                            pattern="[A-Za-z0-9]{6}"
                            data-alnum-uppercase
                        >
                    </span>
                </label>
            </div>

            <div id="workspace-more-filters" class="workspace-filter-grid workspace-filter-advanced" @if (! $moreFiltersOpen) hidden @endif>
                <label class="field">
                    <span class="field-label">Estado</span>
                    <span class="input-prefix-group filter-input-group">
                        <span class="input-prefix filter-input-prefix" aria-hidden="true">
                            <x-ui.icon name="shield" />
                        </span>
                        <select class="field-control" name="status">
                            <option value="">Todos</option>
                            @foreach ($statusOptions as $value => $label)
                                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </span>
                </label>
                <label class="field">
                    <span class="field-label">Titular</span>
                    <span class="input-prefix-group filter-input-group">
                        <span class="input-prefix filter-input-prefix" aria-hidden="true">
                            <x-ui.icon name="user" />
                        </span>
                        <input class="field-control" name="holder_name" value="{{ request('holder_name') }}" maxlength="150">
                    </span>
                </label>
                <label class="field">
                    <span class="field-label">Correo</span>
                    <span class="input-prefix-group filter-input-group">
                        <span class="input-prefix filter-input-prefix" aria-hidden="true">
                            <x-ui.icon name="search" />
                        </span>
                        <input class="field-control" name="holder_email" type="email" value="{{ request('holder_email') }}" maxlength="255">
                    </span>
                </label>
                <label class="field">
                    <span class="field-label">Celular</span>
                    <span class="input-prefix-group filter-input-group">
                        <span class="input-prefix filter-input-prefix" aria-hidden="true">
                            <x-ui.icon name="user" />
                        </span>
                        <input class="field-control" name="holder_phone" value="{{ request('holder_phone') }}">
                    </span>
                </label>
                <label class="field">
                    <span class="field-label">Numero de credito</span>
                    <span class="input-prefix-group filter-input-group">
                        <span class="input-prefix filter-input-prefix" aria-hidden="true">
                            <x-ui.icon name="credit" />
                        </span>
                        <input class="field-control" name="credit_number" value="{{ request('credit_number') }}" maxlength="50">
                    </span>
                </label>
                <label class="field">
                    <span class="field-label">Advisor asignado</span>
                    <span class="input-prefix-group filter-input-group">
                        <span class="input-prefix filter-input-prefix" aria-hidden="true">
                            <x-ui.icon name="users" />
                        </span>
                        <select class="field-control" name="assigned_advisor_user_id">
                            <option value="">Todos</option>
                            @foreach ($advisors as $advisor)
                                <option value="{{ $advisor->id }}" @selected((string) request('assigned_advisor_user_id') === (string) $advisor->id)>{{ $advisor->name }}</option>
                            @endforeach
                        </select>
                    </span>
                </label>
                <label class="field">
                    <span class="field-label">Desde</span>
                    <span class="input-prefix-group filter-input-group">
                        <span class="input-prefix filter-input-prefix" aria-hidden="true">
                            <x-ui.icon name="filter" />
                        </span>
                        <input class="field-control" name="created_from" type="date" value="{{ request('created_from') }}">
                    </span>
                </label>
                <label class="field">
                    <span class="field-label">Hasta</span>
                    <span class="input-prefix-group filter-input-group">
                        <span class="input-prefix filter-input-prefix" aria-hidden="true">
                            <x-ui.icon name="filter" />
                        </span>
                        <input class="field-control" name="created_to" type="date" value="{{ request('created_to') }}">
                    </span>
                </label>
            </div>

            <div class="workspace-filter-toolbar">
                <button class="btn btn-outline" type="button" data-filter-more-toggle aria-controls="workspace-more-filters" aria-expanded="{{ $moreFiltersOpen ? 'true' : 'false' }}">
                    <x-ui.icon name="filter" />
                    <span data-more-label>{{ $moreFiltersOpen ? 'Ocultar filtros adicionales' : 'Mas filtros' }}</span>
                </button>
                <div class="actions">
                    <button class="btn btn-primary" type="submit">
                        <x-ui.icon name="search" />
                        Buscar
                    </button>
                    <a class="btn btn-outline" href="{{ route('advisor.cancellations.index') }}">Limpiar</a>
                </div>
            </div>
        </form>
    </x-ui.form-section>

    <x-ui.card class="table-panel workspace-table">
        <nav class="workspace-type-tabs" aria-label="Tipo de cancelacion">
            @foreach ($typeTabs as $tab)
                @php
                    $tabQuery = $tabBaseQuery;

                    if ($tab['value'] === \App\Enums\CancellationType::MOTO->value) {
                        unset($tabQuery['credit_number']);
                    }

                    if ($tab['value'] === \App\Enums\CancellationType::CREDIT->value) {
                        unset($tabQuery['plate']);
                    }

                    if ($tab['value'] !== 'ALL') {
                        $tabQuery['type'] = $tab['value'];
                    }
                @endphp
                <a
                    class="workspace-type-tab @if ($currentType === $tab['value']) is-active @endif"
                    href="{{ route('advisor.cancellations.index', $tabQuery) }}"
                    data-search-loading-trigger
                    @if ($currentType === $tab['value']) aria-current="page" @endif
                >
                    <x-ui.icon :name="$tab['icon']" />
                    <span>{{ $tab['label'] }}</span>
                </a>
            @endforeach
        </nav>

        @if ($results->isEmpty())
            <x-ui.empty-state title="No hay solicitudes para estos filtros." />
        @else
            <div class="workspace-records-frame">
                <div class="search-loading-overlay table-search-loading-overlay" data-search-loading-overlay hidden>
                    <section class="search-loading-modal" role="status" aria-live="assertive" aria-label="Busqueda en progreso">
                        <span class="search-loading-spinner" aria-hidden="true">
                            <span></span>
                        </span>
                        <h2>Actualizando solicitudes</h2>
                        <p>Estamos aplicando filtros u ordenamiento.</p>
                    </section>
                </div>

                <x-ui.table>
                    <thead>
                        <tr>
                            <th><a class="{{ $sortClass('type') }}" href="{{ $sortUrl('type') }}" aria-label="Ordenar por tipo" data-search-loading-trigger>Tipo<span class="sort-indicator" aria-hidden="true"></span></a></th>
                            <th><a class="{{ $sortClass('radicado') }}" href="{{ $sortUrl('radicado') }}" aria-label="Ordenar por radicado" data-search-loading-trigger>Radicado<span class="sort-indicator" aria-hidden="true"></span></a></th>
                            <th><a class="{{ $sortClass('holder') }}" href="{{ $sortUrl('holder') }}" aria-label="Ordenar por titular" data-search-loading-trigger>Titular<span class="sort-indicator" aria-hidden="true"></span></a></th>
                            <th><a class="{{ $sortClass('contact') }}" href="{{ $sortUrl('contact') }}" aria-label="Ordenar por contacto" data-search-loading-trigger>Contacto<span class="sort-indicator" aria-hidden="true"></span></a></th>
                            <th><a class="{{ $sortClass('plate') }}" href="{{ $sortUrl('plate') }}" aria-label="Ordenar por placa" data-search-loading-trigger>Placa<span class="sort-indicator" aria-hidden="true"></span></a></th>
                            <th><a class="{{ $sortClass('credit') }}" href="{{ $sortUrl('credit') }}" aria-label="Ordenar por credito" data-search-loading-trigger>Credito<span class="sort-indicator" aria-hidden="true"></span></a></th>
                            <th><a class="{{ $sortClass('status') }}" href="{{ $sortUrl('status') }}" aria-label="Ordenar por estado" data-search-loading-trigger>Estado<span class="sort-indicator" aria-hidden="true"></span></a></th>
                            <th><a class="{{ $sortClass('status') }}" href="{{ $sortUrl('status') }}" aria-label="Ordenar por progreso" data-search-loading-trigger>Progreso<span class="sort-indicator" aria-hidden="true"></span></a></th>
                            <th><a class="{{ $sortClass('created') }}" href="{{ $sortUrl('created') }}" aria-label="Ordenar por fecha de creacion" data-search-loading-trigger>Creada<span class="sort-indicator" aria-hidden="true"></span></a></th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($results as $row)
                            @php
                                $isMoto = $row->cancellation_type === \App\Enums\CancellationType::MOTO->value;
                                $editRoute = $isMoto
                                    ? route('advisor.moto.edit', $row->id)
                                    : route('advisor.credit.edit', $row->id);
                                $retryRoute = $isMoto
                                    ? route('advisor.moto.sms.retry', $row->id)
                                    : route('advisor.credit.sms.retry', $row->id);
                                $activityRoute = $isMoto
                                    ? route('advisor.moto.activity', $row->id)
                                    : route('advisor.credit.activity', $row->id);
                                $latestRadicadoSmsStatus = (string) ($row->latest_radicado_sms_status ?? '');
                                $canRetryCurrentSms = $canRetrySms && in_array($latestRadicadoSmsStatus, [
                                    \App\Enums\SmsAttemptStatus::FAILED->value,
                                    \App\Enums\SmsAttemptStatus::UNKNOWN->value,
                                ], true);
                                $isComplete = $row->status === \App\Enums\CancellationStatus::RESPUESTA_OBTENIDA->value;
                                $progressPercent = $isComplete ? 100 : 50;
                            @endphp
                            <tr>
                                <td>
                                    <span class="product-pill {{ $isMoto ? 'is-moto' : 'is-credit' }}">
                                        <x-ui.icon :name="$isMoto ? 'moto' : 'credit'" />
                                        {{ $isMoto ? 'Moto' : 'Credit' }}
                                    </span>
                                </td>
                                <td><strong class="workspace-radicado">{{ $row->radicado }}</strong></td>
                                <td>
                                    <span class="workspace-person">
                                        <strong>{{ $row->holder_name }}</strong>
                                        <span>{{ $row->holder_cedula }}</span>
                                    </span>
                                </td>
                                <td>
                                    <span class="workspace-contact">
                                        <span>{{ $row->holder_email }}</span>
                                        <span>{{ $row->holder_phone }}</span>
                                    </span>
                                </td>
                                <td>{{ $row->plate ?: '-' }}</td>
                                <td>{{ $row->credit_number ?: '-' }}</td>
                                <td><x-ui.status-badge :status="$row->status" /></td>
                                <td>
                                    <span class="workspace-progress {{ $isComplete ? 'is-complete' : 'is-mid' }}" aria-label="Progreso {{ $progressPercent }}%">
                                        <span class="workspace-progress-track" aria-hidden="true"><span></span></span>
                                        <strong>{{ $progressPercent }}%</strong>
                                    </span>
                                </td>
                                <td>{{ $row->created_at ? \Carbon\CarbonImmutable::parse($row->created_at)->format('Y-m-d H:i') : '' }}</td>
                                <td>
                                    <div class="row-actions workspace-row-actions">
                                        @if ($canUpdate)
                                            <a class="btn btn-outline btn-icon btn-sm" href="{{ $editRoute }}" title="Editar solicitud" aria-label="Editar solicitud">
                                                <x-ui.icon name="edit" />
                                            </a>
                                        @endif
                                        <!-- @if ($canRetryCurrentSms)
                                            <form method="post" action="{{ $retryRoute }}" data-loading data-confirm="Se registrara un nuevo intento SMS para el radicado actual.">
                                                @csrf
                                                <button class="btn btn-outline btn-icon btn-sm" type="submit" title="Reintentar SMS" aria-label="Reintentar SMS">
                                                    <x-ui.icon name="refresh" />
                                                </button>
                                            </form>
                                        @endif -->
                                        <!-- @if ($canActivity)
                                            <a class="btn btn-outline btn-icon btn-sm" href="{{ $activityRoute }}" title="Historial operativo" aria-label="Historial operativo">
                                                <x-ui.icon name="audit" />
                                            </a>
                                        @endif -->
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.table>

                <div class="mobile-card-list" aria-label="Solicitudes en vista movil">
                @foreach ($results as $row)
                    @php
                        $isMoto = $row->cancellation_type === \App\Enums\CancellationType::MOTO->value;
                        $editRoute = $isMoto
                            ? route('advisor.moto.edit', $row->id)
                            : route('advisor.credit.edit', $row->id);
                        $retryRoute = $isMoto
                            ? route('advisor.moto.sms.retry', $row->id)
                            : route('advisor.credit.sms.retry', $row->id);
                        $activityRoute = $isMoto
                            ? route('advisor.moto.activity', $row->id)
                            : route('advisor.credit.activity', $row->id);
                        $latestRadicadoSmsStatus = (string) ($row->latest_radicado_sms_status ?? '');
                        $canRetryCurrentSms = $canRetrySms && in_array($latestRadicadoSmsStatus, [
                            \App\Enums\SmsAttemptStatus::FAILED->value,
                            \App\Enums\SmsAttemptStatus::UNKNOWN->value,
                        ], true);
                        $isComplete = $row->status === \App\Enums\CancellationStatus::RESPUESTA_OBTENIDA->value;
                    @endphp
                    <article class="mobile-row-card">
                        <div>
                            <strong>{{ $isMoto ? 'Moto' : 'Credit' }} {{ $row->radicado }}</strong>
                            <p class="muted">{{ $row->holder_name }}</p>
                        </div>
                        <x-ui.status-badge :status="$row->status" />
                        <span class="workspace-progress {{ $isComplete ? 'is-complete' : 'is-mid' }}" aria-label="Progreso {{ $isComplete ? 100 : 50 }}%">
                            <span class="workspace-progress-track" aria-hidden="true"><span></span></span>
                            <strong>{{ $isComplete ? 100 : 50 }}%</strong>
                        </span>
                        <div class="row-actions workspace-row-actions">
                            @if ($canUpdate)
                                <a class="btn btn-outline btn-icon btn-sm" href="{{ $editRoute }}" title="Editar solicitud" aria-label="Editar solicitud">
                                    <x-ui.icon name="edit" />
                                </a>
                            @endif
                            <!-- @if ($canRetryCurrentSms)
                                <form method="post" action="{{ $retryRoute }}" data-loading data-confirm="Se registrara un nuevo intento SMS para el radicado actual.">
                                    @csrf
                                    <button class="btn btn-outline btn-icon btn-sm" type="submit" title="Reintentar SMS" aria-label="Reintentar SMS">
                                        <x-ui.icon name="refresh" />
                                    </button>
                                </form>
                            @endif
                            @if ($canActivity)
                                <a class="btn btn-outline btn-icon btn-sm" href="{{ $activityRoute }}" title="Historial operativo" aria-label="Historial operativo">
                                    <x-ui.icon name="audit" />
                                </a>
                            @endif -->
                        </div>
                    </article>
                @endforeach
                </div>
            </div>

            <nav class="workspace-pager" aria-label="Paginacion de solicitudes">
                <div class="workspace-pager-summary">
                    <span class="workspace-pager-total">{{ $results->total() }}</span>
                    <span>
                        Mostrando {{ $results->firstItem() }}-{{ $results->lastItem() }} de {{ $results->total() }} registros
                    </span>
                </div>

                <form method="get" action="{{ route('advisor.cancellations.index') }}" class="workspace-per-page-form" data-search-loading>
                    @foreach ($perPageBaseQuery as $key => $value)
                        @if (is_scalar($value))
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endif
                    @endforeach
                    <label for="workspace-per-page">Filas</label>
                    <select id="workspace-per-page" name="per_page" aria-label="Registros por pagina" data-workspace-per-page-select>
                        @foreach ($perPageOptions as $pageSize)
                            <option value="{{ $pageSize }}" @selected($currentPerPage === $pageSize)>{{ $pageSize }}</option>
                        @endforeach
                    </select>
                </form>

                <div class="workspace-pager-controls" aria-label="Navegacion de paginas">
                    @if ($results->previousPageUrl())
                        <a class="workspace-page-btn" href="{{ $results->previousPageUrl() }}" data-search-loading-trigger aria-label="Pagina anterior">
                            <x-ui.icon name="chevron" />
                            <span class="sr-only">Anterior</span>
                        </a>
                    @else
                        <span class="workspace-page-btn is-disabled" aria-disabled="true">
                            <x-ui.icon name="chevron" />
                            <span class="sr-only">Anterior</span>
                        </span>
                    @endif

                    @foreach ($results->getUrlRange(max(1, $results->currentPage() - 2), min($results->lastPage(), $results->currentPage() + 2)) as $page => $url)
                        @if ($page === $results->currentPage())
                            <span class="workspace-page-number is-active" aria-current="page">{{ $page }}</span>
                        @else
                            <a class="workspace-page-number" href="{{ $url }}" data-search-loading-trigger aria-label="Ir a pagina {{ $page }}">{{ $page }}</a>
                        @endif
                    @endforeach

                    @if ($results->nextPageUrl())
                        <a class="workspace-page-btn is-next" href="{{ $results->nextPageUrl() }}" data-search-loading-trigger aria-label="Pagina siguiente">
                            <span class="sr-only">Siguiente</span>
                            <x-ui.icon name="chevron" />
                        </a>
                    @else
                        <span class="workspace-page-btn is-next is-disabled" aria-disabled="true">
                            <span class="sr-only">Siguiente</span>
                            <x-ui.icon name="chevron" />
                        </span>
                    @endif
                </div>
            </nav>
        @endif
    </x-ui.card>
@endsection
