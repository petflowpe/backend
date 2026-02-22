<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Verificar si la tabla ya existe antes de crearla
        if (Schema::hasTable('vehicles')) {
            return;
        }

        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('companies')->onDelete('cascade');
            $table->string('name'); // "Furgoneta 1", "Auto Compacto 2"
            $table->enum('type', ['furgoneta_grande', 'auto_compacto', 'camioneta', 'moto'])->default('furgoneta_grande');
            $table->string('placa')->nullable();
            $table->string('marca')->nullable();
            $table->string('modelo')->nullable();
            $table->integer('anio')->nullable();
            $table->string('color')->nullable();
            
            // Capacidad
            $table->integer('capacidad_slots')->default(10);
            $table->text('capacidad_por_categoria')->nullable(); // JSON: {"oro": 2.0, "bronce": 1.5, "plata": 1.0}
            
            // Zonas asignadas
            $table->text('zonas_asignadas')->nullable(); // JSON array de IDs
            
            // Disponibilidad
            $table->boolean('activo')->default(true);
            $table->text('horario_disponibilidad')->nullable(); // JSON con horarios por día
            
            // Mantenimiento
            $table->date('fecha_ultimo_mantenimiento')->nullable();
            $table->date('fecha_proximo_mantenimiento')->nullable();
            $table->text('equipamiento')->nullable(); // JSON array
            $table->text('notas_mantenimiento')->nullable();
            
            // GPS/Tracking
            $table->decimal('current_latitude', 10, 8)->nullable();
            $table->decimal('current_longitude', 11, 8)->nullable();
            $table->timestamp('last_location_update')->nullable();
            
            $table->timestamps();
            
            $table->index(['company_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
