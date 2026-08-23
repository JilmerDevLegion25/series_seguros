<?php

namespace App\Console\Commands;

use App\Actions\Authorization\SyncPermissionCatalogueAction;
use Illuminate\Console\Command;

final class SyncPermissionsCommand extends Command
{
    protected $signature = 'app:sync-permissions';

    protected $description = 'Synchronize the approved permission catalogue.';

    public function handle(SyncPermissionCatalogueAction $syncPermissionCatalogue): int
    {
        $syncPermissionCatalogue->execute();

        $this->info('Permission catalogue synchronized.');

        return self::SUCCESS;
    }
}
