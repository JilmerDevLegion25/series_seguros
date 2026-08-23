<?php

namespace App\Actions\Otp;

use App\DTOs\Otp\OtpVerificationResult;
use App\Exceptions\OtpChallengeException;
use App\Models\OtpChallenge;
use App\Services\Clock\Clock;
use App\Services\Otp\OtpMac;
use App\Services\Otp\OtpRateLimiter;
use Illuminate\Support\Facades\DB;

final readonly class VerifyOtpChallengeAction
{
    public function __construct(
        private OtpMac $otpMac,
        private OtpRateLimiter $rateLimiter,
        private Clock $clock,
    ) {}

    public function execute(string $publicReference, string $otp, string $ip): OtpVerificationResult
    {
        $this->rateLimiter->hitTechnical($ip);

        return DB::transaction(function () use ($publicReference, $otp): OtpVerificationResult {
            $challenge = $this->findLocked($publicReference);

            return $this->verifyLocked($challenge, $otp, consume: false);
        });
    }

    public function verifyLocked(OtpChallenge $challenge, string $otp, bool $consume): OtpVerificationResult
    {
        $now = $this->clock->now();

        if ($challenge->isTerminal()) {
            return new OtpVerificationResult(false, $challenge, 'TERMINAL');
        }

        if ($challenge->isExpired($now)) {
            return new OtpVerificationResult(false, $challenge, 'EXPIRED');
        }

        if ($challenge->failed_attempts >= (int) config('otp.max_failures')) {
            $this->invalidateLocked($challenge, 'MAX_FAILURES');

            return new OtpVerificationResult(false, $challenge, 'MAX_FAILURES');
        }

        $matches = $this->otpMac->matches($otp, $this->macContext($challenge), $challenge->otp_mac);

        if (! $matches) {
            $failedAttempts = $challenge->failed_attempts + 1;
            $updates = ['failed_attempts' => $failedAttempts];

            if ($failedAttempts >= (int) config('otp.max_failures')) {
                $updates['invalidated_at'] = $now;
                $updates['invalidation_reason'] = 'MAX_FAILURES';
                $updates['encrypted_payload'] = null;
            }

            $challenge->forceFill($updates)->save();

            return new OtpVerificationResult(false, $challenge, 'INVALID');
        }

        if ($consume) {
            $challenge->forceFill([
                'consumed_at' => $now,
                'encrypted_payload' => null,
            ])->save();
        }

        return new OtpVerificationResult(true, $challenge);
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

    private function invalidateLocked(OtpChallenge $challenge, string $reason): void
    {
        $challenge->forceFill([
            'invalidated_at' => $this->clock->now(),
            'invalidation_reason' => $reason,
            'encrypted_payload' => null,
        ])->save();
    }

    private function macContext(OtpChallenge $challenge): string
    {
        return $challenge->purpose->value.'|'.$challenge->public_reference.'|'.$challenge->emission_count;
    }
}
