<?php

namespace App\Services\Normalization;

use InvalidArgumentException;

final class IdentityNormalizer
{
    public function normalize(string $value): string
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            throw new InvalidArgumentException('Identity is required.');
        }

        if (! preg_match('/^[0-9.\-\s]+$/', $trimmed)) {
            throw new InvalidArgumentException('Identity contains invalid characters.');
        }

        $canonical = preg_replace('/[^0-9]/', '', $trimmed);

        if (! is_string($canonical) || $canonical === '') {
            throw new InvalidArgumentException('Identity is required.');
        }

        if (strlen($canonical) > 10) {
            throw new InvalidArgumentException('Identity must have at most 10 digits.');
        }

        return $canonical;
    }
}
