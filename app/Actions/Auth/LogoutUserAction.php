<?php

namespace App\Actions\Auth;

use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;

final readonly class LogoutUserAction
{
    public function __construct(
        private StatefulGuard $guard,
    ) {}

    public function execute(Request $request): void
    {
        $this->guard->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
