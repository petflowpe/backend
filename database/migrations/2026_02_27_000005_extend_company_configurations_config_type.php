<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();
        if ($driver !== 'mysql') {
            // SQLite/PostgreSQL no soportan ALTER TABLE ... MODIFY ... ENUM de MySQL.
            // En SQLite, enum se guarda como texto y no requiere extensión del tipo.
            return;
        }

        // Extiende el ENUM para permitir configuraciones de calendario.
        // MySQL requiere redefinir el ENUM completo.
        DB::statement("
            ALTER TABLE company_configurations
            MODIFY config_type ENUM(
                'tax_settings',
                'invoice_settings',
                'gre_settings',
                'document_settings',
                'calendar_settings'
            ) NOT NULL
        ");
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();
        if ($driver !== 'mysql') {
            return;
        }

        // Si existen registros con calendar_settings, los eliminamos para poder revertir el ENUM.
        DB::table('company_configurations')->where('config_type', 'calendar_settings')->delete();

        DB::statement("
            ALTER TABLE company_configurations
            MODIFY config_type ENUM(
                'tax_settings',
                'invoice_settings',
                'gre_settings',
                'document_settings'
            ) NOT NULL
        ");
    }
};

