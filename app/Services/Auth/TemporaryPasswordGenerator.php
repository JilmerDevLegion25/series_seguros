<?php

namespace App\Services\Auth;

final readonly class TemporaryPasswordGenerator
{
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!*-.';

    public function __construct(
        private int $length = 20,
    ) {}

    public function generate(): string
    {
        $password = '';
        $max = strlen(self::ALPHABET) - 1;

        for ($index = 0; $index < $this->length; $index++) {
            $password .= self::ALPHABET[random_int(0, $max)];
        }

        return $password;
    }
}
