<?php

namespace App\Models;

use App\Enums\PermissionKey;
use App\Enums\RoleCode;
use App\Enums\UserStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property int $role_id
 * @property string $username
 * @property string|null $identity
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property string $password
 * @property UserStatus $status
 * @property bool $must_change_password
 * @property Role|null $role
 */
final class User extends Authenticatable
{
    protected $fillable = [
        'role_id',
        'username',
        'identity',
        'name',
        'email',
        'phone',
        'password',
        'status',
        'must_change_password',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'status' => UserStatus::class,
            'must_change_password' => 'bool',
            'password' => 'hashed',
        ];
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * @return HasMany<Notification, $this>
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::ACTIVE;
    }

    public function isClient(): bool
    {
        return $this->role?->code === RoleCode::CLIENT->value;
    }

    public function isAdvisor(): bool
    {
        return $this->role?->code === RoleCode::ADVISOR->value;
    }

    public function hasPermission(PermissionKey $permissionKey): bool
    {
        if ($this->status !== UserStatus::ACTIVE) {
            return false;
        }

        return DB::table('role_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('role_permissions.role_id', $this->role_id)
            ->where('permissions.key', $permissionKey->value)
            ->exists();
    }
}
