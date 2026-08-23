<?php

namespace App\Actions\Otp;

use App\Exceptions\OtpChallengeException;
use App\Models\OtpChallenge;
use App\Services\Clock\Clock;
use Illuminate\Support\Facades\DB;

final readonly class InvalidateOtpChallengeAction
{
    public function __construct(
        private Clock $clock,
    ) {}

    public function execute(string $publicReference, string $reason): OtpChallenge
    {
        return DB::transaction(function () use ($publicReference, $reason): OtpChallenge {
            /** @var OtpChallenge|null $challenge */
            $challenge = OtpChallenge::query()
                ->where('public_reference', $publicReference)
                ->lockForUpdate()
                ->first();

            if (! $challenge instanceof OtpChallenge || $challenge->isTerminal()) {
                throw OtpChallengeException::notFoundOrUnavailable();
            }

            $challenge->forceFill([
                'invalidated_at' => $this->clock->now(),
                'invalidation_reason' => $reason,
                'encrypted_payload' => null,
            ])->save();

            return $challenge;
        });
    }
}
