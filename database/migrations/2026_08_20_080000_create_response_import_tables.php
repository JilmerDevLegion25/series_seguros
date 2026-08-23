<?php

use App\Enums\CancellationStatus;
use App\Enums\CancellationType;
use App\Enums\ImportBatchStatus;
use App\Enums\ImportRowReason;
use App\Enums\ImportRowStatus;
use App\Enums\NotificationType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('responses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('moto_cancellation_id')->nullable()->constrained('moto_cancellations')->restrictOnDelete();
            $table->foreignId('credit_cancellation_id')->nullable()->constrained('credit_cancellations')->restrictOnDelete();
            $table->date('cancellation_date');
            $table->text('observation');
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique('moto_cancellation_id', 'responses_moto_unique');
            $table->unique('credit_cancellation_id', 'responses_credit_unique');
        });

        Schema::create('notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('type', 64);
            $table->foreignId('moto_cancellation_id')->nullable()->constrained('moto_cancellations')->restrictOnDelete();
            $table->foreignId('credit_cancellation_id')->nullable()->constrained('credit_cancellations')->restrictOnDelete();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['moto_cancellation_id', 'type'], 'notifications_moto_type_unique');
            $table->unique(['credit_cancellation_id', 'type'], 'notifications_credit_type_unique');
            $table->index(['user_id', 'read_at']);
        });

        Schema::create('import_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('uploaded_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('cancellation_type', 16);
            $table->string('status', 32);
            $table->string('stored_path', 255);
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('successful_rows')->default(0);
            $table->unsignedInteger('rejected_rows')->default(0);
            $table->string('failure_reason', 64)->nullable();
            $table->timestamps();

            $table->index(['uploaded_by_user_id', 'created_at']);
            $table->index(['cancellation_type', 'status']);
        });

        Schema::create('import_row_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('import_batch_id')->constrained('import_batches')->restrictOnDelete();
            $table->unsignedInteger('row_number');
            $table->unsignedBigInteger('radicado')->nullable();
            $table->string('status', 16);
            $table->string('reason', 64)->nullable();
            $table->string('message', 255);
            $table->foreignId('moto_cancellation_id')->nullable()->constrained('moto_cancellations')->restrictOnDelete();
            $table->foreignId('credit_cancellation_id')->nullable()->constrained('credit_cancellations')->restrictOnDelete();
            $table->foreignId('response_id')->nullable()->constrained('responses')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['import_batch_id', 'status']);
            $table->index(['radicado', 'reason']);
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE responses ADD CONSTRAINT responses_one_parent_check CHECK ((moto_cancellation_id IS NOT NULL) <> (credit_cancellation_id IS NOT NULL))');
            DB::statement('ALTER TABLE responses ADD CONSTRAINT responses_observation_length_check CHECK (CHAR_LENGTH(observation) BETWEEN 1 AND 2000)');
            DB::statement("ALTER TABLE notifications ADD CONSTRAINT notifications_type_check CHECK (type IN ({$this->enumValues(NotificationType::cases())}))");
            DB::statement('ALTER TABLE notifications ADD CONSTRAINT notifications_one_parent_check CHECK ((moto_cancellation_id IS NOT NULL) <> (credit_cancellation_id IS NOT NULL))');
            DB::statement("ALTER TABLE import_batches ADD CONSTRAINT import_batches_type_check CHECK (cancellation_type IN ({$this->enumValues(CancellationType::cases())}))");
            DB::statement("ALTER TABLE import_batches ADD CONSTRAINT import_batches_status_check CHECK (status IN ({$this->enumValues(ImportBatchStatus::cases())}))");
            DB::statement("ALTER TABLE import_row_results ADD CONSTRAINT import_rows_status_check CHECK (status IN ({$this->enumValues(ImportRowStatus::cases())}))");
            DB::statement("ALTER TABLE import_row_results ADD CONSTRAINT import_rows_reason_check CHECK (reason IS NULL OR reason IN ({$this->enumValues(ImportRowReason::cases())}))");
            DB::statement('ALTER TABLE import_row_results ADD CONSTRAINT import_rows_one_parent_check CHECK ((moto_cancellation_id IS NULL AND credit_cancellation_id IS NULL) OR ((moto_cancellation_id IS NOT NULL) <> (credit_cancellation_id IS NOT NULL)))');
            DB::statement('ALTER TABLE moto_cancellations DROP CHECK moto_status_check');
            DB::statement("ALTER TABLE moto_cancellations ADD CONSTRAINT moto_status_check CHECK (status IN ({$this->enumValues(CancellationStatus::cases())}))");
            DB::statement('ALTER TABLE credit_cancellations DROP CHECK credit_status_check');
            DB::statement("ALTER TABLE credit_cancellations ADD CONSTRAINT credit_status_check CHECK (status IN ({$this->enumValues(CancellationStatus::cases())}))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('import_row_results');
        Schema::dropIfExists('import_batches');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('responses');
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
