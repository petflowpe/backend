<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cobros de cita pueden registrarse antes de emitir CPE.
 * La migración 2026_06_02 solo dejaba invoice_id nullable al crear la tabla;
 * si ya existía (2025_09_15), quedó NOT NULL y bloquea register-payment.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('payments') || !Schema::hasColumn('payments', 'invoice_id')) {
            return;
        }

        // Quitar FK existente (nombre varía según motor/historial).
        $fk = collect(DB::select(
            'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
               AND REFERENCED_TABLE_NAME IS NOT NULL
             LIMIT 1',
            ['payments', 'invoice_id']
        ))->first();

        if ($fk && !empty($fk->CONSTRAINT_NAME)) {
            Schema::table('payments', function (Blueprint $table) use ($fk) {
                $table->dropForeign($fk->CONSTRAINT_NAME);
            });
        }

        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('invoice_id')->nullable()->change();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreign('invoice_id')
                ->references('id')
                ->on('invoices')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('payments') || !Schema::hasColumn('payments', 'invoice_id')) {
            return;
        }

        // No restaurar NOT NULL si hay filas con invoice_id null.
        $nulls = DB::table('payments')->whereNull('invoice_id')->count();
        if ($nulls > 0) {
            return;
        }

        $fk = collect(DB::select(
            'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
               AND REFERENCED_TABLE_NAME IS NOT NULL
             LIMIT 1',
            ['payments', 'invoice_id']
        ))->first();

        if ($fk && !empty($fk->CONSTRAINT_NAME)) {
            Schema::table('payments', function (Blueprint $table) use ($fk) {
                $table->dropForeign($fk->CONSTRAINT_NAME);
            });
        }

        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('invoice_id')->nullable(false)->change();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreign('invoice_id')
                ->references('id')
                ->on('invoices')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });
    }
};
