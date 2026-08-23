<?php

namespace App\Actions\Authorization;

use App\Authorization\PermissionCatalogue;
use App\Enums\PermissionKey;
use App\Enums\RoleCode;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class GrantRolePermissionAction
{
    public function execute(Role $role, PermissionKey $permissionKey): void
    {
        if ($role->code === RoleCode::CLIENT->value && PermissionCatalogue::isAdvisorOnly($permissionKey)) {
            throw new InvalidArgumentException('Client role cannot receive Advisor-only permissions.');
        }

        DB::table('role_permissions')->updateOrInsert(
            [
                'role_id' => $role->id,
                'permission_id' => Permission::idFor($permissionKey),
            ],
            [
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }
}
