<?php

namespace Tests\Unit;

use App\Enums\SmsAttemptStatus;
use App\Enums\SmsPurpose;
use App\Services\Clock\SystemClock;
use App\Services\Sms\FakeSmsGateway;
use PHPUnit\Framework\TestCase;

final class FakeSmsGatewayTest extends TestCase
{
    public function test_fake_gateway_returns_sent_result_with_reference(): void
    {
        $gateway = new FakeSmsGateway(new SystemClock);

        $result = $gateway->send('3000000000', 'mensaje', SmsPurpose::OTP);

        $this->assertSame(SmsAttemptStatus::SENT, $result->status);
        $this->assertNotNull($result->providerReference);
        $this->assertStringStartsWith('fake-', $result->providerReference);
    }
}
