<?php

namespace Tests\Feature\Auth;

use App\Enums\RoleCode;
use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\User;
use App\Services\Clock\Clock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Hash;
use Tests\RefreshPhaseDatabase;
use Tests\TestCase;

final class SessionLifetimeTest extends TestCase
{
    use RefreshPhaseDatabase;

    public function test_idle_timeout_expires_after_thirty_minutes_without_activity(): void
    {
        $clock = new MutableClock(CarbonImmutable::parse('2026-08-19 10:00:00'));
        $this->app->instance(Clock::class, $clock);
        $user = $this->makeUser();

        $this->actingAs($user)
            ->withSession([
                'auth_started_at' => $clock->now()->timestamp,
                'auth_last_activity_at' => $clock->now()->timestamp,
            ]);

        $clock->setNow(CarbonImmutable::parse('2026-08-19 10:31:00'));

        $this->get('/dashboard')->assertRedirect(route('login', absolute: false));
        $this->assertGuest();
    }

    public function test_activity_updates_idle_but_not_absolute_lifetime(): void
    {
        $clock = new MutableClock(CarbonImmutable::parse('2026-08-19 10:00:00'));
        $this->app->instance(Clock::class, $clock);
        $user = $this->makeUser();

        $this->actingAs($user)
            ->withSession([
                'auth_started_at' => $clock->now()->timestamp,
                'auth_last_activity_at' => $clock->now()->timestamp,
            ]);

        $clock->setNow(CarbonImmutable::parse('2026-08-19 10:29:00'));
        $this->get('/dashboard')->assertOk();

        $clock->setNow(CarbonImmutable::parse('2026-08-19 18:01:00'));
        $this->get('/dashboard')->assertRedirect(route('login', absolute: false));
        $this->assertGuest();
    }

    private function makeUser(): User
    {
        return User::query()->create([
            'role_id' => Role::idFor(RoleCode::CLIENT),
            'username' => '1234567890',
            'identity' => '1234567890',
            'name' => 'Client User',
            'email' => 'client@example.test',
            'phone' => '+573001234567',
            'password' => Hash::make('1234*'),
            'status' => UserStatus::ACTIVE,
            'must_change_password' => false,
        ]);
    }
}

final class MutableClock implements Clock
{
    public function __construct(
        private CarbonImmutable $now,
    ) {}

    public function now(): CarbonImmutable
    {
        return $this->now;
    }

    public function setNow(CarbonImmutable $now): void
    {
        $this->now = $now;
    }
}
