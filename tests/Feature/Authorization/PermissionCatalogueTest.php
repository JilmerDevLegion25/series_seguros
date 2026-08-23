<?php

namespace Tests\Feature\Authorization;

use App\Actions\Authorization\GrantRolePermissionAction;
use App\Actions\Authorization\SyncPermissionCatalogueAction;
use App\Authorization\PermissionCatalogue;
use App\Enums\PermissionKey;
use App\Enums\RoleCode;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\RefreshPhaseDatabase;
use Tests\TestCase;

final class PermissionCatalogueTest extends TestCase
{
    use RefreshPhaseDatabase;

    public function test_catalogue_contains_exactly_the_approved_keys(): void
    {
        $this->assertSame(
            PermissionCatalogue::keys(),
            Permission::query()->orderBy('id')->pluck('key')->all(),
        );

        $this->assertCount(12, Permission::query()->get());
    }

    public function test_catalogue_sync_is_idempotent_and_removes_unknown_permissions(): void
    {
        app(SyncPermissionCatalogueAction::class)->execute();
        app(SyncPermissionCatalogueAction::class)->execute();

        $this->assertSame(12, Permission::query()->count());
        $this->assertSame(
            PermissionCatalogue::keys(),
            Permission::query()->orderBy('id')->pluck('key')->all(),
        );
    }

    public function test_database_rejects_permissions_outside_the_approved_catalogue(): void
    {
        $this->expectException(QueryException::class);

        DB::table('permissions')->insert([
            'key' => 'admin.anything',
            'name' => 'Forbidden permission',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_no_admin_role_and_no_direct_user_permissions_table_exist(): void
    {
        $this->assertFalse(Role::query()->where('code', 'ADMIN')->exists());
        $this->assertFalse(Schema::hasTable('user_permissions'));
    }

    public function test_client_role_cannot_receive_advisor_only_permissions_through_grant_action(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(GrantRolePermissionAction::class)->execute(
            Role::query()->where('code', RoleCode::CLIENT->value)->firstOrFail(),
            PermissionKey::ADVISOR_ACCOUNTS_VIEW,
        );
    }
}
