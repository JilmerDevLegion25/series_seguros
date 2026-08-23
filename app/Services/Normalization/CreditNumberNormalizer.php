<?php

namespace App\Services\Normalization;

use InvalidArgumentException;

final class CreditNumberNormalizer
{
    public function normalize(string $value): string
    {
        $canonical = trim($value);

        if ($canonical === '') {
            throw new InvalidArgumentException('Credit number is required.');
        }

        if (strlen($canonical) > 50) {
            throw new InvalidArgumentException('Credit number must have at most 50 characters.');
        }

        return $canonical;
    }
}
