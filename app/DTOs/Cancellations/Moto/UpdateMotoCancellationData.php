<?php

namespace App\DTOs\Cancellations\Moto;

final readonly class UpdateMotoCancellationData
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
