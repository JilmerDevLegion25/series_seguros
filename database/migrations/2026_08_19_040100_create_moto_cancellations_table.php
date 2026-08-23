<?php

use App\Enums\CancellationOrigin;
use App\Enums\CancellationStatus;
use App\Enums\MotoCancellationReason;
use App\Enums\MotoInformationSource;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('moto_cancellations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('otp_challenge_id')->unique()->constrained('otp_challenges')->restrictOnDelete();
            $table->unsignedBigInteger('radicado')->unique();
            $table->foreignId('owner_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('origin', 16);
            $table->string('status', 32);
            $table->unsignedInteger('version')->default(1);
            $table->string('holder_name', 150);
            $table->string('holder_cedula', 10);
            $table->boolean('property_lien_adeinco');
            $table->string('plate', 20);
            $table->string('holder_phone', 13);
            $table->string('holder_email', 255);
            $table->string('cancellation_reason', 64);
            $table->string('cancellation_information_source', 64);
            $table->boolean('is_credit_holder');
            $table->string('credit_owner_name', 150)->nullable();
            $table->string('credit_owner_cedula', 10)->nullable();
            $table->boolean('ownership_declaration_accepted');
            $table->boolean('data_processing_accepted');
            $table->timestamps();

            $table->index(['owner_user_id', 'status']);
            $table->index(['created_by_user_id', 'origin']);
            $table->index('holder_cedula');
            $table->index('plate');
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE moto_cancellations ADD CONSTRAINT moto_origin_check CHECK (origin IN ({$this->allowedOrigins()}))");
            DB::statement("ALTER TABLE moto_cancellations ADD CONSTRAINT moto_status_check CHECK (status IN ({$this->allowedStatuses()}))");
            DB::statement("ALTER TABLE moto_cancellations ADD CONSTRAINT moto_reason_check CHECK (cancellation_reason IN ({$this->allowedReasons()}))");
            DB::statement("ALTER TABLE moto_cancellations ADD CONSTRAINT moto_source_check CHECK (cancellation_information_source IN ({$this->allowedSources()}))");
            DB::statement('ALTER TABLE moto_cancellations ADD CONSTRAINT moto_version_check CHECK (version >= 1)');
            DB::statement('ALTER TABLE moto_cancellations ADD CONSTRAINT moto_declarations_check CHECK (ownership_declaration_accepted = 1 AND data_processing_accepted = 1)');
            DB::statement('ALTER TABLE moto_cancellations ADD CONSTRAINT moto_credit_owner_check CHECK (is_credit_holder = 1 OR (credit_owner_name IS NOT NULL AND credit_owner_cedula IS NOT NULL))');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('moto_cancellations');
    }

    private function allowedOrigins(): string
    {
        return $this->enumValues(CancellationOrigin::cases());
    }

    private function allowedStatuses(): string
    {
        return $this->enumValues(CancellationStatus::cases());
    }

    private function allowedReasons(): string
    {
        return $this->enumValues(MotoCancellationReason::cases());
    }

    private function allowedSources(): string
    {
        return $this->enumValues(MotoInformationSource::cases());
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
