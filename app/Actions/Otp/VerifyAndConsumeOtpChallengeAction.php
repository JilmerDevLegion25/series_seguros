<?php

namespace App\Actions\Otp;

use App\DTOs\Otp\OtpVerificationResult;
use App\Exceptions\OtpChallengeException;
use App\Models\OtpChallenge;
use App\Services\Otp\OtpRateLimiter;
use Illuminate\Support\Facades\DB;

final readonly class VerifyAndConsumeOtpChallengeAction
{
    public function __construct(
        private VerifyOtpChallengeAction $verifyOtpChallenge,
        private OtpRateLimiter $rateLimiter,
    ) {}

    public function execute(string $publicReference, string $otp, string $ip): OtpVerificationResult
    {
        $this->rateLimiter->hitTechnical($ip);

        return DB::transaction(function () use ($publicReference, $otp): OtpVerificationResult {
            /** @var OtpChallenge|null $challenge */
            $challenge = OtpChallenge::query()
                ->where('public_reference', $publicReference)
                ->lockForUpdate()
                ->first();

            if (! $challenge instanceof OtpChallenge) {
                throw OtpChallengeException::notFoundOrUnavailable();
            }

            return $this->verifyOtpChallenge->verifyLocked($challenge, $otp, consume: true);
        });
    }
}
