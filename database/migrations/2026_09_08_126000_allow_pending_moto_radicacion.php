<?php

use App\Enums\ActivityType;
use App\Enums\AuditEventType;
use App\Enums\CancellationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE moto_cancellations MODIFY radicado BIGINT UNSIGNED NULL');
            DB::statement('ALTER TABLE moto_cancellations DROP CHECK moto_status_check');
            DB::statement("ALTER TABLE moto_cancellations ADD CONSTRAINT moto_status_check CHECK (status IN ({$this->allowedStatuses()}))");
            DB::statement('ALTER TABLE activities DROP CHECK activities_type_check');
            DB::statement("ALTER TABLE activities ADD CONSTRAINT activities_type_check CHECK (type IN ({$this->enumValues(ActivityType::cases())}))");
            DB::statement('ALTER TABLE audits DROP CHECK audits_event_type_check');
            DB::statement("ALTER TABLE audits ADD CONSTRAINT audits_event_type_check CHECK (event_type IN ({$this->enumValues(AuditEventType::cases())}))");

            return;
        }

        Schema::table('moto_cancellations', function (Blueprint $table): void {
            $table->unsignedBigInteger('radicado')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE moto_cancellations DROP CHECK moto_status_check');
            DB::statement('ALTER TABLE activities DROP CHECK activities_type_check');
            DB::statement('ALTER TABLE audits DROP CHECK audits_event_type_check');
        }

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE moto_cancellations MODIFY radicado BIGINT UNSIGNED NOT NULL');
            DB::statement("ALTER TABLE moto_cancellations ADD CONSTRAINT moto_status_check CHECK (status IN ('EN_GESTION', 'RESPUESTA_OBTENIDA'))");
            DB::statement("ALTER TABLE activities ADD CONSTRAINT activities_type_check CHECK (type IN ('CREATED', 'UPDATED', 'OWNER_REASSIGNED', 'RESPONSE_OBTAINED'))");
            DB::statement("ALTER TABLE audits ADD CONSTRAINT audits_event_type_check CHECK (event_type IN ('CANCELLATION_CREATED', 'CANCELLATION_UPDATED', 'OWNER_REASSIGNED', 'RADICADO_SMS_RETRY_REQUESTED', 'RESPONSE_OBTAINED', 'EXPORT_GENERATED'))");

            return;
        }

        Schema::table('moto_cancellations', function (Blueprint $table): void {
            $table->unsignedBigInteger('radicado')->nullable(false)->change();
        });
    }

    private function allowedStatuses(): string
    {
        return $this->enumValues(CancellationStatus::cases());
    }

    /**
     * @param  list<BackedEnum>  $cases
     */
    private function enumValues(array $cases): string
    {
        return "'".implode("', '", array_map(
            static fn (BackedEnum $case): string => (string) $case->value,
            $cases,
        ))."'";
    }
};
