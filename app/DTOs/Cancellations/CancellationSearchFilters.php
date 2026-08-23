<?php

namespace App\DTOs\Cancellations;

use App\Enums\CancellationStatus;
use App\Enums\CancellationType;
use Carbon\CarbonImmutable;

final readonly class CancellationSearchFilters
{
    public function __construct(
        public ?CancellationType $type = null,
        public ?CancellationStatus $status = null,
        public ?int $radicado = null,
        public ?string $holderName = null,
        public ?string $holderCedula = null,
        public ?string $holderEmail = null,
        public ?string $holderPhone = null,
        public ?string $plate = null,
        public ?string $creditNumber = null,
        public ?int $assignedAdvisorUserId = null,
        public ?CarbonImmutable $createdFrom = null,
        public ?CarbonImmutable $createdTo = null,
        public string $sort = 'created_at_desc',
    ) {}
}
