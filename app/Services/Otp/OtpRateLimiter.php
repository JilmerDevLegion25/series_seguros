<?php

namespace App\Services\Otp;

use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

final readonly class OtpRateLimiter
{
    private const WINDOW_30_MINUTES = 1800;

    private const WINDOW_1_MINUTE = 60;

    public function hitPublicInit(string $identity, string $phone, string $ip): void
    {
        $this->hitOrFail('otp:public:identity:'.sha1($identity), 5, self::WINDOW_30_MINUTES);
        $this->hitOrFail('otp:public:phone:'.sha1($phone), 5, self::WINDOW_30_MINUTES);
        $this->hitOrFail('otp:public:ip:'.sha1($ip), 20, self::WINDOW_30_MINUTES);
    }

    public function hitRecoveryInit(string $target, string $ip): void
    {
        $this->hitOrFail('otp:recovery:target:'.sha1($target), 3, self::WINDOW_30_MINUTES);
        $this->hitOrFail('otp:recovery:ip:'.sha1($ip), 10, self::WINDOW_30_MINUTES);
    }

    public function hitTechnical(string $ip): void
    {
        $this->hitOrFail('otp:technical:ip:'.sha1($ip), 30, self::WINDOW_1_MINUTE);
    }

    private function hitOrFail(string $key, int $maxAttempts, int $decaySeconds): void
    {
        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            throw new TooManyRequestsHttpException(null, 'Too many OTP requests.');
        }

        RateLimiter::hit($key, $decaySeconds);
    }
}
