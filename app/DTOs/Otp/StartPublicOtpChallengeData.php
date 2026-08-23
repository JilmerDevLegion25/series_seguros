<?php

namespace App\DTOs\Otp;

use App\Enums\OtpPurpose;

final readonly class StartPublicOtpChallengeData
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public OtpPurpose $purpose,
        public string $identity,
        public string $phone,
        public array $payload,
        public string $ip,
    ) {}
}
