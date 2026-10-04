<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('services')) {
            return;
        }

        Schema::table('services', function (Blueprint $table) {
            try {
                $table->dropUnique(['code']);
            } catch (\Throwable $e) {
                // Puede no existir o tener otro nombre en algunos entornos.
            }
        });

        Schema::table('services', function (Blueprint $table) {
            $table->unique(['company_id', 'code'], 'services_company_id_code_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('services')) {
            return;
        }

        Schema::table('services', function (Blueprint $table) {
            try {
                $table->dropUnique('services_company_id_code_unique');
            } catch (\Throwable $e) {
                // ignore
            }
            $table->unique('code');
        });
    }
};
