<?php

use App\Enums\CancellationType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('radicado_sequences', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 16)->unique();
            $table->unsignedBigInteger('next_value');
            $table->timestamps();
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE radicado_sequences ADD CONSTRAINT radicado_sequences_type_check CHECK (`type` IN ({$this->allowedTypes()}))");
            DB::statement('ALTER TABLE radicado_sequences ADD CONSTRAINT radicado_sequences_next_value_check CHECK (next_value > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('radicado_sequences');
    }

    private function allowedTypes(): string
    {
        return "'".implode("', '", array_map(
            static fn (CancellationType $type): string => $type->value,
            CancellationType::cases(),
        ))."'";
    }
};
