<?php

namespace App\DTOs\Clients;

final readonly class ResolveClientData
{
    public function __construct(
        public string $identity,
        public string $name,
        public string $email,
        public string $phone,
    ) {}
}
