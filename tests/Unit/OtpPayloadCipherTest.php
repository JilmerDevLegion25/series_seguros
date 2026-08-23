<?php

namespace Tests\Unit;

use App\Services\Otp\OtpPayloadCipher;
use Illuminate\Contracts\Encryption\DecryptException;
use Tests\TestCase;

final class OtpPayloadCipherTest extends TestCase
{
    public function test_payload_encrypts_and_decrypts(): void
    {
        $cipher = new OtpPayloadCipher(app('encrypter'));
        $payload = ['identity' => '123', 'phone' => '3000000000'];

        $encrypted = $cipher->encrypt($payload);

        $this->assertSame($payload, $cipher->decrypt($encrypted));
    }

    public function test_plaintext_payload_is_not_visible(): void
    {
        $cipher = new OtpPayloadCipher(app('encrypter'));

        $encrypted = $cipher->encrypt(['identity' => '123']);

        $this->assertStringNotContainsString('123', $encrypted);
        $this->assertStringNotContainsString('identity', $encrypted);
    }

    public function test_tamper_detection(): void
    {
        $cipher = new OtpPayloadCipher(app('encrypter'));
        $encrypted = $cipher->encrypt(['identity' => '123']);

        $this->expectException(DecryptException::class);

        $cipher->decrypt($encrypted.'tampered');
    }
}
