<?php

namespace App\Actions\Otp;

use App\DTOs\Otp\OtpEmissionResult;
use App\Enums\SmsPurpose;
use App\Exceptions\OtpChallengeException;
use App\Models\OtpChallenge;
use App\Services\Clock\Clock;
use App\Services\Otp\OtpCodeGenerator;
use App\Services\Otp\OtpMac;
use App\Services\Otp\OtpMessageFactory;
use App\Services\Otp\OtpRateLimiter;
use App\Services\Sms\SmsGateway;
use Illuminate\Support\Facades\DB;

final readonly class ResendOtpChallengeAction
{
    public function __construct(
        private OtpCodeGenerator $otpCodeGenerator,
        private OtpMac $otpMac,
        private OtpMessageFactory $messageFactory,
        private OtpRateLimiter $rateLimiter,
        private SmsGateway $smsGateway,
        private Clock $clock,
    ) {}

    public function execute(string $publicReference, string $ip): OtpEmissionResult
    {
        $this->rateLimiter->hitTechnical($ip);
        $otp = $this->otpCodeGenerator->generate();

        $challenge = DB::transaction(function () use ($publicReference, $otp): OtpChallenge {
            $challenge = $this->findLocked($publicReference);
            $now = $this->clock->now();

            if ($challenge->isTerminal() || $challenge->isExpired($now)) {
                throw OtpChallengeException::notFoundOrUnavailable();
            }

            if ($challenge->emission_count >= (int) config('otp.max_emissions')) {
                throw OtpChallengeException::emissionLimitReached();
            }

            $cooldownUntil = $challenge->last_emitted_at?->addSeconds((int) config('otp.resend_cooldown_seconds'));
            if ($cooldownUntil !== null && $now->lessThan($cooldownUntil)) {
                throw OtpChallengeException::cooldownActive();
            }

            $emissionCount = $challenge->emission_count + 1;
            $challenge->forceFill([
                'otp_mac' => $this->otpMac->make($otp, $this->macContext($challenge, $emissionCount)),
                'emission_count' => $emissionCount,
                'last_emitted_at' => $now,
                'expires_at' => $now->addSeconds((int) config('otp.ttl_seconds')),
            ])->save();

            return $challenge;
        });

        $deliveryResult = $this->smsGateway->send(
            $challenge->destination_snapshot,
            $this->messageFactory->make($otp, $challenge->purpose),
            SmsPurpose::OTP,
        );

        return new OtpEmissionResult($challenge, $deliveryResult);
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

    private function macContext(OtpChallenge $challenge, int $emissionCount): string
    {
        return $challenge->purpose->value.'|'.$challenge->public_reference.'|'.$emissionCount;
    }
}
