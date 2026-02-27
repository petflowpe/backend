<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
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

