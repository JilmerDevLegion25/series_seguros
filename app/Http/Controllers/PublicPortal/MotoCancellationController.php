<?php

namespace App\Http\Controllers\PublicPortal;

use App\Actions\Cancellations\Moto\CompleteMotoCancellationAction;
use App\Actions\Cancellations\Moto\StartMotoCancellationOtpAction;
use App\Enums\CancellationOrigin;
use App\Enums\MotoCancellationReason;
use App\Enums\MotoInformationSource;
use App\Exceptions\OtpChallengeException;
use App\Http\Requests\Moto\CompleteMotoCancellationRequest;
use App\Http\Requests\Moto\StoreMotoCancellationRequest;
use App\Services\Normalization\IdentityNormalizer;
use App\Services\Normalization\PhoneNormalizer;
use App\Services\Normalization\PlateNormalizer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

final class MotoCancellationController
{
    public function create(): View
    {
        return $this->formView(route('public.moto.store'));
    }

    public function store(
        StoreMotoCancellationRequest $request,
        StartMotoCancellationOtpAction $startMotoCancellationOtp,
        IdentityNormalizer $identityNormalizer,
        PhoneNormalizer $phoneNormalizer,
        PlateNormalizer $plateNormalizer,
    ): View {
        $data = $request->toData($identityNormalizer, $phoneNormalizer, $plateNormalizer);
        $result = $startMotoCancellationOtp->execute(
            $data,
            CancellationOrigin::PUBLIC,
            (string) $request->ip(),
        );

        return view('moto.otp', [
            'challenge' => $result->challenge,
            'completeRoute' => route('public.moto.complete'),
            'resendRoute' => route('otp.resend', $result->challenge->public_reference),
        ]);
    }

    public function complete(
        CompleteMotoCancellationRequest $request,
        CompleteMotoCancellationAction $completeMotoCancellation,
    ): View|RedirectResponse {
        try {
            $result = $completeMotoCancellation->execute(
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

        if (! $result->completed || $result->moto === null) {
            return back()->withErrors(['otp' => 'No fue posible validar el codigo.']);
        }

        return view('moto.success', [
            'moto' => $result->moto,
            'alreadyCompleted' => ! $result->created,
        ]);
    }

    private function formView(string $action): View
    {
        return view('moto.create', [
            'action' => $action,
            'reasons' => MotoCancellationReason::labels(),
            'sources' => MotoInformationSource::labels(),
            'title' => 'Cancelacion Moto',
        ]);
    }
}
