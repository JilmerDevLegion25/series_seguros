<?php

use App\Enums\AuditEventType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_attempts', function (Blueprint $table): void {
            $table->boolean('is_manual_retry')->default(false)->after('status');
            $table->timestamp('completed_at')->nullable()->after('safe_error_message');
            $table->index(['moto_cancellation_id', 'purpose', 'is_manual_retry', 'created_at'], 'sms_moto_retry_window_index');
            $table->index(['credit_cancellation_id', 'purpose', 'is_manual_retry', 'created_at'], 'sms_credit_retry_window_index');
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE audits DROP CHECK audits_event_type_check');
            DB::statement("ALTER TABLE audits ADD CONSTRAINT audits_event_type_check CHECK (event_type IN ({$this->auditEventTypes()}))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE audits DROP CHECK audits_event_type_check');
            DB::statement("ALTER TABLE audits ADD CONSTRAINT audits_event_type_check CHECK (event_type IN ('CANCELLATION_CREATED', 'CANCELLATION_UPDATED', 'OWNER_REASSIGNED', 'RESPONSE_OBTAINED', 'EXPORT_GENERATED'))");
        }

        Schema::table('sms_attempts', function (Blueprint $table): void {
            $table->dropIndex('sms_credit_retry_window_index');
            $table->dropIndex('sms_moto_retry_window_index');
            $table->dropColumn(['is_manual_retry', 'completed_at']);
        });
    }

    private function auditEventTypes(): string
    {
        return "'".implode("', '", array_map(
            static fn (AuditEventType $eventType): string => $eventType->value,
            AuditEventType::cases(),
        ))."'";
    }
};
