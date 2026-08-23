<?php

namespace App\Services\Otp;

use InvalidArgumentException;

final readonly class OtpCodeGenerator
{
    public function __construct(
        private int $length = 6,
    ) {
        if ($this->length !== 6) {
            throw new InvalidArgumentException('Phase 00 OTP length must be 6 digits.');
        }
    }

    public function generate(): string
    {
        return $this->format(random_int(0, 999999));
    }

    public function format(int $value): string
    {
        if ($value < 0 || $value > 999999) {
            throw new InvalidArgumentException('OTP value must fit in 6 digits.');
        }

        return str_pad((string) $value, $this->length, '0', STR_PAD_LEFT);
    }
}
