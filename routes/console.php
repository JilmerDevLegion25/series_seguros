<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('about:foundation', function (): void {
    $this->info('Phase 00 foundation is installed.');
})->purpose('Show the Phase 00 foundation marker');
