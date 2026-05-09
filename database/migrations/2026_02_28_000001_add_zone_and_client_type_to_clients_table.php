<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (!Schema::hasColumn('clients', 'zone_id')) {
                $table->foreignId('zone_id')
                    ->nullable()
                    ->constrained('zones')
                    ->nullOnDelete()
                    ->after('company_id');
                $table->index(['company_id', 'zone_id']);
            }

            if (!Schema::hasColumn('clients', 'client_type')) {
                $table->enum('client_type', ['Regular', 'VIP', 'Moroso', 'Problemático', 'Empleado'])
                    ->default('Regular')
                    ->after('zone_id');
                $table->index(['company_id', 'client_type']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (Schema::hasColumn('clients', 'client_type')) {
                $table->dropIndex(['company_id', 'client_type']);
                $table->dropColumn('client_type');
            }
            if (Schema::hasColumn('clients', 'zone_id')) {
                $table->dropIndex(['company_id', 'zone_id']);
                $table->dropConstrainedForeignId('zone_id');
            }
        });
    }
};

