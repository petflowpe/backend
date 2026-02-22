<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // Teléfono adicional
            if (!Schema::hasColumn('clients', 'telefono2')) {
                $table->string('telefono2')->nullable()->after('telefono');
            }
            
            // Información personal adicional
            if (!Schema::hasColumn('clients', 'fecha_nacimiento')) {
                $table->date('fecha_nacimiento')->nullable()->after('email');
            }
            
            if (!Schema::hasColumn('clients', 'genero')) {
                $table->enum('genero', ['Masculino', 'Femenino', 'Otro'])->nullable()->after('fecha_nacimiento');
            }
            
            // Notas y observaciones
            if (!Schema::hasColumn('clients', 'notas')) {
                $table->text('notas')->nullable()->after('activo');
            }
            
            // Preferencias y configuraciones
            if (!Schema::hasColumn('clients', 'preferencias_contacto')) {
                $table->text('preferencias_contacto')->nullable()->after('notas'); // JSON: {preferencia: 'email', horario: '9-18'}
            }
            
            if (!Schema::hasColumn('clients', 'zona_preferida')) {
                $table->string('zona_preferida')->nullable()->after('distrito');
            }
            
            // Información de fidelización
            if (!Schema::hasColumn('clients', 'puntos_fidelizacion')) {
                $table->integer('puntos_fidelizacion')->default(0)->after('activo');
            }
            
            if (!Schema::hasColumn('clients', 'nivel_fidelizacion')) {
                $table->enum('nivel_fidelizacion', ['Bronce', 'Plata', 'Oro', 'VIP'])->nullable()->after('puntos_fidelizacion');
            }
            
            // Fechas importantes
            if (!Schema::hasColumn('clients', 'fecha_ultima_visita')) {
                $table->date('fecha_ultima_visita')->nullable()->after('fecha_nacimiento');
            }
            
            if (!Schema::hasColumn('clients', 'fecha_registro')) {
                $table->date('fecha_registro')->nullable()->after('fecha_ultima_visita');
            }
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $columns = [
                'telefono2', 'fecha_nacimiento', 'genero', 'notas', 
                'preferencias_contacto', 'zona_preferida', 'puntos_fidelizacion',
                'nivel_fidelizacion', 'fecha_ultima_visita', 'fecha_registro'
            ];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('clients', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
