<?php

namespace Tests\Unit\Normalization;

use App\Services\Normalization\PhoneNormalizer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PhoneNormalizerTest extends TestCase
{
    public function test_canonicalizes_national_and_plus_57_mobile_formats(): void
    {
        $normalizer = new PhoneNormalizer;

        $this->assertSame('+573001234567', $normalizer->normalize('300 123 4567'));
        $this->assertSame('+573001234567', $normalizer->normalize('+57 300-123-4567'));
    }

    public function test_rejects_fixed_international_length_and_invalid_content(): void
    {
        $normalizer = new PhoneNormalizer;

        foreach (['6011234567', '+51 987654321', '+58 3001234567', '300123456', 'phone3001234567'] as $invalid) {
            try {
                $normalizer->normalize($invalid);
                $this->fail("Expected invalid phone {$invalid} to be rejected.");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_can_allow_peru_mobile_numbers_with_explicit_country_code(): void
    {
        $normalizer = new PhoneNormalizer(['57', '51']);

        $this->assertSame('+51987654321', $normalizer->normalize('+51 987 654 321'));
        $this->assertSame('+573001234567', $normalizer->normalize('300 123 4567'));
    }

    public function test_rejects_invalid_peru_mobile_number_even_when_country_is_allowed(): void
    {
        $normalizer = new PhoneNormalizer(['57', '51']);

        $this->expectException(InvalidArgumentException::class);

        $normalizer->normalize('+51 187 654 321');
    }
}
