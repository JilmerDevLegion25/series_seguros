<?php

namespace App\Actions\Cancellations\Moto;

use App\Actions\Otp\DecryptOtpPayloadAction;
use App\Actions\Otp\VerifyOtpChallengeAction;
use App\Actions\Sms\DeliverSmsAttemptAction;
use App\DTOs\Cancellations\Moto\CreateMotoCancellationData;
use App\DTOs\Cancellations\Moto\MotoCreationResult;
use App\DTOs\Clients\ResolveClientData;
use App\Enums\ActivityType;
use App\Enums\AuditEventType;
use App\Enums\CancellationOrigin;
use App\Enums\CancellationStatus;
use App\Enums\CancellationType;
use App\Enums\OtpPurpose;
use App\Enums\PermissionKey;
use App\Enums\SmsAttemptStatus;
use App\Enums\SmsPurpose;
use App\Exceptions\OtpChallengeException;
use App\Models\Activity;
use App\Models\Audit;
use App\Models\MotoCancellation;
use App\Models\OtpChallenge;
use App\Models\SmsAttempt;
use App\Models\User;
use App\Services\Clients\ClientResolver;
use App\Services\Otp\OtpMac;
use App\Services\Otp\OtpRateLimiter;
use App\Services\Radicado\RadicadoAllocator;
use App\Services\Sms\RadicadoSmsMessageFactory;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final readonly class CompleteMotoCancellationAction
{
    public function __construct(
        private VerifyOtpChallengeAction $verifyOtpChallenge,
        private DecryptOtpPayloadAction $decryptOtpPayload,
        private ClientResolver $clientResolver,
        private RadicadoAllocator $radicadoAllocator,
        private DeliverSmsAttemptAction $deliverSmsAttempt,
        private RadicadoSmsMessageFactory $messageFactory,
        private OtpRateLimiter $rateLimiter,
        private OtpMac $otpMac,
    ) {}

    public function execute(
        string $publicReference,
        string $otp,
        string $ip,
        ?User $currentUser,
        CancellationOrigin $expectedOrigin,
        ?string $requestId = null,
    ): MotoCreationResult {
        $this->rateLimiter->hitTechnical($ip);

        $result = DB::transaction(function () use ($publicReference, $otp, $currentUser, $expectedOrigin, $requestId): MotoCreationResult {
            $challenge = $this->findLocked($publicReference);

            if ($challenge->purpose !== OtpPurpose::CREATE_MOTO) {
                return MotoCreationResult::failed('INVALID_PURPOSE');
            }

            if ($challenge->isConsumed()) {
                $existingMoto = $this->existingMotoForChallenge($challenge);

                if (
                    $existingMoto instanceof MotoCancellation
                    && $existingMoto->origin === $expectedOrigin
                    && $this->otpMatches($challenge, $otp)
                ) {
                    return MotoCreationResult::completed($existingMoto, created: false);
                }

                return MotoCreationResult::failed('TERMINAL');
            }

            $verification = $this->verifyOtpChallenge->verifyLocked($challenge, $otp, consume: false);

            if (! $verification->valid) {
                return MotoCreationResult::failed($verification->reason);
            }

            $payload = $this->decryptOtpPayload->execute($challenge);
            $origin = CreateMotoCancellationData::originFromPayload($payload);

            if ($origin !== $expectedOrigin) {
                return MotoCreationResult::failed('ORIGIN_MISMATCH');
            }

            $data = CreateMotoCancellationData::fromPayload($payload);
            $advisorCreator = $origin === CancellationOrigin::ADVISOR
                ? $this->resolveAdvisorCreator($payload, $currentUser)
                : null;
            $owner = $this->clientResolver->resolve(new ResolveClientData(
                identity: $data->holderCedula,
                name: $data->holderName,
                email: $data->holderEmail,
                phone: $data->holderPhone,
            ));
            $creator = $advisorCreator ?? $owner;
            $radicado = $this->radicadoAllocator->allocate(CancellationType::MOTO);

            $moto = MotoCancellation::query()->create([
                'otp_challenge_id' => $challenge->id,
                'radicado' => $radicado,
                'owner_user_id' => $owner->id,
                'created_by_user_id' => $creator->id,
                'assigned_advisor_user_id' => $advisorCreator?->id,
                'origin' => $origin,
                'status' => CancellationStatus::EN_GESTION,
                'version' => 1,
                'holder_name' => $data->holderName,
                'holder_cedula' => $data->holderCedula,
                'property_lien_adeinco' => $data->propertyLienAdeinco,
                'plate' => $data->plate,
                'holder_phone' => $data->holderPhone,
                'holder_email' => $data->holderEmail,
                'cancellation_reason' => $data->cancellationReason,
                'cancellation_information_source' => $data->cancellationInformationSource,
                'is_credit_holder' => $data->isCreditHolder,
                'credit_owner_name' => $data->creditOwnerName,
                'credit_owner_cedula' => $data->creditOwnerCedula,
                'ownership_declaration_accepted' => $data->ownershipDeclarationAccepted,
                'data_processing_accepted' => $data->dataProcessingAccepted,
            ]);

            Activity::query()->create([
                'moto_cancellation_id' => $moto->id,
                'actor_user_id' => $creator->id,
                'type' => ActivityType::CREATED,
                'metadata' => [
                    'event' => AuditEventType::CANCELLATION_CREATED->value,
                    'radicado' => $moto->radicado,
                    'origin' => $origin->value,
                ],
            ]);

            Audit::query()->create([
                'moto_cancellation_id' => $moto->id,
                'actor_user_id' => $creator->id,
                'event_type' => AuditEventType::CANCELLATION_CREATED,
                'request_id' => $requestId,
                'metadata' => [
                    'type' => CancellationType::MOTO->value,
                    'radicado' => $moto->radicado,
                    'origin' => $origin->value,
                ],
            ]);

            $smsAttempt = SmsAttempt::query()->create([
                'moto_cancellation_id' => $moto->id,
                'purpose' => SmsPurpose::RADICADO,
                'status' => SmsAttemptStatus::PENDING,
                'destination' => $moto->holder_phone,
            ]);

            $challenge->forceFill([
                'consumed_at' => now(),
                'encrypted_payload' => null,
            ])->save();

            return MotoCreationResult::completed($moto, created: true, smsAttempt: $smsAttempt);
        });

        if ($result->completed && $result->created && $result->moto instanceof MotoCancellation && $result->smsAttempt instanceof SmsAttempt) {
            $this->deliverSmsAttempt->execute(
                $result->smsAttempt,
                $this->messageFactory->makeForMoto($result->moto),
            );
        }

        return $result;
    }

    private function findLocked(string $publicReference): OtpChallenge
    {
        /** @var OtpChallenge|null $challenge */
        $challenge = OtpChallenge::query()
            ->where('public_reference', $publicReference)
            ->lockForUpdate()
            ->first();

        if (! $challenge instanceof OtpChallenge) {
            throw OtpChallengeException::notFoundOrUnavailable();
        }

        return $challenge;
    }

    private function existingMotoForChallenge(OtpChallenge $challenge): ?MotoCancellation
    {
        /** @var MotoCancellation|null $moto */
        $moto = MotoCancellation::query()
            ->where('otp_challenge_id', $challenge->id)
            ->first();

        return $moto;
    }

    private function otpMatches(OtpChallenge $challenge, string $otp): bool
    {
        return $this->otpMac->matches(
            $otp,
            $challenge->purpose->value.'|'.$challenge->public_reference.'|'.$challenge->emission_count,
            $challenge->otp_mac,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveAdvisorCreator(array $payload, ?User $currentUser): User
    {
        $createdByUserId = CreateMotoCancellationData::createdByUserIdFromPayload($payload);

        if (
            ! $currentUser instanceof User
            || $createdByUserId !== $currentUser->id
            || ! $currentUser->isAdvisor()
            || ! $currentUser->can(PermissionKey::CANCELLATIONS_CREATE->value)
        ) {
            throw new AuthorizationException;
        }

        return $currentUser;
    }
}
