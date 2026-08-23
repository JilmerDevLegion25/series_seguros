<?php

namespace Tests\Unit;

use App\Services\Otp\OtpMac;
use PHPUnit\Framework\TestCase;

final class OtpMacTest extends TestCase
{
    public function test_mac_is_deterministic_for_same_context_key_and_input(): void
    {
        $mac = new OtpMac('test-key');

        $first = $mac->make('123456', 'challenge-1');
        $second = $mac->make('123456', 'challenge-1');

        $this->assertSame($first, $second);
    }

    public function test_mac_changes_with_otp_or_context(): void
    {
        $mac = new OtpMac('test-key');

        $base = $mac->make('123456', 'challenge-1');

        $this->assertNotSame($base, $mac->make('654321', 'challenge-1'));
        $this->assertNotSame($base, $mac->make('123456', 'challenge-2'));
    }

    public function test_safe_matching_abstraction(): void
    {
        $mac = new OtpMac('test-key');
        $expected = $mac->make('123456', 'challenge-1');

        $this->assertTrue($mac->matches('123456', 'challenge-1', $expected));
        $this->assertFalse($mac->matches('123456', 'challenge-2', $expected));
    }

    public function test_plaintext_otp_is_not_exposed_in_mac(): void
    {
        $otp = '123456';
        $stored = (new OtpMac('test-key'))->make($otp, 'challenge-1');

        $this->assertStringNotContainsString($otp, $stored);
    }
}
