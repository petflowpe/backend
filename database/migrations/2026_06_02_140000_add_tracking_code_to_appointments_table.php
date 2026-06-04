<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('appointments')) {
            return;
        }

        if (Schema::hasColumn('appointments', 'tracking_code')) {
            return;
        }

        Schema::table('appointments', function (Blueprint $table) {
            $table->string('tracking_code', 20)->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('appointments') || !Schema::hasColumn('appointments', 'tracking_code')) {
            return;
        }

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropUnique(['tracking_code']);
            $table->dropColumn('tracking_code');
        });
    }
};
