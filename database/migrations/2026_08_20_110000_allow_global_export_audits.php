<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE audits DROP CHECK audits_one_parent_check');
        DB::statement('ALTER TABLE audits ADD CONSTRAINT audits_parent_scope_check CHECK (NOT (moto_cancellation_id IS NOT NULL AND credit_cancellation_id IS NOT NULL))');
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE audits DROP CHECK audits_parent_scope_check');
        DB::statement('ALTER TABLE audits ADD CONSTRAINT audits_one_parent_check CHECK ((moto_cancellation_id IS NOT NULL) <> (credit_cancellation_id IS NOT NULL))');
    }
};
