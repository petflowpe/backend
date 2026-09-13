<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            if (! Schema::hasColumn('suppliers', 'specialty')) {
                $table->string('specialty', 120)->nullable()->after('supplier_type');
            }
            if (! Schema::hasColumn('suppliers', 'professional_license')) {
                $table->string('professional_license', 50)->nullable()->after('specialty');
            }
            if (! Schema::hasColumn('suppliers', 'clinic_name')) {
                $table->string('clinic_name', 255)->nullable()->after('professional_license');
            }
            if (! Schema::hasColumn('suppliers', 'fee_rate')) {
                $table->decimal('fee_rate', 12, 2)->nullable()->after('clinic_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            foreach (['specialty', 'professional_license', 'clinic_name', 'fee_rate'] as $col) {
                if (Schema::hasColumn('suppliers', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
