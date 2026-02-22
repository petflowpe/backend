<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Verificar si la tabla ya existe antes de crearla
        if (Schema::hasTable('vaccine_records')) {
            return;
        }

        Schema::create('vaccine_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pet_id')->constrained('pets')->onDelete('cascade');
            $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');
            $table->foreignId('company_id')->nullable()->constrained('companies')->onDelete('cascade');
            $table->foreignId('medical_record_id')->nullable()->constrained('medical_records')->onDelete('set null');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null'); // Veterinario
            
            $table->string('name'); // Nombre de la vacuna
            $table->date('date'); // Fecha de aplicación
            $table->date('next_due_date')->nullable(); // Próxima fecha de aplicación
            $table->string('veterinarian')->nullable();
            $table->string('lot')->nullable(); // Lote de la vacuna
            $table->string('manufacturer')->nullable(); // Fabricante
            $table->text('notes')->nullable();
            
            $table->timestamps();
            
            $table->index(['pet_id', 'date']);
            $table->index('next_due_date'); // Para alertas de próximas vacunas
            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vaccine_records');
    }
};
