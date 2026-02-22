<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pets', function (Blueprint $table) {
            // Características físicas adicionales
            if (!Schema::hasColumn('pets', 'size')) {
                $table->enum('size', ['Pequeño', 'Mediano', 'Grande', 'Gigante'])->nullable()->after('weight');
            }
            
            // Comportamiento y temperamento
            if (!Schema::hasColumn('pets', 'temperament')) {
                $table->string('temperament')->nullable()->after('color'); // JSON array o string
            }
            
            if (!Schema::hasColumn('pets', 'behavior')) {
                $table->text('behavior')->nullable()->after('temperament'); // JSON array de comportamientos
            }
            
            // Información médica adicional
            if (!Schema::hasColumn('pets', 'sterilized')) {
                $table->boolean('sterilized')->default(false)->after('fallecido');
            }
            
            if (!Schema::hasColumn('pets', 'sterilization_date')) {
                $table->date('sterilization_date')->nullable()->after('sterilized');
            }
            
            if (!Schema::hasColumn('pets', 'last_vaccination_date')) {
                $table->date('last_vaccination_date')->nullable()->after('sterilization_date');
            }
            
            if (!Schema::hasColumn('pets', 'next_vaccination_date')) {
                $table->date('next_vaccination_date')->nullable()->after('last_vaccination_date');
            }
            
            if (!Schema::hasColumn('pets', 'last_deworming_date')) {
                $table->date('last_deworming_date')->nullable()->after('next_vaccination_date');
            }
            
            if (!Schema::hasColumn('pets', 'next_deworming_date')) {
                $table->date('next_deworming_date')->nullable()->after('last_deworming_date');
            }
            
            // Información de seguro/plan
            if (!Schema::hasColumn('pets', 'insurance_company')) {
                $table->string('insurance_company')->nullable()->after('notes');
            }
            
            if (!Schema::hasColumn('pets', 'insurance_policy_number')) {
                $table->string('insurance_policy_number')->nullable()->after('insurance_company');
            }
            
            // Información de emergencia
            if (!Schema::hasColumn('pets', 'emergency_contact_name')) {
                $table->string('emergency_contact_name')->nullable()->after('insurance_policy_number');
            }
            
            if (!Schema::hasColumn('pets', 'emergency_contact_phone')) {
                $table->string('emergency_contact_phone')->nullable()->after('emergency_contact_name');
            }
            
            // Fechas importantes
            if (!Schema::hasColumn('pets', 'fecha_registro')) {
                $table->date('fecha_registro')->nullable()->after('birth_date');
            }
            
            if (!Schema::hasColumn('pets', 'fecha_ultima_visita')) {
                $table->date('fecha_ultima_visita')->nullable()->after('fecha_registro');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pets', function (Blueprint $table) {
            $columns = [
                'size', 'temperament', 'behavior', 'sterilized', 'sterilization_date',
                'last_vaccination_date', 'next_vaccination_date', 'last_deworming_date',
                'next_deworming_date', 'insurance_company', 'insurance_policy_number',
                'emergency_contact_name', 'emergency_contact_phone', 'fecha_registro',
                'fecha_ultima_visita'
            ];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('pets', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
