<?php

use App\Enums\RoleCode;
use App\Enums\UserStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->createRolesTable();

        DB::table('roles')->insert([
            [
                'code' => RoleCode::CLIENT->value,
                'name' => 'Client',
                'created_at' => now(),
            ],
            [
                'code' => RoleCode::ADVISOR->value,
                'name' => 'Advisor',
                'created_at' => now(),
            ],
        ]);

        $this->createUsersTable();
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('roles');
    }

    private function createRolesTable(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement(
                "CREATE TABLE roles (
                    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                    code VARCHAR(32) NOT NULL,
                    name VARCHAR(64) NOT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    CONSTRAINT roles_code_unique UNIQUE (code),
                    CONSTRAINT roles_code_check CHECK (code IN ('CLIENT', 'ADVISOR'))
                )"
            );

            return;
        }

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 64);
            $table->timestamp('created_at')->useCurrent();
        });

        DB::statement("ALTER TABLE roles ADD CONSTRAINT roles_code_check CHECK (code IN ('CLIENT', 'ADVISOR'))");
    }

    private function createUsersTable(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement(
                "CREATE TABLE users (
                    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                    role_id INTEGER NOT NULL,
                    username VARCHAR(64) NOT NULL,
                    identity VARCHAR(10),
                    name VARCHAR(150) NOT NULL,
                    email VARCHAR(255),
                    phone VARCHAR(13),
                    password VARCHAR(255) NOT NULL,
                    status VARCHAR(16) NOT NULL DEFAULT 'ACTIVE',
                    must_change_password TINYINT(1) NOT NULL DEFAULT 0,
                    created_at DATETIME,
                    updated_at DATETIME,
                    CONSTRAINT users_username_unique UNIQUE (username),
                    CONSTRAINT users_role_identity_unique UNIQUE (role_id, identity),
                    CONSTRAINT users_role_id_foreign FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE RESTRICT,
                    CONSTRAINT users_status_check CHECK (status IN ('ACTIVE', 'INACTIVE'))
                )"
            );

            return;
        }

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->restrictOnDelete();
            $table->string('username', 64)->unique();
            $table->string('identity', 10)->nullable();
            $table->string('name', 150);
            $table->string('email', 255)->nullable();
            $table->string('phone', 13)->nullable();
            $table->string('password');
            $table->string('status', 16)->default(UserStatus::ACTIVE->value);
            $table->boolean('must_change_password')->default(false);
            $table->timestamps();

            $table->unique(['role_id', 'identity']);
        });

        DB::statement("ALTER TABLE users ADD CONSTRAINT users_status_check CHECK (status IN ('ACTIVE', 'INACTIVE'))");
    }
};
