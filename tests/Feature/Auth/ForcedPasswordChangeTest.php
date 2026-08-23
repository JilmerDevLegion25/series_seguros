<?php

namespace Tests\Feature\Auth;

use App\Enums\RoleCode;
use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\RefreshPhaseDatabase;
use Tests\TestCase;

final class ForcedPasswordChangeTest extends TestCase
{
    use RefreshPhaseDatabase;

    public function test_user_must_change_password_before_dashboard(): void
    {
        $user = $this->makeUser(mustChangePassword: true);

        $this->actingAs($user)
            ->withSession(['auth_started_at' => now()->timestamp, 'auth_last_activity_at' => now()->timestamp])
            ->get('/dashboard')
            ->assertRedirect(route('password.change', absolute: false));
    }

    public function test_password_change_hashes_password_and_clears_flag(): void
    {
        $user = $this->makeUser(mustChangePassword: true);

        $this->actingAs($user)
            ->withSession(['auth_started_at' => now()->timestamp, 'auth_last_activity_at' => now()->timestamp])
            ->post('/password/change', [
                'password' => 'changed-password',
                'password_confirmation' => 'changed-password',
            ])
            ->assertRedirect(route('dashboard', absolute: false));

        $user->refresh();

        $this->assertFalse($user->must_change_password);
        $this->assertTrue(Hash::check('changed-password', $user->password));
        $this->assertNotSame('changed-password', $user->password);
    }

    private function makeUser(bool $mustChangePassword): User
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
            'must_change_password' => $mustChangePassword,
        ]);
    }
}
