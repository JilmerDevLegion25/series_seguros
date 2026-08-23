<?php

namespace App\Services\Otp;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\StringEncrypter;
use JsonException;
use RuntimeException;

final readonly class OtpPayloadCipher
{
    public function __construct(
        private StringEncrypter $encrypter,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws JsonException
     */
    public function encrypt(array $payload): string
    {
        return $this->encrypter->encryptString(json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, mixed>
     *
     * @throws DecryptException
     * @throws JsonException
     */
    public function decrypt(string $ciphertext): array
    {
        $decoded = json_decode($this->encrypter->decryptString($ciphertext), true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            throw new RuntimeException('OTP payload must decrypt to an array.');
        }

        return $decoded;
    }
}
