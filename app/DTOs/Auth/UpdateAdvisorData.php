<?php

namespace App\DTOs\Auth;

use App\Enums\UserStatus;

final readonly class UpdateAdvisorData
{
    public function __construct(
        public string $name,
        public ?string $email,
        public ?string $phone,
        public UserStatus $status,
    ) {}
}
