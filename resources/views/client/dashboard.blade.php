@extends('client.layout')

@section('title', 'Panel Client')

@section('content')
    <x-ui.page-header title="Panel Cliente" :subtitle="auth()->user()?->name">
        <x-slot:actions>
            <x-ui.button variant="outline" :href="route('password.change')" icon="user">Mi cuenta</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <!-- <section class="stats-grid advisor-stats-grid client-stats-grid" aria-label="Resumen de solicitudes">
        <x-ui.stat-card class="dashboard-stat-card tone-teal" label="Total" :value="$clientStats['total']">
            <span class="dashboard-card-icon"><x-ui.icon name="list" /></span>
        </x-ui.stat-card>
        <x-ui.stat-card class="dashboard-stat-card tone-cyan" label="Moto" :value="$clientStats['moto']">
            <span class="dashboard-card-icon"><x-ui.icon name="moto" /></span>
        </x-ui.stat-card>
        <x-ui.stat-card class="dashboard-stat-card tone-coral" label="Credit" :value="$clientStats['credit']">
            <span class="dashboard-card-icon"><x-ui.icon name="credit" /></span>
        </x-ui.stat-card>
        <x-ui.stat-card class="dashboard-stat-card tone-amber" label="En gestion" :value="$clientStats['en_gestion']">
            <span class="dashboard-card-icon"><x-ui.icon name="shield" /></span>
        </x-ui.stat-card>
    </section> -->

    <section class="dashboard-actions client-dashboard-actions" aria-label="Accesos rapidos">
        <div class="choice-grid dashboard-choice-grid">
            <a class="link-card dashboard-link-card tone-teal" href="{{ route('client.cancellations.index') }}">
                <span class="dashboard-link-label">Mis solicitudes | 
                    <span class="stat-value">{{$clientStats['total']}}</span>
                </span>
                <span class="dashboard-card-icon"><x-ui.icon name="list" /></span>
            </a>
            <a class="link-card dashboard-link-card tone-cyan" href="{{ route('client.notifications.index') }}">
                <span class="dashboard-link-label">Notificaciones</span>
                <span class="client-dashboard-notification">
                    <span class="dashboard-card-icon"><x-ui.icon name="bell" /></span>
                    @if ($unreadCount > 0)
                        <span class="client-dashboard-badge">{{ $unreadCount }}</span>
                    @endif
                </span>
            </a>
        </div>
    </section>
@endsection
