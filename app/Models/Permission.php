<?php

namespace App\Models;

use App\Enums\PermissionKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use RuntimeException;

/**
 * @property int $id
 * @property string $key
 * @property string $name
 */
final class Permission extends Model
{
    protected $fillable = [
        'key',
        'name',
    ];

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permissions')
            ->withTimestamps();
    }

    public static function idFor(PermissionKey $permissionKey): int
    {
        $id = self::query()->where('key', $permissionKey->value)->value('id');

        if (! is_numeric($id)) {
            throw new RuntimeException("Permission {$permissionKey->value} is missing.");
        }

        return (int) $id;
    }
}
