<?php

namespace App\Http\Controllers\Advisor;

use App\Actions\Cancellations\Credit\CompleteCreditCancellationAction;
use App\Actions\Cancellations\Credit\ReassignCreditCancellationAction;
use App\Actions\Cancellations\Credit\StartCreditCancellationOtpAction;
use App\Actions\Cancellations\Credit\UpdateCreditCancellationAction;
use App\Enums\CancellationOrigin;
use App\Enums\MotoCancellationReason;
use App\Enums\MotoInformationSource;
use App\Enums\RoleCode;
use App\Enums\UserStatus;
use App\Exceptions\CancellationMutationException;
use App\Exceptions\OtpChallengeException;
use App\Http\Requests\Cancellations\ReassignCancellationAdvisorRequest;
use App\Http\Requests\Credit\CompleteCreditCancellationRequest;
use App\Http\Requests\Credit\StoreCreditCancellationRequest;
use App\Http\Requests\Credit\UpdateCreditCancellationRequest;
use App\Models\CreditCancellation;
use App\Models\Role;
use App\Models\User;
use App\Services\Normalization\CreditNumberNormalizer;
use App\Services\Normalization\IdentityNormalizer;
use App\Services\Normalization\PhoneNormalizer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;

final class CreditCancellationController
{
    public function create(): View
    {
        return view('credit.create', [
            'action' => route('advisor.credit.store'),
            'reasons' => MotoCancellationReason::labels(),
            'sources' => MotoInformationSource::labels(),
            'title' => 'Crear Credito',
        ]);
    }

    public function edit(CreditCancellation $credit): View
    {
        return view('credit.edit', [
            'credit' => $credit,
            'reasons' => MotoCancellationReason::labels(),
            'sources' => MotoInformationSource::labels(),
        ]);
    }

    public function update(
        UpdateCreditCancellationRequest $request,
        CreditCancellation $credit,
        UpdateCreditCancellationAction $updateCreditCancellation,
        PhoneNormalizer $phoneNormalizer,
        CreditNumberNormalizer $creditNumberNormalizer,
    ): RedirectResponse {
        try {
            $result = $updateCreditCancellation->execute(
                $credit,
                $request->toData($phoneNormalizer, $creditNumberNormalizer),
                $this->advisor($request->user()),
                (string) $request->attributes->get('request_id', ''),
            );
        } catch (CancellationMutationException $exception) {
            return back()
                ->withInput()
                ->withErrors($this->mutationErrors($exception));
        }

        return redirect()
            ->route('advisor.credit.edit', $result->cancellation)
            ->with('status', $result->changed ? 'Cancelacion Credit actualizada.' : 'No hubo cambios.');
    }

    public function reassign(CreditCancellation $credit): View
    {
        return view('credit.reassign', [
            'credit' => $credit,
            'advisors' => $this->activeAdvisors(),
        ]);
    }

    public function storeReassignment(
        ReassignCancellationAdvisorRequest $request,
        CreditCancellation $credit,
        ReassignCreditCancellationAction $reassignCreditCancellation,
    ): RedirectResponse {
        try {
            $result = $reassignCreditCancellation->execute(
                $credit,
                $request->toData(),
                $this->advisor($request->user()),
                (string) $request->attributes->get('request_id', ''),
            );
        } catch (CancellationMutationException $exception) {
            return back()
                ->withInput()
                ->withErrors($this->mutationErrors($exception));
        }

        return redirect()
            ->route('advisor.credit.edit', $result->cancellation)
            ->with('status', $result->changed ? 'Advisor Credit reasignado.' : 'No hubo cambios.');
    }

    /**
     * @throws AuthorizationException
     */
    public function store(
        StoreCreditCancellationRequest $request,
        StartCreditCancellationOtpAction $startCreditCancellationOtp,
        IdentityNormalizer $identityNormalizer,
        PhoneNormalizer $phoneNormalizer,
        CreditNumberNormalizer $creditNumberNormalizer,
    ): View {
        $advisor = $this->advisor($request->user());
        $data = $request->toData($identityNormalizer, $phoneNormalizer, $creditNumberNormalizer);
        $result = $startCreditCancellationOtp->execute(
            $data,
            CancellationOrigin::ADVISOR,
            (string) $request->ip(),
            $advisor,
        );

        return view('credit.otp', [
            'challenge' => $result->challenge,
            'completeRoute' => route('advisor.credit.complete'),
            'resendRoute' => route('otp.resend', $result->challenge->public_reference),
        ]);
    }

    /**
     * @throws AuthorizationException
     */
    public function complete(
        CompleteCreditCancellationRequest $request,
        CompleteCreditCancellationAction $completeCreditCancellation,
    ): View|RedirectResponse {
        try {
            $result = $completeCreditCancellation->execute(
                (string) $request->string('challenge_reference'),
                (string) $request->string('otp'),
                (string) $request->ip(),
                $this->advisor($request->user()),
                CancellationOrigin::ADVISOR,
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
    private function mutationErrors(CancellationMutationException $exception): array
    {
        return match ($exception->getMessage()) {
            'STALE_VERSION' => ['expected_version' => 'La solicitud fue modificada por otra operacion. Recarga e intenta de nuevo.'],
            'TERMINAL' => ['status' => 'La solicitud ya esta en estado terminal y no puede modificarse.'],
            'CREDIT_INSURANCE_REQUIRED' => ['cancel_personal_accidents' => 'Debe seleccionar al menos un seguro a cancelar.'],
            'ASSIGNED_ADVISOR_NOT_FOUND' => ['assigned_advisor_user_id' => 'El Advisor asignado no esta disponible.'],
            default => ['mutation' => 'No fue posible aplicar el cambio.'],
        };
    }

    /**
     * @return Collection<int, User>
     */
    private function activeAdvisors()
    {
        return User::query()
            ->where('role_id', Role::idFor(RoleCode::ADVISOR))
            ->where('status', UserStatus::ACTIVE->value)
            ->orderBy('username')
            ->get();
    }
}
