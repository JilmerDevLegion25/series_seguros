<?php

namespace App\DTOs\Otp;

final readonly class PasswordRecoveryStartResult
{
    public const GENERIC_MESSAGE = 'Si los datos corresponden a una cuenta activa, enviaremos un codigo de recuperacion.';

    public function __construct(
        public string $publicReference,
        public string $message = self::GENERIC_MESSAGE,
    ) {}
}
