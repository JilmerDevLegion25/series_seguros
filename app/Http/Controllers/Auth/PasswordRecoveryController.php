<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Otp\CompletePasswordRecoveryAction;
use App\Actions\Otp\StartPasswordRecoveryAction;
use App\DTOs\Otp\CompletePasswordRecoveryData;
use App\DTOs\Otp\StartPasswordRecoveryData;
use App\Exceptions\OtpChallengeException;
use App\Http\Requests\Auth\CompletePasswordRecoveryRequest;
use App\Http\Requests\Auth\StartPasswordRecoveryRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

final class PasswordRecoveryController
{
    public function create(): View
    {
        return view('auth.recovery-start');
    }

    public function store(
        StartPasswordRecoveryRequest $request,
        StartPasswordRecoveryAction $startPasswordRecovery,
    ): View {
        $result = $startPasswordRecovery->execute(new StartPasswordRecoveryData(
            username: (string) $request->string('username'),
            ip: (string) $request->ip(),
        ));

        return view('auth.recovery-reset', [
            'publicReference' => $result->publicReference,
            'status' => $result->message,
        ]);
    }

    public function update(
        CompletePasswordRecoveryRequest $request,
        CompletePasswordRecoveryAction $completePasswordRecovery,
    ): RedirectResponse {
        try {
            $result = $completePasswordRecovery->execute(new CompletePasswordRecoveryData(
                publicReference: (string) $request->string('public_reference'),
                otp: (string) $request->string('otp'),
                password: (string) $request->string('password'),
                ip: (string) $request->ip(),
            ));
        } catch (OtpChallengeException) {
            return back()->withErrors(['otp' => 'Codigo invalido o expirado.']);
        }

        if (! $result->valid) {
            return back()->withErrors(['otp' => 'Codigo invalido o expirado.']);
        }

        return redirect()
            ->route('login')
            ->with('status', 'Contrasena actualizada. Ingresa con tu nueva contrasena.');
    }
}
