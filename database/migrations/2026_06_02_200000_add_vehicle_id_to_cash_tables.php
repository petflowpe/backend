<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_sessions', function (Blueprint $table) {
            $table->foreignId('vehicle_id')->nullable()->after('branch_id')->constrained()->nullOnDelete();
            $table->index(['company_id', 'status', 'vehicle_id']);
        });

        Schema::table('cash_movements', function (Blueprint $table) {
            $table->foreignId('vehicle_id')->nullable()->after('branch_id')->constrained()->nullOnDelete();
            $table->unsignedBigInteger('appointment_id')->nullable()->after('cash_session_id');
            $table->index(['cash_session_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::table('cash_movements', function (Blueprint $table) {
            $table->dropIndex(['cash_session_id', 'type']);
            $table->dropColumn(['vehicle_id', 'appointment_id']);
        });

        Schema::table('cash_sessions', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'status', 'vehicle_id']);
            $table->dropConstrainedForeignId('vehicle_id');
        });
    }
};
