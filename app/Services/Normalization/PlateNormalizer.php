<?php

namespace App\Services\Normalization;

use InvalidArgumentException;

final class PlateNormalizer
{
    public function normalize(string $value): string
    {
        $canonical = strtoupper(trim($value));

        if ($canonical === '') {
            throw new InvalidArgumentException('Plate is required.');
        }

        if (preg_match('/^[A-Z0-9]{6}$/', $canonical) !== 1) {
            throw new InvalidArgumentException('Plate must have exactly 6 alphanumeric characters.');
        }

        return $canonical;
    }
}
