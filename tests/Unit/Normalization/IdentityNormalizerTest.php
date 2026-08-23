<?php

namespace Tests\Unit\Normalization;

use App\Services\Normalization\IdentityNormalizer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class IdentityNormalizerTest extends TestCase
{
    public function test_canonicalizes_digits_and_allowed_separators(): void
    {
        $normalizer = new IdentityNormalizer;

        $this->assertSame('1234567890', $normalizer->normalize('1.234 567-890'));
    }

    public function test_rejects_arbitrary_content(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new IdentityNormalizer)->normalize('ABC123');
    }

    public function test_rejects_more_than_ten_digits(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new IdentityNormalizer)->normalize('12345678901');
    }
}
