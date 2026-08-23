<?php

namespace App\Actions\Otp;

use App\DTOs\Otp\IssueOtpChallengeData;
use App\DTOs\Otp\OtpEmissionResult;
use App\DTOs\Otp\StartPublicOtpChallengeData;
use App\Enums\OtpPurpose;
use App\Exceptions\OtpChallengeException;
use App\Services\Normalization\IdentityNormalizer;
use App\Services\Normalization\PhoneNormalizer;
use App\Services\Otp\OtpRateLimiter;

final readonly class StartPublicOtpChallengeAction
{
    public function __construct(
        private IssueOtpChallengeAction $issueOtpChallenge,
        private IdentityNormalizer $identityNormalizer,
        private PhoneNormalizer $phoneNormalizer,
        private OtpRateLimiter $rateLimiter,
    ) {}

    public function execute(StartPublicOtpChallengeData $data): OtpEmissionResult
    {
        if (! in_array($data->purpose, [OtpPurpose::CREATE_MOTO, OtpPurpose::CREATE_CREDIT], true)) {
            throw OtpChallengeException::invalidPurpose();
        }

        $identity = $this->identityNormalizer->normalize($data->identity);
        $phone = $this->phoneNormalizer->normalize($data->phone);
        $this->rateLimiter->hitPublicInit($identity, $phone, $data->ip);

        $payload = array_merge($data->payload, [
            'identity' => $identity,
            'phone' => $phone,
            'purpose' => $data->purpose->value,
        ]);

        return $this->issueOtpChallenge->execute(new IssueOtpChallengeData(
            purpose: $data->purpose,
            destination: $phone,
            payload: $payload,
        ));
    }
}
