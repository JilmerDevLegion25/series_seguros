<?php

namespace App\DTOs\Cancellations;

use App\Support\Changes\ChangeSet;
use Illuminate\Database\Eloquent\Model;

final readonly class CancellationMutationResult
{
    public function __construct(
        public Model $cancellation,
        public bool $changed,
        public ChangeSet $changeSet,
    ) {}
}
