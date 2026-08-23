<?php

namespace Tests\Unit\Auth;

use App\Services\Auth\TemporaryPasswordGenerator;
use PHPUnit\Framework\TestCase;

final class TemporaryPasswordGeneratorTest extends TestCase
{
    public function test_generates_secure_temporary_password_shape(): void
    {
        $generator = new TemporaryPasswordGenerator;

        $first = $generator->generate();
        $second = $generator->generate();

        $this->assertSame(20, strlen($first));
        $this->assertNotSame($first, $second);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9!*.\-]+$/', $first);
    }
}
