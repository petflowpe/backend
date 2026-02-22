<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Verificar si la tabla ya existe antes de crearla
        if (Schema::hasTable('pets')) {
            return;
        }

        Schema::create('pets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');
            $table->foreignId('company_id')->nullable()->constrained('companies')->onDelete('cascade');
            $table->string('name');
            $table->enum('species', ['Perro', 'Gato', 'Otro'])->default('Perro');
            $table->string('breed')->nullable();
            $table->integer('age')->nullable(); // años
            $table->decimal('weight', 8, 2)->nullable(); // kg
            $table->enum('gender', ['Macho', 'Hembra'])->nullable();
            $table->string('color')->nullable();
            $table->string('microchip')->nullable();
            $table->text('allergies')->nullable(); // JSON array
            $table->text('medications')->nullable(); // JSON array
            $table->string('photo')->nullable();
            $table->boolean('fallecido')->default(false);
            $table->date('birth_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['client_id', 'fallecido']);
            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pets');
    }
};
