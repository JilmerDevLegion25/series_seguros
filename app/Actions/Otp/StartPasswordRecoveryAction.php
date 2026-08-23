<?php

namespace App\Actions\Otp;

use App\DTOs\Otp\IssueOtpChallengeData;
use App\DTOs\Otp\PasswordRecoveryStartResult;
use App\DTOs\Otp\StartPasswordRecoveryData;
use App\Enums\OtpPurpose;
use App\Enums\UserStatus;
use App\Models\User;
use App\Services\Normalization\UsernameNormalizer;
use App\Services\Otp\OtpRateLimiter;
use App\Services\Otp\OtpReferenceGenerator;
use InvalidArgumentException;

final readonly class StartPasswordRecoveryAction
{
    public function __construct(
        private IssueOtpChallengeAction $issueOtpChallenge,
        private OtpRateLimiter $rateLimiter,
        private OtpReferenceGenerator $referenceGenerator,
        private UsernameNormalizer $usernameNormalizer,
    ) {}

    public function execute(StartPasswordRecoveryData $data): PasswordRecoveryStartResult
    {
        $target = $this->normalizeTarget($data->username);
        $this->rateLimiter->hitRecoveryInit($target, $data->ip);

        $user = User::query()
            ->where('username', $target)
            ->where('status', UserStatus::ACTIVE)
            ->first();

        if (! $user instanceof User || $user->phone === null) {
            return new PasswordRecoveryStartResult($this->referenceGenerator->generate());
        }

        $result = $this->issueOtpChallenge->execute(new IssueOtpChallengeData(
            purpose: OtpPurpose::PASSWORD_RECOVERY,
            destination: $user->phone,
            targetUser: $user,
        ));

        return new PasswordRecoveryStartResult($result->challenge->public_reference);
    }

    private function normalizeTarget(string $username): string
    {
        try {
            return $this->usernameNormalizer->normalizeLogin($username);
        } catch (InvalidArgumentException) {
            return strtoupper(trim($username));
        }
    }
}
