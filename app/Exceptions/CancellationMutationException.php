<?php

namespace App\Exceptions;

use RuntimeException;

final class CancellationMutationException extends RuntimeException
{
    public static function staleVersion(): self
    {
        return new self('STALE_VERSION');
    }

    public static function terminal(): self
    {
        return new self('TERMINAL');
    }

    public static function invalidData(string $message = 'INVALID_DATA'): self
    {
        return new self($message);
    }
}
