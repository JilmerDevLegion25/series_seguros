<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\ChangePasswordAction;
use App\DTOs\Auth\ChangePasswordData;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

final class PasswordController
{
    public function edit(): View
    {
        return view('auth.change-password');
    }

    public function update(ChangePasswordRequest $request, ChangePasswordAction $changePassword): RedirectResponse
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            abort(403);
        }

        $changePassword->execute(new ChangePasswordData(
            user: $user,
            newPassword: (string) $request->string('password'),
        ));

        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }
}
