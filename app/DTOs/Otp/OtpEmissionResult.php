<?php

namespace App\DTOs\Otp;

use App\Models\OtpChallenge;
use App\Services\Sms\SmsGatewayResult;

final readonly class OtpEmissionResult
{
    public function __construct(
        public OtpChallenge $challenge,
        public ?SmsGatewayResult $deliveryResult,
    ) {}
}
