<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            if (! Schema::hasColumn('vehicles', 'ultimo_cumplimiento_inspeccion')) {
                $table->decimal('ultimo_cumplimiento_inspeccion', 5, 1)->nullable()->after('notas_mantenimiento');
            }
            if (! Schema::hasColumn('vehicles', 'fecha_ultima_inspeccion')) {
                $table->dateTime('fecha_ultima_inspeccion')->nullable()->after('ultimo_cumplimiento_inspeccion');
            }
            if (! Schema::hasColumn('vehicles', 'indice_chofer')) {
                $table->unsignedTinyInteger('indice_chofer')->default(100)->after('fecha_ultima_inspeccion');
            }
            if (! Schema::hasColumn('vehicles', 'puntos_observacion_chofer')) {
                $table->unsignedInteger('puntos_observacion_chofer')->default(0)->after('indice_chofer');
            }
            if (! Schema::hasColumn('vehicles', 'observaciones_inspeccion_acumuladas')) {
                $table->longText('observaciones_inspeccion_acumuladas')->nullable()->after('puntos_observacion_chofer');
            }
        });

        if (Schema::hasTable('vehicle_inspection_attachments') && ! Schema::hasColumn('vehicle_inspection_attachments', 'data_url')) {
            Schema::table('vehicle_inspection_attachments', function (Blueprint $table) {
                $table->longText('data_url')->nullable()->after('path');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('vehicle_inspection_attachments') && Schema::hasColumn('vehicle_inspection_attachments', 'data_url')) {
            Schema::table('vehicle_inspection_attachments', function (Blueprint $table) {
                $table->dropColumn('data_url');
            });
        }

        Schema::table('vehicles', function (Blueprint $table) {
            foreach ([
                'observaciones_inspeccion_acumuladas',
                'puntos_observacion_chofer',
                'indice_chofer',
                'fecha_ultima_inspeccion',
                'ultimo_cumplimiento_inspeccion',
            ] as $column) {
                if (Schema::hasColumn('vehicles', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
