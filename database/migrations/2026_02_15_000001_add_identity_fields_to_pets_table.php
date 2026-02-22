<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pets', function (Blueprint $table) {
            if (!Schema::hasColumn('pets', 'last_name')) {
                $table->string('last_name', 120)->nullable()->after('name');
            }
            if (!Schema::hasColumn('pets', 'identification_type')) {
                $table->string('identification_type', 50)->nullable()->after('microchip');
            }
            if (!Schema::hasColumn('pets', 'identification_number')) {
                $table->string('identification_number', 100)->nullable()->after('identification_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pets', function (Blueprint $table) {
            foreach (['last_name', 'identification_type', 'identification_number'] as $column) {
                if (Schema::hasColumn('pets', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
