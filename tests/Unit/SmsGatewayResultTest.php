<?php

namespace Tests\Unit;

use App\Enums\SmsAttemptStatus;
use App\Services\Sms\SmsGatewayResult;
use PHPUnit\Framework\TestCase;

final class SmsGatewayResultTest extends TestCase
{
    public function test_sent_result(): void
    {
        $result = SmsGatewayResult::sent('provider-1');

        $this->assertSame(SmsAttemptStatus::SENT, $result->status);
        $this->assertSame('provider-1', $result->providerReference);
    }

    public function test_failed_result(): void
    {
        $result = SmsGatewayResult::failed('SAFE_CODE', 'safe message');

        $this->assertSame(SmsAttemptStatus::FAILED, $result->status);
        $this->assertSame('SAFE_CODE', $result->safeErrorCode);
        $this->assertSame('safe message', $result->safeErrorMessage);
    }

    public function test_unknown_result(): void
    {
        $result = SmsGatewayResult::unknown('TIMEOUT');

        $this->assertSame(SmsAttemptStatus::UNKNOWN, $result->status);
        $this->assertSame('TIMEOUT', $result->safeErrorCode);
    }
}
