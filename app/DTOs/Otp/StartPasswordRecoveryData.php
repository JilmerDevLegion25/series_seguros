<?php

namespace App\DTOs\Otp;

final readonly class StartPasswordRecoveryData
{
    public function __construct(
        public string $username,
        public string $ip,
    ) {}
}
