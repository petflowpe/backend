<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('route_stops')) {
            return;
        }

        if (Schema::hasColumn('route_stops', 'appointment_id')) {
            return;
        }

        Schema::table('route_stops', function (Blueprint $table) {
            $table->foreignId('appointment_id')
                ->nullable()
                ->after('client_id')
                ->constrained('appointments')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('route_stops') || !Schema::hasColumn('route_stops', 'appointment_id')) {
            return;
        }

        Schema::table('route_stops', function (Blueprint $table) {
            $table->dropForeign(['appointment_id']);
            $table->dropColumn('appointment_id');
        });
    }
};
