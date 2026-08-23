<?php

namespace Tests\Feature\AdvisorAccounts;

use App\Actions\AdvisorAccounts\ResetAdvisorPasswordAction;
use App\Actions\Auth\CreateAdvisorAction;
use App\Actions\Authorization\GrantRolePermissionAction;
use App\DTOs\Auth\CreateAdvisorData;
use App\Enums\PermissionKey;
use App\Enums\RoleCode;
use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\RefreshPhaseDatabase;
use Tests\TestCase;

final class AdvisorAccountAdminTest extends TestCase
{
    use RefreshPhaseDatabase;

    public function test_advisor_account_routes_require_their_permissions(): void
    {
        $manager = $this->makeAdvisor('MANAGER01');
        $target = $this->makeAdvisor('TARGET01');

        $this->actingAsReady($manager);

        $this->get(route('advisor.accounts.index'))->assertForbidden();
        $this->get(route('advisor.accounts.create'))->assertForbidden();
        $this->post(route('advisor.accounts.store'), [])->assertForbidden();
        $this->get(route('advisor.accounts.edit', $target))->assertForbidden();
        $this->patch(route('advisor.accounts.update', $target), [])->assertForbidden();
        $this->post(route('advisor.accounts.reset-password', $target))->assertForbidden();
    }

    public function test_client_cannot_access_advisor_account_admin(): void
    {
        $client = User::query()->create([
            'role_id' => Role::idFor(RoleCode::CLIENT),
            'username' => '1234567890',
            'identity' => '1234567890',
            'name' => 'Client User',
            'email' => 'client@example.test',
            'phone' => '+573001234567',
            'password' => Hash::make('password'),
            'status' => UserStatus::ACTIVE,
            'must_change_password' => false,
        ]);

        $this->actingAsReady($client);

        $this->get(route('advisor.accounts.index'))->assertForbidden();
    }

    public function test_create_advisor_uses_temporary_password_and_forced_change(): void
    {
        $result = app(CreateAdvisorAction::class)->execute(new CreateAdvisorData(
            username: 'newadvisor01',
            name: 'New Advisor',
            email: 'newadvisor@example.test',
            phone: '3001234567',
        ));

        $advisor = $result['user']->fresh();

        $this->assertInstanceOf(User::class, $advisor);
        $this->assertSame(Role::idFor(RoleCode::ADVISOR), $advisor->role_id);
        $this->assertSame('NEWADVISOR01', $advisor->username);
        $this->assertTrue($advisor->must_change_password);
        $this->assertSame(20, strlen($result['temporary_password']));
        $this->assertNotSame($result['temporary_password'], $advisor->password);
        $this->assertTrue(Hash::check($result['temporary_password'], $advisor->password));
    }

    public function test_create_advisor_route_is_permission_protected(): void
    {
        $manager = $this->makeAdvisor('MANAGER01');
        $this->grant(PermissionKey::ADVISOR_ACCOUNTS_CREATE);

        $this->actingAsReady($manager);

        $this->post(route('advisor.accounts.store'), [
            'username' => 'ops01',
            'name' => 'Ops Advisor',
            'email' => 'ops@example.test',
            'phone' => '3001234567',
        ])->assertRedirect(route('advisor.accounts.index', absolute: false));

        $created = User::query()->where('username', 'OPS01')->firstOrFail();

        $this->assertSame(Role::idFor(RoleCode::ADVISOR), $created->role_id);
        $this->assertTrue($created->must_change_password);
        $this->assertSame('+573001234567', $created->phone);
    }

    public function test_update_advisor_route_does_not_allow_privilege_escalation_and_invalidates_inactive_sessions(): void
    {
        $manager = $this->makeAdvisor('MANAGER01');
        $target = $this->makeAdvisor('TARGET01');
        $this->grant(PermissionKey::ADVISOR_ACCOUNTS_UPDATE);
        $this->insertSessionFor($target);

        $this->actingAsReady($manager);

        $this->patch(route('advisor.accounts.update', $target), [
            'name' => 'Updated Target',
            'email' => 'target.updated@example.test',
            'phone' => '+573007654321',
            'status' => UserStatus::INACTIVE->value,
            'role_id' => Role::idFor(RoleCode::CLIENT),
            'must_change_password' => false,
        ])->assertRedirect(route('advisor.accounts.index', absolute: false));

        $target->refresh();

        $this->assertSame(Role::idFor(RoleCode::ADVISOR), $target->role_id);
        $this->assertSame('Updated Target', $target->name);
        $this->assertSame('+573007654321', $target->phone);
        $this->assertSame(UserStatus::INACTIVE, $target->status);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $target->id)->count());
    }

    public function test_reset_advisor_password_sets_temporary_password_and_invalidates_target_sessions(): void
    {
        $target = $this->makeAdvisor('TARGET01', [
            'password' => Hash::make('old-password'),
            'must_change_password' => false,
        ]);
        $this->insertSessionFor($target);

        $result = app(ResetAdvisorPasswordAction::class)->execute($target);
        $target->refresh();

        $this->assertSame(20, strlen($result['temporary_password']));
        $this->assertTrue($target->must_change_password);
        $this->assertFalse(Hash::check('old-password', $target->password));
        $this->assertTrue(Hash::check($result['temporary_password'], $target->password));
        $this->assertSame(0, DB::table('sessions')->where('user_id', $target->id)->count());
    }

    public function test_reset_advisor_password_route_requires_permission_and_deletes_are_not_exposed(): void
    {
        $manager = $this->makeAdvisor('MANAGER01');
        $target = $this->makeAdvisor('TARGET01');
        $this->grant(PermissionKey::ADVISOR_ACCOUNTS_RESET_PASSWORD);
        $this->insertSessionFor($target);

        $this->actingAsReady($manager);

        $this->post(route('advisor.accounts.reset-password', $target))
            ->assertRedirect(route('advisor.accounts.index', absolute: false));
        $this->assertSame(0, DB::table('sessions')->where('user_id', $target->id)->count());

        $this->delete('/advisor/accounts/'.$target->id)->assertStatus(405);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeAdvisor(string $username, array $attributes = []): User
    {
        return User::query()->create(array_merge([
            'role_id' => Role::idFor(RoleCode::ADVISOR),
            'username' => $username,
            'identity' => null,
            'name' => $username,
            'email' => strtolower($username).'@example.test',
            'phone' => '+573001234567',
            'password' => Hash::make('password'),
            'status' => UserStatus::ACTIVE,
            'must_change_password' => false,
        ], $attributes));
    }

    private function grant(PermissionKey $permissionKey): void
    {
        app(GrantRolePermissionAction::class)->execute(
            Role::query()->where('code', RoleCode::ADVISOR->value)->firstOrFail(),
            $permissionKey,
        );
    }

    private function actingAsReady(User $user): void
    {
        $this->actingAs($user)
            ->withSession([
                'auth_started_at' => now()->timestamp,
                'auth_last_activity_at' => now()->timestamp,
            ]);
    }

    private function insertSessionFor(User $user): void
    {
        DB::table('sessions')->insert([
            'id' => 'session-'.$user->id,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Feature test',
            'payload' => 'payload',
            'last_activity' => now()->timestamp,
        ]);
    }
}
