<?php

namespace App\Services\Sms;

use App\Enums\SmsPurpose;

interface SmsGateway
{
    public function send(string $destination, string $message, SmsPurpose $purpose): SmsGatewayResult;
}
