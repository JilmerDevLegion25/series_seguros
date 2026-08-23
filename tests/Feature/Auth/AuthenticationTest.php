<?php

namespace Tests\Feature\Auth;

use App\Enums\RoleCode;
use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\RefreshPhaseDatabase;
use Tests\TestCase;

final class AuthenticationTest extends TestCase
{
    use RefreshPhaseDatabase;

    public function test_client_can_login_with_canonical_identity_and_must_change_password(): void
    {
        $client = $this->makeUser(RoleCode::CLIENT, [
            'username' => '1234567890',
            'identity' => '1234567890',
            'password' => Hash::make('1234*'),
            'must_change_password' => true,
        ]);

        $response = $this->post('/login', [
            'username' => '1.234.567.890',
            'password' => '1234*',
            'remember' => 'on',
        ]);

        $response->assertRedirect(route('password.change', absolute: false));
        $this->assertAuthenticatedAs($client);
        $this->assertTrue(session()->has('auth_started_at'));
        $this->assertTrue(session()->has('auth_last_activity_at'));
        $rememberCookies = array_filter(
            $response->headers->getCookies(),
            static fn (Cookie $cookie): bool => str_starts_with($cookie->getName(), 'remember_web'),
        );
        $this->assertSame([], $rememberCookies);
    }

    public function test_advisor_can_login_with_alphanumeric_username(): void
    {
        $advisor = $this->makeUser(RoleCode::ADVISOR, [
            'username' => 'ADVISOR01',
            'identity' => null,
            'password' => Hash::make('TemporaryPassword1*'),
            'must_change_password' => false,
        ]);

        $this->post('/login', [
            'username' => 'advisor01',
            'password' => 'TemporaryPassword1*',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($advisor);
    }

    public function test_login_ignores_previous_intended_url_and_starts_on_dashboard(): void
    {
        $advisor = $this->makeUser(RoleCode::ADVISOR, [
            'username' => 'ADVISOR02',
            'identity' => null,
            'password' => Hash::make('TemporaryPassword1*'),
            'must_change_password' => false,
        ]);

        $this->withSession(['url.intended' => '/client/cancellations/moto/3'])
            ->post('/login', [
                'username' => 'advisor02',
                'password' => 'TemporaryPassword1*',
            ])
            ->assertRedirect(route('dashboard', absolute: false))
            ->assertSessionMissing('url.intended');

        $this->assertAuthenticatedAs($advisor);
    }

    public function test_login_errors_are_generic_for_missing_wrong_or_inactive_users(): void
    {
        $this->makeUser(RoleCode::CLIENT, [
            'username' => '1234567890',
            'identity' => '1234567890',
            'password' => Hash::make('correct-password'),
            'status' => UserStatus::INACTIVE,
        ]);

        $this->from('/login')->post('/login', [
            'username' => '1234567890',
            'password' => 'correct-password',
        ])->assertRedirect('/login')->assertSessionHasErrors(['username' => 'Credenciales inválidas.']);

        $this->from('/login')->post('/login', [
            'username' => '1234567890',
            'password' => 'wrong-password',
        ])->assertRedirect('/login')->assertSessionHasErrors(['username' => 'Credenciales inválidas.']);

        $this->from('/login')->post('/login', [
            'username' => '9999999999',
            'password' => 'anything',
        ])->assertRedirect('/login')->assertSessionHasErrors(['username' => 'Credenciales inválidas.']);
    }

    public function test_login_rate_limiting(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->from('/login')->post('/login', [
                'username' => '1234567890',
                'password' => 'wrong-password',
            ])->assertRedirect('/login');
        }

        $this->post('/login', [
            'username' => '1234567890',
            'password' => 'wrong-password',
        ])->assertTooManyRequests();
    }

    public function test_logout_invalidates_session_and_regenerates_csrf_token(): void
    {
        $user = $this->makeUser(RoleCode::CLIENT, ['must_change_password' => false]);
        $this->actingAs($user);
        session()->put('auth_started_at', 1);
        session()->put('auth_last_activity_at', 1);
        $token = session()->token();

        $this->post('/logout')->assertRedirect(route('login', absolute: false));

        $this->assertGuest();
        $this->assertNotSame($token, session()->token());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeUser(RoleCode $roleCode, array $attributes = []): User
    {
        return User::query()->create(array_merge([
            'role_id' => Role::idFor($roleCode),
            'username' => $roleCode === RoleCode::CLIENT ? '1234567890' : 'ADVISOR01',
            'identity' => $roleCode === RoleCode::CLIENT ? '1234567890' : null,
            'name' => 'Test User',
            'email' => 'user@example.test',
            'phone' => '+573001234567',
            'password' => Hash::make('password'),
            'status' => UserStatus::ACTIVE,
            'must_change_password' => false,
        ], $attributes));
    }
}
