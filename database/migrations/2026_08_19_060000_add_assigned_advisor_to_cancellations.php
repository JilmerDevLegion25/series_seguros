<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('moto_cancellations', function (Blueprint $table): void {
            $table->foreignId('assigned_advisor_user_id')
                ->nullable()
                ->after('created_by_user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->index(['assigned_advisor_user_id', 'status']);
        });

        Schema::table('credit_cancellations', function (Blueprint $table): void {
            $table->foreignId('assigned_advisor_user_id')
                ->nullable()
                ->after('created_by_user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->index(['assigned_advisor_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('credit_cancellations', function (Blueprint $table): void {
            $table->dropIndex(['assigned_advisor_user_id', 'status']);
            $table->dropConstrainedForeignId('assigned_advisor_user_id');
        });

        Schema::table('moto_cancellations', function (Blueprint $table): void {
            $table->dropIndex(['assigned_advisor_user_id', 'status']);
            $table->dropConstrainedForeignId('assigned_advisor_user_id');
        });
    }
};
