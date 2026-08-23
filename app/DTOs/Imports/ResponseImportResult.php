<?php

namespace App\DTOs\Imports;

use App\Models\ImportBatch;

final readonly class ResponseImportResult
{
    public function __construct(
        public ImportBatch $batch,
    ) {}
}
