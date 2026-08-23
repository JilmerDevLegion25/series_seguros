<?php

namespace App\Console\Commands;

use App\Enums\CancellationType;
use App\Models\RadicadoSequence;
use Illuminate\Console\Command;

final class InitializeCreditRadicadoSequenceCommand extends Command
{
    protected $signature = 'app:init-credit-radicado-sequence {next_value : Positive next Credit radicado value}';

    protected $description = 'Initialize or update the Credit radicado sequence from an explicit deployment/local input.';

    public function handle(): int
    {
        $nextValue = filter_var($this->argument('next_value'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if (! is_int($nextValue)) {
            $this->error('next_value must be a positive integer.');

            return self::FAILURE;
        }

        RadicadoSequence::query()->updateOrCreate(
            ['type' => CancellationType::CREDIT],
            ['next_value' => $nextValue],
        );

        $this->info('Credit radicado sequence initialized.');

        return self::SUCCESS;
    }
}
