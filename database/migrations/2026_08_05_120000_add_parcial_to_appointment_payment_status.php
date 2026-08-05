<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Permite cobro parcial: Pendiente | Parcial | Pagado | Reembolsado.
 * En MySQL amplía el ENUM; en SQLite el tipo es flexible y no requiere ALTER.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('appointments')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE appointments MODIFY COLUMN payment_status ENUM('Pendiente', 'Parcial', 'Pagado', 'Reembolsado') NOT NULL DEFAULT 'Pendiente'"
            );
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('appointments')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::table('appointments')
                ->where('payment_status', 'Parcial')
                ->update(['payment_status' => 'Pendiente']);

            DB::statement(
                "ALTER TABLE appointments MODIFY COLUMN payment_status ENUM('Pendiente', 'Pagado', 'Reembolsado') NOT NULL DEFAULT 'Pendiente'"
            );
        }
    }
};
