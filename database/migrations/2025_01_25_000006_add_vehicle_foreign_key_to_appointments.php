<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Esta migración se ejecuta después de que vehicles esté creada
        // Verificar si la tabla appointments existe y si la foreign key ya está creada
        if (!Schema::hasTable('appointments') || !Schema::hasTable('vehicles')) {
            return;
        }

        // Verificar si la foreign key ya existe
        $foreignKeys = Schema::getForeignKeys('appointments');

        $hasForeignKey = false;
        foreach ($foreignKeys as $foreignKey) {
            if ($foreignKey['name'] === 'appointments_vehicle_id_foreign') {
                $hasForeignKey = true;
                break;
            }
        }

        if (!$hasForeignKey) {
            Schema::table('appointments', function (Blueprint $table) {
                $table->foreign('vehicle_id')
                    ->references('id')
                    ->on('vehicles')
                    ->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['vehicle_id']);
        });
    }
};
