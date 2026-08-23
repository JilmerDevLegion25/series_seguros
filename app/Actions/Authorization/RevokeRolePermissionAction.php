<?php

namespace App\Actions\Authorization;

use App\Enums\PermissionKey;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\DB;

final readonly class RevokeRolePermissionAction
{
    public function execute(Role $role, PermissionKey $permissionKey): void
    {
        DB::table('role_permissions')
            ->where('role_id', $role->id)
            ->where('permission_id', Permission::idFor($permissionKey))
            ->delete();
    }
}
