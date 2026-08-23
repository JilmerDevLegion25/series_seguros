<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\AuthenticateUserAction;
use App\DTOs\Auth\LoginData;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

final class LoginController
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request, AuthenticateUserAction $authenticateUser): RedirectResponse
    {
        $user = $authenticateUser->execute(
            new LoginData(
                username: (string) $request->string('username'),
                password: (string) $request->string('password'),
            ),
            $request,
        );

        $request->session()->forget('url.intended');

        if ($user->must_change_password) {
            return redirect()->route('password.change');
        }

        return redirect()->to(route('dashboard', absolute: false));
    }
}
