<?php

namespace App\DTOs\Cancellations;

final readonly class ReassignCancellationAdvisorData
{
    public function __construct(
        public int $expectedVersion,
        public string $reason,
        public int $assignedAdvisorUserId,
    ) {}
}
