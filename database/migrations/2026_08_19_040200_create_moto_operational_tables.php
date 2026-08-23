<?php

use App\Enums\ActivityType;
use App\Enums\AuditEventType;
use App\Enums\SmsAttemptStatus;
use App\Enums\SmsPurpose;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('moto_cancellation_id')->nullable()->constrained('moto_cancellations')->restrictOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('type', 64);
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('moto_cancellation_id')->nullable()->constrained('moto_cancellations')->restrictOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('event_type', 64);
            $table->string('request_id', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('sms_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('moto_cancellation_id')->nullable()->constrained('moto_cancellations')->restrictOnDelete();
            $table->string('purpose', 32);
            $table->string('status', 16);
            $table->string('destination', 32);
            $table->string('provider_reference', 128)->nullable();
            $table->string('safe_error_code', 64)->nullable();
            $table->string('safe_error_message', 255)->nullable();
            $table->timestamps();

            $table->index(['moto_cancellation_id', 'purpose']);
            $table->index(['status', 'created_at']);
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE activities ADD CONSTRAINT activities_type_check CHECK (type IN ({$this->activityTypes()}))");
            DB::statement("ALTER TABLE audits ADD CONSTRAINT audits_event_type_check CHECK (event_type IN ({$this->auditEventTypes()}))");
            DB::statement("ALTER TABLE sms_attempts ADD CONSTRAINT sms_attempts_purpose_check CHECK (purpose IN ({$this->smsPurposes()}))");
            DB::statement("ALTER TABLE sms_attempts ADD CONSTRAINT sms_attempts_status_check CHECK (status IN ({$this->smsStatuses()}))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_attempts');
        Schema::dropIfExists('audits');
        Schema::dropIfExists('activities');
    }

    private function activityTypes(): string
    {
        return $this->enumValues(ActivityType::cases());
    }

    private function auditEventTypes(): string
    {
        return $this->enumValues(AuditEventType::cases());
    }

    private function smsPurposes(): string
    {
        return $this->enumValues(SmsPurpose::cases());
    }

    private function smsStatuses(): string
    {
        return $this->enumValues(SmsAttemptStatus::cases());
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
