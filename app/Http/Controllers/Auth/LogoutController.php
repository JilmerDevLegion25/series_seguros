<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\LogoutUserAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class LogoutController
{
    public function __invoke(Request $request, LogoutUserAction $logoutUser): RedirectResponse
    {
        $logoutUser->execute($request);

        return redirect()->route('login');
    }
}
