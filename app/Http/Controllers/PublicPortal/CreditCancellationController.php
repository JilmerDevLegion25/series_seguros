<?php

namespace App\Http\Controllers\PublicPortal;

use App\Actions\Cancellations\Credit\CompleteCreditCancellationAction;
use App\Actions\Cancellations\Credit\StartCreditCancellationOtpAction;
use App\Enums\CancellationOrigin;
use App\Enums\MotoCancellationReason;
use App\Enums\MotoInformationSource;
use App\Exceptions\OtpChallengeException;
use App\Http\Requests\Credit\CompleteCreditCancellationRequest;
use App\Http\Requests\Credit\StoreCreditCancellationRequest;
use App\Services\Normalization\CreditNumberNormalizer;
use App\Services\Normalization\IdentityNormalizer;
use App\Services\Normalization\PhoneNormalizer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

final class CreditCancellationController
{
    public function create(): View
    {
        return $this->formView(route('public.credit.store'));
    }

    public function store(
        StoreCreditCancellationRequest $request,
        StartCreditCancellationOtpAction $startCreditCancellationOtp,
        IdentityNormalizer $identityNormalizer,
        PhoneNormalizer $phoneNormalizer,
        CreditNumberNormalizer $creditNumberNormalizer,
    ): View {
        $data = $request->toData($identityNormalizer, $phoneNormalizer, $creditNumberNormalizer);
        $result = $startCreditCancellationOtp->execute(
            $data,
            CancellationOrigin::PUBLIC,
            (string) $request->ip(),
        );

        return view('credit.otp', [
            'challenge' => $result->challenge,
            'completeRoute' => route('public.credit.complete'),
            'resendRoute' => route('otp.resend', $result->challenge->public_reference),
        ]);
    }

    public function complete(
        CompleteCreditCancellationRequest $request,
        CompleteCreditCancellationAction $completeCreditCancellation,
    ): View|RedirectResponse {
        try {
            $result = $completeCreditCancellation->execute(
                (string) $request->string('challenge_reference'),
                (string) $request->string('otp'),
                (string) $request->ip(),
                null,
                CancellationOrigin::PUBLIC,
                (string) $request->attributes->get('request_id', ''),
            );
        } catch (OtpChallengeException) {
            return back()->withErrors(['otp' => 'No fue posible validar el codigo.']);
        }

        if (! $result->completed || $result->credit === null) {
            return back()->withErrors(['otp' => 'No fue posible validar el codigo.']);
        }

        return view('credit.success', [
            'credit' => $result->credit,
            'alreadyCompleted' => ! $result->created,
        ]);
    }

    private function formView(string $action): View
    {
        return view('credit.create', [
            'action' => $action,
            'reasons' => MotoCancellationReason::labels(),
            'sources' => MotoInformationSource::labels(),
            'title' => 'Cancelacion Credito',
        ]);
    }
}
