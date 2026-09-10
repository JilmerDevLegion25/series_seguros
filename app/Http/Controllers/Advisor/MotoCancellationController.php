<?php

namespace App\Http\Controllers\Advisor;

use App\Actions\Cancellations\Moto\CompleteMotoCancellationAction;
use App\Actions\Cancellations\Moto\GenerateMotoRadicadoAction;
use App\Actions\Cancellations\Moto\ReassignMotoCancellationAction;
use App\Actions\Cancellations\Moto\StartMotoCancellationOtpAction;
use App\Actions\Cancellations\Moto\UpdateMotoCancellationAction;
use App\Enums\CancellationOrigin;
use App\Enums\MotoCancellationReason;
use App\Enums\MotoInformationSource;
use App\Enums\RoleCode;
use App\Enums\UserStatus;
use App\Exceptions\CancellationMutationException;
use App\Exceptions\OtpChallengeException;
use App\Http\Requests\Cancellations\ReassignCancellationAdvisorRequest;
use App\Http\Requests\Moto\CompleteMotoCancellationRequest;
use App\Http\Requests\Moto\StoreMotoCancellationRequest;
use App\Http\Requests\Moto\UpdateMotoCancellationRequest;
use App\Models\MotoCancellation;
use App\Models\Role;
use App\Models\User;
use App\Services\Normalization\IdentityNormalizer;
use App\Services\Normalization\PhoneNormalizer;
use App\Services\Normalization\PlateNormalizer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class MotoCancellationController
{
    public function create(): View
    {
        return view('moto.create', [
            'action' => route('advisor.moto.store'),
            'reasons' => MotoCancellationReason::labels(),
            'sources' => MotoInformationSource::labels(),
            'title' => 'Crear Moto',
        ]);
    }

    public function edit(MotoCancellation $moto): View
    {
        return view('moto.edit', [
            'moto' => $moto,
            'reasons' => MotoCancellationReason::labels(),
            'sources' => MotoInformationSource::labels(),
        ]);
    }

    public function update(
        UpdateMotoCancellationRequest $request,
        MotoCancellation $moto,
        UpdateMotoCancellationAction $updateMotoCancellation,
        PhoneNormalizer $phoneNormalizer,
        PlateNormalizer $plateNormalizer,
        IdentityNormalizer $identityNormalizer,
    ): RedirectResponse {
        try {
            $result = $updateMotoCancellation->execute(
                $moto,
                $request->toData($phoneNormalizer, $plateNormalizer, $identityNormalizer),
                $this->advisor($request->user()),
                (string) $request->attributes->get('request_id', ''),
            );
        } catch (CancellationMutationException $exception) {
            return back()
                ->withInput()
                ->withErrors($this->mutationErrors($exception));
        }

        return redirect()
            ->route('advisor.moto.edit', $result->cancellation)
            ->with('status', $result->changed ? 'Cancelacion Moto actualizada.' : 'No hubo cambios.');
    }

    public function reassign(MotoCancellation $moto): View
    {
        return view('moto.reassign', [
            'moto' => $moto,
            'advisors' => $this->activeAdvisors(),
        ]);
    }

    public function storeReassignment(
        ReassignCancellationAdvisorRequest $request,
        MotoCancellation $moto,
        ReassignMotoCancellationAction $reassignMotoCancellation,
    ): RedirectResponse {
        try {
            $result = $reassignMotoCancellation->execute(
                $moto,
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
            ->route('advisor.moto.edit', $result->cancellation)
            ->with('status', $result->changed ? 'Advisor Moto reasignado.' : 'No hubo cambios.');
    }

    /**
     * @throws AuthorizationException
     */
    public function generateRadicado(
        Request $request,
        MotoCancellation $moto,
        GenerateMotoRadicadoAction $generateMotoRadicado,
    ): RedirectResponse {
        try {
            $radicated = $generateMotoRadicado->execute(
                $moto,
                $this->advisor($request->user()),
                (string) $request->attributes->get('request_id', ''),
            );
        } catch (CancellationMutationException $exception) {
            return back()->withErrors($this->mutationErrors($exception));
        }

        return redirect()
            ->route('advisor.moto.edit', $radicated)
            ->with('status', 'Radicado Moto generado.');
    }

    /**
     * @throws AuthorizationException
     */
    public function store(
        StoreMotoCancellationRequest $request,
        StartMotoCancellationOtpAction $startMotoCancellationOtp,
        IdentityNormalizer $identityNormalizer,
        PhoneNormalizer $phoneNormalizer,
        PlateNormalizer $plateNormalizer,
    ): View {
        $advisor = $this->advisor($request->user());
        $data = $request->toData($identityNormalizer, $phoneNormalizer, $plateNormalizer);
        $result = $startMotoCancellationOtp->execute(
            $data,
            CancellationOrigin::ADVISOR,
            (string) $request->ip(),
            $advisor,
        );

        return view('moto.otp', [
            'challenge' => $result->challenge,
            'completeRoute' => route('advisor.moto.complete'),
            'resendRoute' => route('otp.resend', $result->challenge->public_reference),
        ]);
    }

    /**
     * @throws AuthorizationException
     */
    public function complete(
        CompleteMotoCancellationRequest $request,
        CompleteMotoCancellationAction $completeMotoCancellation,
    ): View|RedirectResponse {
        try {
            $result = $completeMotoCancellation->execute(
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

        if (! $result->completed || $result->moto === null) {
            return back()->withErrors(['otp' => 'No fue posible validar el codigo.']);
        }

        return view('moto.success', [
            'moto' => $result->moto,
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
            'MOTO_LIEN_REQUIRED' => ['radicado' => 'La radicacion manual solo aplica para Moto con prenda vigente.'],
            'RADICADO_ALREADY_EXISTS' => ['radicado' => 'La solicitud ya tiene radicado.'],
            'PENDING_RADICACION_REQUIRED' => ['status' => 'La solicitud no esta pendiente de radicacion.'],
            'CREDIT_OWNER_REQUIRED' => ['credit_owner_cedula' => 'Debe informar el dueno del credito cuando el titular no lo es.'],
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
