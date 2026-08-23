<?php

namespace App\Actions\Auth;

use App\DTOs\Auth\ChangePasswordData;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Support\Facades\Hash;

final readonly class ChangePasswordAction
{
    public function __construct(
        private StatefulGuard $guard,
    ) {}

    public function execute(ChangePasswordData $data): void
    {
        $data->user->forceFill([
            'password' => Hash::make($data->newPassword),
            'must_change_password' => false,
        ])->save();

        $this->guard->setUser($data->user);
    }
}
