<?php

namespace App\Actions\Cancellations\Credit;

use App\Actions\Otp\IssueOtpChallengeAction;
use App\DTOs\Cancellations\Credit\CreateCreditCancellationData;
use App\DTOs\Otp\IssueOtpChallengeData;
use App\DTOs\Otp\OtpEmissionResult;
use App\Enums\CancellationOrigin;
use App\Enums\OtpPurpose;
use App\Enums\PermissionKey;
use App\Models\User;
use App\Services\Otp\OtpRateLimiter;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class StartCreditCancellationOtpAction
{
    public function __construct(
        private IssueOtpChallengeAction $issueOtpChallenge,
        private OtpRateLimiter $rateLimiter,
    ) {}

    public function execute(CreateCreditCancellationData $data, CancellationOrigin $origin, string $ip, ?User $advisor = null): OtpEmissionResult
    {
        $createdByUserId = null;

        if ($origin === CancellationOrigin::ADVISOR) {
            if (! $advisor instanceof User || ! $advisor->isAdvisor() || ! $advisor->can(PermissionKey::CANCELLATIONS_CREATE->value)) {
                throw new AuthorizationException;
            }

            $createdByUserId = $advisor->id;
        }

        $this->rateLimiter->hitPublicInit($data->holderCedula, $data->holderPhone, $ip);

        return $this->issueOtpChallenge->execute(new IssueOtpChallengeData(
            purpose: OtpPurpose::CREATE_CREDIT,
            destination: $data->holderPhone,
            payload: $data->toPayload($origin, $createdByUserId),
        ));
    }
}
