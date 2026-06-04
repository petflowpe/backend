<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pets', function (Blueprint $table) {
            if (!Schema::hasColumn('pets', 'last_flea_treatment_date')) {
                $table->date('last_flea_treatment_date')->nullable()->after('next_deworming_date');
            }
            if (!Schema::hasColumn('pets', 'next_flea_treatment_date')) {
                $table->date('next_flea_treatment_date')->nullable()->after('last_flea_treatment_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pets', function (Blueprint $table) {
            if (Schema::hasColumn('pets', 'next_flea_treatment_date')) {
                $table->dropColumn('next_flea_treatment_date');
            }
            if (Schema::hasColumn('pets', 'last_flea_treatment_date')) {
                $table->dropColumn('last_flea_treatment_date');
            }
        });
    }
};
