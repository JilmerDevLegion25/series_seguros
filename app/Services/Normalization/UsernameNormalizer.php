<?php

namespace App\Services\Normalization;

use InvalidArgumentException;

final readonly class UsernameNormalizer
{
    public function __construct(
        private IdentityNormalizer $identityNormalizer,
    ) {}

    public function normalizeLogin(string $value): string
    {
        $trimmed = trim($value);

        try {
            return $this->identityNormalizer->normalize($trimmed);
        } catch (InvalidArgumentException) {
            return $this->normalizeAdvisor($trimmed);
        }
    }

    public function normalizeAdvisor(string $value): string
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            throw new InvalidArgumentException('Username is required.');
        }

        // if (! preg_match('/^[A-Za-z0-9]+$/', $trimmed)) {
        //     throw new InvalidArgumentException('Advisor username must be alphanumeric.');
        // }

        if (strlen($trimmed) > 64) {
            throw new InvalidArgumentException('Advisor username must have at most 64 characters.');
        }

        return strtoupper($trimmed);
    }
}
