<?php

namespace Tests\Unit;

use App\Services\Otp\OtpCodeGenerator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class OtpCodeGeneratorTest extends TestCase
{
    public function test_generated_code_has_six_digits(): void
    {
        $code = (new OtpCodeGenerator)->generate();

        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
    }

    public function test_leading_zeros_are_possible_and_preserved(): void
    {
        $code = (new OtpCodeGenerator)->format(42);

        $this->assertSame('000042', $code);
    }

    public function test_value_must_be_in_valid_range(): void
    {
        $generator = new OtpCodeGenerator;

        $this->assertSame('000000', $generator->format(0));
        $this->assertSame('999999', $generator->format(999999));

        $this->expectException(InvalidArgumentException::class);

        $generator->format(1000000);
    }
}
