<?php

use App\Enums\OtpPurpose;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->createOtpChallengesTable();
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_challenges');
    }

    private function createOtpChallengesTable(): void
    {
        $purposes = "'".implode("', '", array_map(
            static fn (OtpPurpose $purpose): string => $purpose->value,
            OtpPurpose::cases(),
        ))."'";

        if (DB::getDriverName() === 'sqlite') {
            DB::statement(
                "CREATE TABLE otp_challenges (
                    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                    public_reference VARCHAR(64) NOT NULL,
                    purpose VARCHAR(32) NOT NULL,
                    target_user_id INTEGER,
                    destination_snapshot VARCHAR(32) NOT NULL,
                    encrypted_payload TEXT,
                    otp_mac CHAR(64) NOT NULL,
                    failed_attempts INTEGER NOT NULL DEFAULT 0,
                    emission_count INTEGER NOT NULL DEFAULT 0,
                    last_emitted_at DATETIME,
                    expires_at DATETIME NOT NULL,
                    consumed_at DATETIME,
                    invalidated_at DATETIME,
                    invalidation_reason VARCHAR(64),
                    created_at DATETIME,
                    updated_at DATETIME,
                    CONSTRAINT otp_public_reference_unique UNIQUE (public_reference),
                    CONSTRAINT otp_target_user_foreign FOREIGN KEY (target_user_id) REFERENCES users (id) ON DELETE RESTRICT,
                    CONSTRAINT otp_purpose_check CHECK (purpose IN ({$purposes})),
                    CONSTRAINT otp_failed_attempts_check CHECK (failed_attempts >= 0 AND failed_attempts <= 5),
                    CONSTRAINT otp_emission_count_check CHECK (emission_count >= 0 AND emission_count <= 3),
                    CONSTRAINT otp_terminal_xor_check CHECK (NOT (consumed_at IS NOT NULL AND invalidated_at IS NOT NULL))
                )"
            );

            return;
        }

        Schema::create('otp_challenges', function (Blueprint $table): void {
            $table->id();
            $table->string('public_reference', 64)->unique();
            $table->string('purpose', 32);
            $table->foreignId('target_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('destination_snapshot', 32);
            $table->text('encrypted_payload')->nullable();
            $table->char('otp_mac', 64);
            $table->unsignedTinyInteger('failed_attempts')->default(0);
            $table->unsignedTinyInteger('emission_count')->default(0);
            $table->timestamp('last_emitted_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('invalidated_at')->nullable();
            $table->string('invalidation_reason', 64)->nullable();
            $table->timestamps();

            $table->index(['purpose', 'target_user_id']);
        });

        DB::statement("ALTER TABLE otp_challenges ADD CONSTRAINT otp_purpose_check CHECK (purpose IN ({$purposes}))");
        DB::statement('ALTER TABLE otp_challenges ADD CONSTRAINT otp_failed_attempts_check CHECK (failed_attempts >= 0 AND failed_attempts <= 5)');
        DB::statement('ALTER TABLE otp_challenges ADD CONSTRAINT otp_emission_count_check CHECK (emission_count >= 0 AND emission_count <= 3)');
        DB::statement('ALTER TABLE otp_challenges ADD CONSTRAINT otp_terminal_xor_check CHECK (NOT (consumed_at IS NOT NULL AND invalidated_at IS NOT NULL))');
    }
};
