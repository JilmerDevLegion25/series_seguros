<?php

namespace App\Services\Normalization;

use InvalidArgumentException;

final class PhoneNormalizer
{
    /**
     * @param  list<string>  $allowedCountryCodes
     */
    public function __construct(
        private readonly array $allowedCountryCodes = ['57'],
    ) {}

    public function normalize(string $value): string
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            throw new InvalidArgumentException('Phone is required.');
        }

        if (! preg_match('/^\+?[0-9\s().-]+$/', $trimmed)) {
            throw new InvalidArgumentException('Phone contains invalid characters.');
        }

        $compact = preg_replace('/[\s().-]+/', '', $trimmed);

        if (! is_string($compact)) {
            throw new InvalidArgumentException('Phone is invalid.');
        }

        if (str_starts_with($compact, '+')) {
            return $this->normalizeInternational(substr($compact, 1));
        }

        if (! $this->allowsCountryCode('57')) {
            throw new InvalidArgumentException('Phone must include an allowed country code.');
        }

        return '+57'.$this->validNationalNumberOrFail('57', $compact);
    }

    private function normalizeInternational(string $digits): string
    {
        foreach ($this->allowedCountryCodes() as $countryCode) {
            if (! str_starts_with($digits, $countryCode)) {
                continue;
            }

            $national = substr($digits, strlen($countryCode));

            return '+'.$countryCode.$this->validNationalNumberOrFail($countryCode, $national);
        }

        throw new InvalidArgumentException('Phone country code is not allowed.');
    }

    /**
     * @return list<string>
     */
    private function allowedCountryCodes(): array
    {
        $codes = [];

        foreach ($this->allowedCountryCodes as $countryCode) {
            $code = trim($countryCode);

            if (preg_match('/^[1-9][0-9]{0,2}$/', $code) === 1) {
                $codes[] = $code;
            }
        }

        usort($codes, static fn (string $left, string $right): int => strlen($right) <=> strlen($left));

        return array_values(array_unique($codes));
    }

    private function allowsCountryCode(string $countryCode): bool
    {
        return in_array($countryCode, $this->allowedCountryCodes(), true);
    }

    private function validNationalNumberOrFail(string $countryCode, string $national): string
    {
        $valid = match ($countryCode) {
            '51' => preg_match('/^9[0-9]{8}$/', $national) === 1,
            '57' => preg_match('/^3[0-9]{9}$/', $national) === 1,
            default => preg_match('/^[1-9][0-9]{6,14}$/', $national) === 1,
        };

        if (! $valid) {
            throw new InvalidArgumentException('Phone must be a valid mobile number for the selected country.');
        }

        return $national;
    }
}
