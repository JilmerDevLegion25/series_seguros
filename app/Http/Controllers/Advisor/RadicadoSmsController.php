<?php

namespace App\Http\Controllers\Advisor;

use App\Actions\Sms\RetryRadicadoSmsAction;
use App\Exceptions\SmsRetryException;
use App\Models\CreditCancellation;
use App\Models\MotoCancellation;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class RadicadoSmsController
{
    /**
     * @throws AuthorizationException
     */
    public function retryMoto(
        Request $request,
        MotoCancellation $moto,
        RetryRadicadoSmsAction $retryRadicadoSms,
    ): RedirectResponse {
        try {
            $retryRadicadoSms->retryMoto(
                $moto,
                $this->advisor($request->user()),
                (string) $request->attributes->get('request_id', ''),
            );
        } catch (SmsRetryException $exception) {
            return back()->withErrors($this->retryErrors($exception));
        }

        return back()->with('status', 'Reintento SMS Moto registrado.');
    }

    /**
     * @throws AuthorizationException
     */
    public function retryCredit(
        Request $request,
        CreditCancellation $credit,
        RetryRadicadoSmsAction $retryRadicadoSms,
    ): RedirectResponse {
        try {
            $retryRadicadoSms->retryCredit(
                $credit,
                $this->advisor($request->user()),
                (string) $request->attributes->get('request_id', ''),
            );
        } catch (SmsRetryException $exception) {
            return back()->withErrors($this->retryErrors($exception));
        }

        return back()->with('status', 'Reintento SMS Credit registrado.');
    }

    /**
     * @throws AuthorizationException
     */
    private function advisor(?User $user): User
    {
        if (! $user instanceof User) {
            throw new AuthorizationException;
        }

        return $user;
    }

    /**
     * @return array<string, string>
     */
    private function retryErrors(SmsRetryException $exception): array
    {
        return match ($exception->getMessage()) {
            'SMS_RETRY_RATE_LIMITED' => ['sms_retry' => 'Demasiados reintentos en este momento.'],
            'SMS_RETRY_LIMIT_EXCEEDED' => ['sms_retry' => 'Se alcanzo el limite de reintentos SMS.'],
            'SMS_RETRY_COOLDOWN' => ['sms_retry' => 'Debes esperar antes de reintentar este SMS.'],
            default => ['sms_retry' => 'No fue posible reintentar el SMS.'],
        };
    }
}
