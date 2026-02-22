<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Verificar si la tabla ya existe antes de crearla
        if (Schema::hasTable('appointments')) {
            return;
        }

        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');
            $table->foreignId('pet_id')->constrained('pets')->onDelete('cascade');
            $table->foreignId('company_id')->nullable()->constrained('companies')->onDelete('cascade');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->onDelete('set null');
            $table->unsignedBigInteger('vehicle_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null'); // Veterinario/Groomer
            
            // Información del servicio
            $table->string('service_type'); // movilvet-vacunacion, peluqueria-bano-basico, etc.
            $table->string('service_name');
            $table->enum('service_category', ['MovilVet', 'Peluquería']);
            
            // Fecha y hora
            $table->date('date');
            $table->time('time');
            $table->integer('duration')->default(60); // minutos
            
            // Ubicación
            $table->string('address');
            $table->string('district')->nullable();
            $table->string('province')->nullable();
            $table->string('department')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            
            // Estado y precio
            $table->enum('status', ['Pendiente', 'Confirmada', 'En Proceso', 'Completada', 'Cancelada'])->default('Pendiente');
            $table->decimal('price', 10, 2);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            
            // Pago
            $table->enum('payment_status', ['Pendiente', 'Pagado', 'Reembolsado'])->default('Pendiente');
            $table->enum('payment_method', ['Efectivo', 'Tarjeta', 'Yape', 'Plin', 'Transferencia'])->nullable();
            
            // Notas
            $table->text('notes')->nullable();
            $table->text('cancellation_reason')->nullable();
            
            // Metadata
            $table->string('client_category')->nullable(); // Oro, Bronce, Plata
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            
            $table->index(['date', 'status']);
            $table->index(['client_id', 'status']);
            $table->index(['company_id', 'date']);
            $table->index('vehicle_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
