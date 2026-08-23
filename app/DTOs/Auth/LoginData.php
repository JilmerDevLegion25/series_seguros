<?php

namespace App\DTOs\Auth;

final readonly class LoginData
{
    public function __construct(
        public string $username,
        public string $password,
    ) {}
}
