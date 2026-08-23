<?php

namespace App\DTOs\Auth;

final readonly class CreateAdvisorData
{
    public function __construct(
        public string $username,
        public string $name,
        public ?string $email = null,
        public ?string $phone = null,
    ) {}
}
