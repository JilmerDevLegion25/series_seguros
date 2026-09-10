<?php

namespace App\Actions\Sms;

use App\Enums\AuditEventType;
use App\Enums\CancellationType;
use App\Enums\PermissionKey;
use App\Enums\SmsAttemptStatus;
use App\Enums\SmsPurpose;
use App\Exceptions\SmsRetryException;
use App\Models\Audit;
use App\Models\CreditCancellation;
use App\Models\MotoCancellation;
use App\Models\SmsAttempt;
use App\Models\User;
use App\Services\Sms\RadicadoSmsMessageFactory;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

final readonly class RetryRadicadoSmsAction
{
    public function __construct(
        private DeliverSmsAttemptAction $deliverSmsAttempt,
        private RadicadoSmsMessageFactory $messageFactory,
    ) {}

    /**
     * @throws AuthorizationException
     * @throws SmsRetryException
     */
    public function retryMoto(MotoCancellation $moto, User $actor, ?string $requestId = null): SmsAttempt
    {
        $this->assertAdvisorCanRetry($actor);
        $this->hitEndpointLimiter($actor);

        [$attempt, $message] = DB::transaction(function () use ($moto, $actor, $requestId): array {
            /** @var MotoCancellation $lockedMoto */
            $lockedMoto = MotoCancellation::query()
                ->whereKey($moto->id)
                ->lockForUpdate()
                ->firstOrFail();

            $attempt = $this->reserveMotoAttempt($lockedMoto, $actor, $requestId);

            return [$attempt, $this->messageFactory->makeForMoto($lockedMoto)];
        });

        return $this->deliverSmsAttempt->execute($attempt, $message);
    }

    /**
     * @throws AuthorizationException
     * @throws SmsRetryException
     */
    public function retryCredit(CreditCancellation $credit, User $actor, ?string $requestId = null): SmsAttempt
    {
        $this->assertAdvisorCanRetry($actor);
        $this->hitEndpointLimiter($actor);

        [$attempt, $message] = DB::transaction(function () use ($credit, $actor, $requestId): array {
            /** @var CreditCancellation $lockedCredit */
            $lockedCredit = CreditCancellation::query()
                ->whereKey($credit->id)
                ->lockForUpdate()
                ->firstOrFail();

            $attempt = $this->reserveCreditAttempt($lockedCredit, $actor, $requestId);

            return [$attempt, $this->messageFactory->makeForCredit($lockedCredit)];
        });

        return $this->deliverSmsAttempt->execute($attempt, $message);
    }

    /**
     * @throws AuthorizationException
     */
    private function assertAdvisorCanRetry(User $actor): void
    {
        if (! $actor->isAdvisor() || ! $actor->can(PermissionKey::RADICADO_SMS_RETRY->value)) {
            throw new AuthorizationException;
        }
    }

    /**
     * @throws SmsRetryException
     */
    private function hitEndpointLimiter(User $actor): void
    {
        $key = 'radicado-sms-retry:user:'.$actor->id;

        if (RateLimiter::tooManyAttempts($key, 10)) {
            throw SmsRetryException::rateLimited();
        }

        RateLimiter::hit($key, 60);
    }

    /**
     * @throws SmsRetryException
     */
    private function reserveMotoAttempt(MotoCancellation $moto, User $actor, ?string $requestId): SmsAttempt
    {
        if ($moto->radicado === null) {
            throw SmsRetryException::unavailable();
        }

        $this->assertRetryQuota(
            SmsAttempt::query()->where('moto_cancellation_id', $moto->id),
        );

        $attempt = SmsAttempt::query()->create([
            'moto_cancellation_id' => $moto->id,
            'purpose' => SmsPurpose::RADICADO,
            'status' => SmsAttemptStatus::PENDING,
            'is_manual_retry' => true,
            'destination' => $moto->holder_phone,
        ]);

        $this->recordAudit(
            actor: $actor,
            requestId: $requestId,
            type: CancellationType::MOTO,
            radicado: (int) $moto->radicado,
            smsAttempt: $attempt,
            moto: $moto,
        );

        return $attempt;
    }

    /**
     * @throws SmsRetryException
     */
    private function reserveCreditAttempt(CreditCancellation $credit, User $actor, ?string $requestId): SmsAttempt
    {
        $this->assertRetryQuota(
            SmsAttempt::query()->where('credit_cancellation_id', $credit->id),
        );

        $attempt = SmsAttempt::query()->create([
            'credit_cancellation_id' => $credit->id,
            'purpose' => SmsPurpose::RADICADO,
            'status' => SmsAttemptStatus::PENDING,
            'is_manual_retry' => true,
            'destination' => $credit->holder_phone,
        ]);

        $this->recordAudit(
            actor: $actor,
            requestId: $requestId,
            type: CancellationType::CREDIT,
            radicado: $credit->radicado,
            smsAttempt: $attempt,
            credit: $credit,
        );

        return $attempt;
    }

    /**
     * @param  Builder<SmsAttempt>  $baseQuery
     *
     * @throws SmsRetryException
     */
    private function assertRetryQuota(Builder $baseQuery): void
    {
        $windowStart = now()->subHours((int) config('sms.radicado_retry.window_hours'));
        $manualRetryQuery = (clone $baseQuery)
            ->where('purpose', SmsPurpose::RADICADO)
            ->where('is_manual_retry', true)
            ->where('created_at', '>=', $windowStart);

        if ($manualRetryQuery->count() >= (int) config('sms.radicado_retry.max_attempts')) {
            throw SmsRetryException::limitExceeded();
        }

        /** @var SmsAttempt|null $latestManualRetry */
        $latestManualRetry = (clone $manualRetryQuery)
            ->orderByDesc('created_at')
            ->first();

        if (
            $latestManualRetry instanceof SmsAttempt
            && $latestManualRetry->created_at !== null
            && $latestManualRetry->created_at->greaterThan(now()->subSeconds((int) config('sms.radicado_retry.cooldown_seconds')))
        ) {
            throw SmsRetryException::cooldown();
        }
    }

    private function recordAudit(
        User $actor,
        ?string $requestId,
        CancellationType $type,
        int $radicado,
        SmsAttempt $smsAttempt,
        ?MotoCancellation $moto = null,
        ?CreditCancellation $credit = null,
    ): void {
        Audit::query()->create([
            'moto_cancellation_id' => $moto?->id,
            'credit_cancellation_id' => $credit?->id,
            'actor_user_id' => $actor->id,
            'event_type' => AuditEventType::RADICADO_SMS_RETRY_REQUESTED,
            'request_id' => $requestId,
            'metadata' => [
                'type' => $type->value,
                'radicado' => $radicado,
                'sms_attempt_id' => $smsAttempt->id,
                'purpose' => SmsPurpose::RADICADO->value,
                'destination_snapshot' => $this->maskDestination($smsAttempt->destination),
            ],
        ]);
    }

    private function maskDestination(string $destination): string
    {
        if (strlen($destination) <= 2) {
            return str_repeat('*', strlen($destination));
        }

        return str_repeat('*', strlen($destination) - 2).substr($destination, -2);
    }
}
