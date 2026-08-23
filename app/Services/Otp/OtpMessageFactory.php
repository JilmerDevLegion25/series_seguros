<?php

namespace App\Services\Otp;

use App\Enums\OtpPurpose;

final readonly class OtpMessageFactory
{
    public function make(string $otp, OtpPurpose $purpose): string
    {
        return "{$otp} es su codigo de validacion Series Seguros";
    }
}
