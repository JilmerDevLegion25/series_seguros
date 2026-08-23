<?php

namespace App\DTOs\Auth;

use App\Models\User;

final readonly class ChangePasswordData
{
    public function __construct(
        public User $user,
        public string $newPassword,
    ) {}
}
