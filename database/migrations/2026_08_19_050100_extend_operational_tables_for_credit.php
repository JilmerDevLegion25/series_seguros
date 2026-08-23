<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table): void {
            $table->foreignId('credit_cancellation_id')->nullable()->after('moto_cancellation_id')->constrained('credit_cancellations')->restrictOnDelete();
            $table->index(['credit_cancellation_id', 'type']);
        });

        Schema::table('audits', function (Blueprint $table): void {
            $table->foreignId('credit_cancellation_id')->nullable()->after('moto_cancellation_id')->constrained('credit_cancellations')->restrictOnDelete();
            $table->index(['credit_cancellation_id', 'event_type']);
        });

        Schema::table('sms_attempts', function (Blueprint $table): void {
            $table->foreignId('credit_cancellation_id')->nullable()->after('moto_cancellation_id')->constrained('credit_cancellations')->restrictOnDelete();
            $table->index(['credit_cancellation_id', 'purpose']);
        });

        if (DB::getDriverName() !== 'sqlite') {
            $this->makeMotoReferencesNullable();
            DB::statement('ALTER TABLE activities ADD CONSTRAINT activities_one_parent_check CHECK ((moto_cancellation_id IS NOT NULL) <> (credit_cancellation_id IS NOT NULL))');
            DB::statement('ALTER TABLE audits ADD CONSTRAINT audits_one_parent_check CHECK ((moto_cancellation_id IS NOT NULL) <> (credit_cancellation_id IS NOT NULL))');
            DB::statement('ALTER TABLE sms_attempts ADD CONSTRAINT sms_attempts_one_parent_check CHECK ((moto_cancellation_id IS NOT NULL) <> (credit_cancellation_id IS NOT NULL))');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE sms_attempts DROP CHECK sms_attempts_one_parent_check');
            DB::statement('ALTER TABLE audits DROP CHECK audits_one_parent_check');
            DB::statement('ALTER TABLE activities DROP CHECK activities_one_parent_check');
        }

        Schema::table('sms_attempts', function (Blueprint $table): void {
            $table->dropForeign(['credit_cancellation_id']);
            $table->dropIndex(['credit_cancellation_id', 'purpose']);
            $table->dropColumn('credit_cancellation_id');
        });

        Schema::table('audits', function (Blueprint $table): void {
            $table->dropForeign(['credit_cancellation_id']);
            $table->dropIndex(['credit_cancellation_id', 'event_type']);
            $table->dropColumn('credit_cancellation_id');
        });

        Schema::table('activities', function (Blueprint $table): void {
            $table->dropForeign(['credit_cancellation_id']);
            $table->dropIndex(['credit_cancellation_id', 'type']);
            $table->dropColumn('credit_cancellation_id');
        });
    }

    private function makeMotoReferencesNullable(): void
    {
        DB::statement('ALTER TABLE activities MODIFY moto_cancellation_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE audits MODIFY moto_cancellation_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE sms_attempts MODIFY moto_cancellation_id BIGINT UNSIGNED NULL');
    }
};
