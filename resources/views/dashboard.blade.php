@extends('layouts.advisor')

@section('title', 'Panel')

@section('content')
    <x-ui.page-header title="Panel" :subtitle="auth()->user()?->name">
        <x-slot:actions>
            <x-ui.button variant="outline" :href="route('password.change')" icon="user">Mi cuenta</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($advisorStats)
        <section class="stats-grid advisor-stats-grid" aria-label="Resumen de solicitudes">
            <x-ui.stat-card class="dashboard-stat-card tone-teal" label="Total" :value="$advisorStats['total']">
                <span class="dashboard-card-icon"><x-ui.icon name="list" /></span>
            </x-ui.stat-card>
            <x-ui.stat-card class="dashboard-stat-card tone-cyan" label="Moto" :value="$advisorStats['moto']">
                <span class="dashboard-card-icon"><x-ui.icon name="moto" /></span>
            </x-ui.stat-card>
            <x-ui.stat-card class="dashboard-stat-card tone-coral" label="Credit" :value="$advisorStats['credit']">
                <span class="dashboard-card-icon"><x-ui.icon name="credit" /></span>
            </x-ui.stat-card>
            <x-ui.stat-card class="dashboard-stat-card tone-amber" label="En gestion" :value="$advisorStats['en_gestion']">
                <span class="dashboard-card-icon"><x-ui.icon name="shield" /></span>
            </x-ui.stat-card>
        </section>
    @endif

    <section class="dashboard-actions" aria-label="Accesos rapidos">
        <div class="choice-grid dashboard-choice-grid">
            @can('cancellations.view')
                <a class="link-card dashboard-link-card tone-teal" href="{{ route('advisor.cancellations.index') }}">
                    <span class="dashboard-link-label">Workspace Asesor</span>
                    <span class="dashboard-card-icon"><x-ui.icon name="list" /></span>
                </a>
            @endcan

            @can('advisor_accounts.view')
                <a class="link-card dashboard-link-card tone-cyan" href="{{ route('advisor.accounts.index') }}">
                    <span class="dashboard-link-label">Cuentas Advisor</span>
                    <span class="dashboard-card-icon"><x-ui.icon name="users" /></span>
                </a>
            @endcan

            @can('cancellations.create')
                <a class="link-card dashboard-link-card tone-coral" href="{{ route('advisor.moto.create') }}">
                    <span class="dashboard-link-label">Crear Moto</span>
                    <span class="dashboard-card-icon"><x-ui.icon name="moto" /></span>
                </a>
                <a class="link-card dashboard-link-card tone-amber" href="{{ route('advisor.credit.create') }}">
                    <span class="dashboard-link-label">Crear Credito</span>
                    <span class="dashboard-card-icon"><x-ui.icon name="credit" /></span>
                </a>
            @endcan

            @can('responses.import')
                <a class="link-card dashboard-link-card tone-slate" href="{{ route('advisor.responses.import.create') }}">
                    <span class="dashboard-link-label">Importar respuestas</span>
                    <span class="dashboard-card-icon"><x-ui.icon name="upload" /></span>
                </a>
            @endcan

        </div>
    </section>
@endsection
