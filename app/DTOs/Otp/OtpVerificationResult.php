<?php

namespace App\DTOs\Otp;

use App\Models\OtpChallenge;

final readonly class OtpVerificationResult
{
    public function __construct(
        public bool $valid,
        public OtpChallenge $challenge,
        public string $reason = 'OK',
    ) {}
}
