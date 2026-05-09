<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            // Campos de flota / documentos
            if (!Schema::hasColumn('vehicles', 'vin')) {
                $table->string('vin', 64)->nullable()->after('modelo');
            }
            if (!Schema::hasColumn('vehicles', 'zona_operacion')) {
                $table->string('zona_operacion', 255)->nullable()->after('vin');
            }
            if (!Schema::hasColumn('vehicles', 'kilometraje')) {
                $table->unsignedInteger('kilometraje')->nullable()->after('anio');
            }
            if (!Schema::hasColumn('vehicles', 'nivel_combustible')) {
                $table->unsignedTinyInteger('nivel_combustible')->nullable()->after('kilometraje');
            }
            if (!Schema::hasColumn('vehicles', 'eficiencia')) {
                $table->unsignedTinyInteger('eficiencia')->nullable()->after('nivel_combustible');
            }
            if (!Schema::hasColumn('vehicles', 'fecha_seguro')) {
                $table->date('fecha_seguro')->nullable()->after('fecha_proximo_mantenimiento');
            }
            if (!Schema::hasColumn('vehicles', 'fecha_itv')) {
                $table->date('fecha_itv')->nullable()->after('fecha_seguro');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            foreach ([
                'vin',
                'zona_operacion',
                'kilometraje',
                'nivel_combustible',
                'eficiencia',
                'fecha_seguro',
                'fecha_itv',
            ] as $column) {
                if (Schema::hasColumn('vehicles', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

