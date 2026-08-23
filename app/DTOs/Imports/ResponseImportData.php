<?php

namespace App\DTOs\Imports;

use App\Enums\CancellationType;

final readonly class ResponseImportData
{
    public function __construct(
        public CancellationType $type,
        public string $storedPath,
    ) {}
}
