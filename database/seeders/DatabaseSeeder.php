<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if ($this->isProduction()) {
            $this->command?->warn('Development seeders skipped in production.');

            return;
        }

        $this->call(DevelopmentSeeder::class);
    }

    private function isProduction(): bool
    {
        return app()->environment('production') || config('app.env') === 'production';
    }
}
