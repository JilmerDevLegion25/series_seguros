<?php

namespace App\DTOs\Exports;

final readonly class CancellationExportResult
{
    public function __construct(
        public string $storedPath,
        public string $absolutePath,
        public string $filename,
        public int $rowCount,
    ) {}
}
