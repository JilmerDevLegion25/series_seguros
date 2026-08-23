@extends('layouts.advisor')

@section('title', 'Cuentas Asesor')

@section('content')
    @php
        $currentSort = $currentSort ?? 'username_asc';
        $sortBaseQuery = collect(request()->except(['page', 'sort']))
            ->reject(fn (mixed $value): bool => $value === null || $value === '')
            ->all();
        $sortColumns = [
            'username' => ['asc' => 'username_asc', 'desc' => 'username_desc'],
            'name' => ['asc' => 'name_asc', 'desc' => 'name_desc'],
            'email' => ['asc' => 'email_asc', 'desc' => 'email_desc'],
            'phone' => ['asc' => 'phone_asc', 'desc' => 'phone_desc'],
            'status' => ['asc' => 'status_asc', 'desc' => 'status_desc'],
        ];
        $sortUrl = static function (string $column) use ($sortBaseQuery, $sortColumns, $currentSort): string {
            $options = $sortColumns[$column];
            $nextSort = $currentSort === $options['asc'] ? $options['desc'] : $options['asc'];

            return route('advisor.accounts.index', array_merge($sortBaseQuery, ['sort' => $nextSort]));
        };
        $sortClass = static function (string $column) use ($sortColumns, $currentSort): string {
            $options = $sortColumns[$column];

            return in_array($currentSort, $options, true)
                ? 'table-sort is-active '.($currentSort === $options['asc'] ? 'is-asc' : 'is-desc')
                : 'table-sort';
        };
    @endphp

    <x-ui.page-header title="Cuentas Asesores" :subtitle="$advisors->count().' registros'">
        <x-slot:actions>
            @can('advisor_accounts.create')
                <x-ui.button :href="route('advisor.accounts.create')" icon="plus">Crear Cuenta</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    @if (session()->has('temporary_password'))
        <x-ui.card class="temporary-password-panel" role="status" aria-live="polite">
            <span class="temporary-password-icon" aria-hidden="true">
                <x-ui.icon name="lock" />
            </span>
            <div class="temporary-password-copy">
                <p class="eyebrow">Contrasena temporal</p>
                <h2>Contrasena generada para {{ session('temporary_password_advisor') }}</h2>
                <p>Entregala al Advisor y solicita el cambio en el primer ingreso. Esta clave solo se muestra una vez.</p>
            </div>
            <div class="temporary-password-value" aria-label="Contrasena temporal generada">
                <code>{{ session('temporary_password') }}</code>
            </div>
        </x-ui.card>
    @endif

    <div class="account-reset-overlay" data-account-reset-overlay hidden>
        <section class="account-reset-modal" role="status" aria-live="assertive">
            <span class="export-build-animation account-reset-animation" aria-hidden="true">
                <span></span>
                <span></span>
                <span></span>
            </span>
            <h2>Generando contrasena temporal</h2>
            <p>Estamos preparando una clave segura para el Advisor.</p>
        </section>
    </div>

    <x-ui.card class="table-panel workspace-table advisor-accounts-table">
        <div class="account-sort-overlay" data-account-sort-overlay hidden>
            <div class="account-sort-loader" role="status" aria-live="polite">
                <span class="account-sort-animation" aria-hidden="true">
                    <span></span>
                    <span></span>
                    <span></span>
                </span>
                <strong>Ordenando informacion</strong>
            </div>
        </div>

        <x-ui.table>
            <thead>
                <tr>
                    <th><a class="{{ $sortClass('username') }}" href="{{ $sortUrl('username') }}" aria-label="Ordenar por usuario" data-account-sort-trigger>Usuario<span class="sort-indicator" aria-hidden="true"></span></a></th>
                    <th><a class="{{ $sortClass('name') }}" href="{{ $sortUrl('name') }}" aria-label="Ordenar por nombre" data-account-sort-trigger>Nombre<span class="sort-indicator" aria-hidden="true"></span></a></th>
                    <th><a class="{{ $sortClass('email') }}" href="{{ $sortUrl('email') }}" aria-label="Ordenar por email" data-account-sort-trigger>Email<span class="sort-indicator" aria-hidden="true"></span></a></th>
                    <th><a class="{{ $sortClass('phone') }}" href="{{ $sortUrl('phone') }}" aria-label="Ordenar por telefono" data-account-sort-trigger>Telefono<span class="sort-indicator" aria-hidden="true"></span></a></th>
                    <th><a class="{{ $sortClass('status') }}" href="{{ $sortUrl('status') }}" aria-label="Ordenar por estado" data-account-sort-trigger>Estado<span class="sort-indicator" aria-hidden="true"></span></a></th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($advisors as $advisor)
                    <tr>
                        <td><strong class="workspace-radicado">{{ $advisor->username }}</strong></td>
                        <td>
                            <span class="workspace-person">
                                <strong>{{ $advisor->name }}</strong>
                                <span>Advisor</span>
                            </span>
                        </td>
                        <td>
                            <span class="workspace-contact">
                                <span>{{ $advisor->email }}</span>
                            </span>
                        </td>
                        <td>{{ $advisor->phone }}</td>
                        <td><x-ui.status-badge :status="$advisor->status->value" /></td>
                        <td>
                            <div class="row-actions workspace-row-actions">
                                @can('advisor_accounts.update')
                                    <a class="btn btn-outline btn-icon btn-sm" href="{{ route('advisor.accounts.edit', $advisor) }}" title="Editar Advisor" aria-label="Editar Advisor {{ $advisor->username }}">
                                        <x-ui.icon name="edit" />
                                    </a>
                                @endcan

                                @can('advisor_accounts.reset_password')
                                    <form method="post" action="{{ route('advisor.accounts.reset-password', $advisor) }}" data-loading data-account-reset-loading data-confirm="Se generara una contrasena temporal para este Advisor.">
                                        @csrf
                                        <button class="btn btn-outline btn-icon btn-sm" type="submit" title="Resetear contrasena temporal" aria-label="Resetear contrasena temporal de {{ $advisor->username }}">
                                            <x-ui.icon name="lock" />
                                        </button>
                                    </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-ui.table>
    </x-ui.card>
@endsection
