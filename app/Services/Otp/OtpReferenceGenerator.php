<?php

namespace App\Services\Otp;

final readonly class OtpReferenceGenerator
{
    public function generate(): string
    {
        return bin2hex(random_bytes(32));
    }
}
