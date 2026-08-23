<?php

namespace App\DTOs\Cancellations\Credit;

final readonly class UpdateCreditCancellationData
{
    /**
     * @param  array<string, bool|string|null>  $fields
     */
    public function __construct(
        public int $expectedVersion,
        public string $reason,
        public array $fields,
    ) {}
}
