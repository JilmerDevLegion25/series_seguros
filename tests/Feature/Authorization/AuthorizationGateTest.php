<?php

namespace Tests\Feature\Authorization;

use App\Actions\Authorization\GrantRolePermissionAction;
use App\Actions\Authorization\RevokeRolePermissionAction;
use App\Enums\PermissionKey;
use App\Enums\RoleCode;
use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\RefreshPhaseDatabase;
use Tests\TestCase;

final class AuthorizationGateTest extends TestCase
{
    use RefreshPhaseDatabase;

    public function test_advisor_without_permission_is_denied_and_with_permission_is_allowed(): void
    {
        $advisor = $this->makeUser(RoleCode::ADVISOR);
        $advisorRole = Role::query()->where('code', RoleCode::ADVISOR->value)->firstOrFail();

        $this->assertFalse(Gate::forUser($advisor)->allows(PermissionKey::CANCELLATIONS_EXPORT->value));

        app(GrantRolePermissionAction::class)->execute($advisorRole, PermissionKey::CANCELLATIONS_EXPORT);

        $this->assertTrue(Gate::forUser($advisor)->allows(PermissionKey::CANCELLATIONS_EXPORT->value));
    }

    public function test_grant_and_revoke_affect_the_next_request_without_logout_or_login(): void
    {
        Route::get('/authorization-harness/export', static fn (): string => 'ok')
            ->middleware(['web', 'auth', 'active', 'password.changed', 'can:'.PermissionKey::CANCELLATIONS_EXPORT->value]);

        $advisor = $this->makeUser(RoleCode::ADVISOR);
        $advisorRole = Role::query()->where('code', RoleCode::ADVISOR->value)->firstOrFail();

        $this->actingAsReady($advisor);
        $this->get('/authorization-harness/export')->assertForbidden();

        app(GrantRolePermissionAction::class)->execute($advisorRole, PermissionKey::CANCELLATIONS_EXPORT);
        $this->get('/authorization-harness/export')->assertOk()->assertSee('ok');

        app(RevokeRolePermissionAction::class)->execute($advisorRole, PermissionKey::CANCELLATIONS_EXPORT);
        $this->get('/authorization-harness/export')->assertForbidden();
    }

    public function test_client_does_not_receive_advisor_capabilities(): void
    {
        $client = $this->makeUser(RoleCode::CLIENT, [
            'username' => '1234567890',
            'identity' => '1234567890',
        ]);

        $this->assertFalse(Gate::forUser($client)->allows(PermissionKey::ADVISOR_ACCOUNTS_VIEW->value));
        $this->assertFalse(Gate::forUser($client)->allows(PermissionKey::RESPONSES_IMPORT->value));
        $this->assertFalse(Gate::forUser($client)->allows(PermissionKey::CANCELLATIONS_EXPORT->value));
    }

    public function test_inactive_advisor_is_denied_even_with_permission(): void
    {
        $advisor = $this->makeUser(RoleCode::ADVISOR, ['status' => UserStatus::INACTIVE]);
        $advisorRole = Role::query()->where('code', RoleCode::ADVISOR->value)->firstOrFail();

        app(GrantRolePermissionAction::class)->execute($advisorRole, PermissionKey::CANCELLATIONS_EXPORT);

        $this->assertFalse(Gate::forUser($advisor)->allows(PermissionKey::CANCELLATIONS_EXPORT->value));
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
            'name' => 'Authorized User',
            'email' => 'authorized@example.test',
            'phone' => '+573001234567',
            'password' => Hash::make('password'),
            'status' => UserStatus::ACTIVE,
            'must_change_password' => false,
        ], $attributes));
    }

    private function actingAsReady(User $user): void
    {
        $this->actingAs($user)
            ->withSession([
                'auth_started_at' => now()->timestamp,
                'auth_last_activity_at' => now()->timestamp,
            ]);
    }
}
