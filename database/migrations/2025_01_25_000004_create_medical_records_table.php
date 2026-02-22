<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Verificar si la tabla ya existe antes de crearla
        if (Schema::hasTable('medical_records')) {
            return;
        }

        Schema::create('medical_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pet_id')->constrained('pets')->onDelete('cascade');
            $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');
            $table->foreignId('company_id')->nullable()->constrained('companies')->onDelete('cascade');
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->onDelete('set null');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null'); // Veterinario
            
            $table->date('date');
            $table->enum('type', ['Consulta', 'Vacunación', 'Cirugía', 'Emergencia', 'Chequeo', 'Laboratorio', 'Desparasitación', 'Tratamiento']);
            $table->string('title')->nullable();
            $table->text('description');
            $table->text('diagnosis')->nullable();
            $table->text('treatment')->nullable();
            $table->text('prescription')->nullable(); // JSON array de medicamentos
            $table->text('attachments')->nullable(); // JSON array de URLs de archivos
            $table->decimal('weight', 8, 2)->nullable(); // Peso al momento del registro
            $table->decimal('temperature', 5, 2)->nullable(); // Temperatura
            $table->text('vital_signs')->nullable(); // JSON con signos vitales
            $table->text('notes')->nullable();
            
            $table->timestamps();
            
            $table->index(['pet_id', 'date']);
            $table->index(['client_id', 'type']);
            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_records');
    }
};
