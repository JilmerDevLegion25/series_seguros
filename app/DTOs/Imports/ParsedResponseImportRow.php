<?php

namespace App\DTOs\Imports;

use App\Enums\ImportRowReason;
use Carbon\CarbonImmutable;

final readonly class ParsedResponseImportRow
{
    public function __construct(
        public int $rowNumber,
        public ?int $radicado,
        public ?CarbonImmutable $cancellationDate,
        public ?string $observation,
        public ?ImportRowReason $rejectionReason,
    ) {}

    public function withRejection(ImportRowReason $reason): self
    {
        return new self(
            rowNumber: $this->rowNumber,
            radicado: $this->radicado,
            cancellationDate: $this->cancellationDate,
            observation: $this->observation,
            rejectionReason: $reason,
        );
    }

    public function isEligible(): bool
    {
        return $this->rejectionReason === null
            && $this->radicado !== null
            && $this->cancellationDate !== null
            && $this->observation !== null;
    }
}
