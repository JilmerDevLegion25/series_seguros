<?php

namespace Tests\Feature\Users;

use App\Enums\RoleCode;
use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Hash;
use Tests\RefreshPhaseDatabase;
use Tests\TestCase;

final class UserSchemaTest extends TestCase
{
    use RefreshPhaseDatabase;

    public function test_structural_roles_exist_without_admin(): void
    {
        $this->assertDatabaseHas('roles', ['code' => RoleCode::CLIENT->value]);
        $this->assertDatabaseHas('roles', ['code' => RoleCode::ADVISOR->value]);
        $this->assertDatabaseMissing('roles', ['code' => 'ADMIN']);
    }

    public function test_username_is_unique_and_client_identity_is_unique(): void
    {
        $this->makeUser(['username' => '1234567890', 'identity' => '1234567890']);

        $this->expectException(QueryException::class);
        $this->makeUser(['username' => '1234567890', 'identity' => '9999999999']);
    }

    public function test_phone_is_not_unique_and_status_is_cast(): void
    {
        $first = $this->makeUser([
            'username' => '1234567890',
            'identity' => '1234567890',
            'phone' => '+573001234567',
        ]);
        $second = $this->makeUser([
            'username' => '2222222222',
            'identity' => '2222222222',
            'phone' => '+573001234567',
            'status' => UserStatus::INACTIVE,
        ]);

        $this->assertSame(UserStatus::ACTIVE, $first->status);
        $this->assertSame(UserStatus::INACTIVE, $second->status);
        $this->assertSame('+573001234567', $second->phone);
    }

    public function test_same_client_identity_cannot_be_inserted_twice(): void
    {
        $this->makeUser(['username' => '1234567890', 'identity' => '1234567890']);

        $this->expectException(QueryException::class);
        $this->makeUser(['username' => '9999999999', 'identity' => '1234567890']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeUser(array $attributes): User
    {
        return User::query()->create(array_merge([
            'role_id' => Role::idFor(RoleCode::CLIENT),
            'username' => '1234567890',
            'identity' => '1234567890',
            'name' => 'Client User',
            'email' => 'client@example.test',
            'phone' => '+573001234567',
            'password' => Hash::make('1234*'),
            'status' => UserStatus::ACTIVE,
            'must_change_password' => true,
        ], $attributes));
    }
}
