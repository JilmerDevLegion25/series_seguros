<?php

namespace App\Actions\Authorization;

use App\Authorization\PermissionCatalogue;
use App\Models\Permission;
use Illuminate\Support\Facades\DB;

final readonly class SyncPermissionCatalogueAction
{
    public function execute(): void
    {
        DB::transaction(function (): void {
            $definitions = PermissionCatalogue::definitions();
            $keys = array_keys($definitions);
            $unknownPermissionIds = Permission::query()
                ->whereNotIn('key', $keys)
                ->pluck('id');

            if ($unknownPermissionIds->isNotEmpty()) {
                DB::table('role_permissions')
                    ->whereIn('permission_id', $unknownPermissionIds)
                    ->delete();

                Permission::query()
                    ->whereIn('id', $unknownPermissionIds)
                    ->delete();
            }

            foreach ($definitions as $key => $name) {
                Permission::query()->updateOrCreate(
                    ['key' => $key],
                    ['name' => $name],
                );
            }
        });
    }
}
