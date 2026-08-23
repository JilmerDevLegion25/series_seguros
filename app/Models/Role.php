<?php

namespace App\Models;

use App\Enums\RoleCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 */
final class Role extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'code',
        'name',
    ];

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return BelongsToMany<Permission, $this>
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions')
            ->withTimestamps();
    }

    public static function idFor(RoleCode $code): int
    {
        $id = self::query()->where('code', $code->value)->value('id');

        if (! is_numeric($id)) {
            throw new RuntimeException("Structural role {$code->value} is missing.");
        }

        return (int) $id;
    }
}
