<?php

namespace App\Http\Controllers\Otp;

use App\Actions\Otp\ResendOtpChallengeAction;
use App\Actions\Otp\VerifyOtpChallengeAction;
use App\Exceptions\OtpChallengeException;
use App\Http\Requests\Otp\VerifyOtpRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class OtpController
{
    public function resend(
        Request $request,
        string $publicReference,
        ResendOtpChallengeAction $resendOtpChallenge,
    ): RedirectResponse {
        try {
            $resendOtpChallenge->execute($publicReference, (string) $request->ip());
        } catch (OtpChallengeException) {
            return back()->withErrors(['otp' => 'No fue posible reenviar el codigo.']);
        }

        return back()->with('status', 'Codigo reenviado.');
    }

    public function verify(
        VerifyOtpRequest $request,
        string $publicReference,
        VerifyOtpChallengeAction $verifyOtpChallenge,
    ): Response {
        try {
            $result = $verifyOtpChallenge->execute(
                $publicReference,
                (string) $request->string('otp'),
                (string) $request->ip(),
            );
        } catch (OtpChallengeException) {
            return new Response(status: 404);
        }

        return new Response(status: $result->valid ? 204 : 422);
    }
}
