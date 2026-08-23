<?php

namespace App\Services\Radicado;

use App\Enums\CancellationType;
use App\Models\RadicadoSequence;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class RadicadoAllocator
{
    public function allocate(CancellationType $type): int
    {
        if (DB::transactionLevel() === 0) {
            return DB::transaction(fn (): int => $this->allocateLocked($type));
        }

        return $this->allocateLocked($type);
    }

    private function allocateLocked(CancellationType $type): int
    {
        /** @var RadicadoSequence|null $sequence */
        $sequence = RadicadoSequence::query()
            ->where('type', $type)
            ->lockForUpdate()
            ->first();

        if (! $sequence instanceof RadicadoSequence) {
            throw new RuntimeException("Radicado sequence {$type->value} is not initialized.");
        }

        $radicado = $sequence->next_value;
        $sequence->forceFill(['next_value' => $radicado + 1])->save();

        return $radicado;
    }
}
