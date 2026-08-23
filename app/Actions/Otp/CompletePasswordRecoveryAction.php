<?php

namespace App\Actions\Otp;

use App\Actions\AdvisorAccounts\InvalidateUserSessionsAction;
use App\DTOs\Otp\CompletePasswordRecoveryData;
use App\DTOs\Otp\OtpVerificationResult;
use App\Enums\OtpPurpose;
use App\Exceptions\OtpChallengeException;
use App\Models\OtpChallenge;
use App\Models\User;
use App\Services\Otp\OtpRateLimiter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final readonly class CompletePasswordRecoveryAction
{
    public function __construct(
        private VerifyOtpChallengeAction $verifyOtpChallenge,
        private InvalidateUserSessionsAction $invalidateUserSessions,
        private OtpRateLimiter $rateLimiter,
    ) {}

    public function execute(CompletePasswordRecoveryData $data): OtpVerificationResult
    {
        $this->rateLimiter->hitTechnical($data->ip);

        return DB::transaction(function () use ($data): OtpVerificationResult {
            /** @var OtpChallenge|null $challenge */
            $challenge = OtpChallenge::query()
                ->where('public_reference', $data->publicReference)
                ->where('purpose', OtpPurpose::PASSWORD_RECOVERY)
                ->lockForUpdate()
                ->first();

            if (! $challenge instanceof OtpChallenge || $challenge->target_user_id === null) {
                throw OtpChallengeException::notFoundOrUnavailable();
            }

            /** @var User|null $user */
            $user = User::query()
                ->whereKey($challenge->target_user_id)
                ->lockForUpdate()
                ->first();

            if (! $user instanceof User) {
                throw OtpChallengeException::notFoundOrUnavailable();
            }

            $result = $this->verifyOtpChallenge->verifyLocked($challenge, $data->otp, consume: true);

            if (! $result->valid) {
                return $result;
            }

            $user->forceFill([
                'password' => Hash::make($data->password),
                'must_change_password' => false,
            ])->save();

            $this->invalidateUserSessions->execute($user);

            return $result;
        });
    }
}
