<?php

namespace App\DTOs\Otp;

final readonly class CompletePasswordRecoveryData
{
    public function __construct(
        public string $publicReference,
        public string $otp,
        public string $password,
        public string $ip,
    ) {}
}
