<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property int $role_id
 * @property int $permission_id
 */
final class RolePermission extends Pivot
{
    protected $table = 'role_permissions';

    public $incrementing = false;

    protected $fillable = [
        'role_id',
        'permission_id',
    ];
}
