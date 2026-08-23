<?php

use App\Actions\Authorization\SyncPermissionCatalogueAction;
use App\Authorization\PermissionCatalogue;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->createPermissionsTable();

        Schema::create('role_permissions', function (Blueprint $table): void {
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['role_id', 'permission_id']);
        });

        app(SyncPermissionCatalogueAction::class)->execute();
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
    }

    private function createPermissionsTable(): void
    {
        $allowedKeys = "'".implode("', '", PermissionCatalogue::keys())."'";

        if (DB::getDriverName() === 'sqlite') {
            DB::statement(
                "CREATE TABLE permissions (
                    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                    key VARCHAR(64) NOT NULL,
                    name VARCHAR(128) NOT NULL,
                    created_at DATETIME,
                    updated_at DATETIME,
                    CONSTRAINT permissions_key_unique UNIQUE (key),
                    CONSTRAINT permissions_key_check CHECK (key IN ({$allowedKeys}))
                )"
            );

            return;
        }

        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('name', 128);
            $table->timestamps();
        });

        DB::statement("ALTER TABLE permissions ADD CONSTRAINT permissions_key_check CHECK (`key` IN ({$allowedKeys}))");
    }
};
