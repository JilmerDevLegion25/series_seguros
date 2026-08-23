<?php

namespace App\Services\Otp;

use InvalidArgumentException;
use SensitiveParameter;

final readonly class OtpMac
{
    public function __construct(
        #[SensitiveParameter]
        private string $key,
    ) {
        if ($this->key === '') {
            throw new InvalidArgumentException('OTP MAC key must not be empty.');
        }
    }

    public function make(string $otp, string $context): string
    {
        return hash_hmac('sha256', $context."\0".$otp, $this->key);
    }

    public function matches(string $otp, string $context, string $expectedMac): bool
    {
        return hash_equals($expectedMac, $this->make($otp, $context));
    }
}
