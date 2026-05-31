<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->string('codigo', 10);
            $table->string('nombre');
            $table->string('direccion');
            $table->string('ubigeo', 6);
            $table->string('distrito');
            $table->string('provincia');
            $table->string('departamento');
            $table->string('telefono')->nullable();
            $table->string('email')->nullable();

            $table->json('series_factura')->nullable();
            $table->json('series_boleta')->nullable();
            $table->json('series_nota_credito')->nullable();
            $table->json('series_nota_debito')->nullable();
            $table->json('series_guia_remision')->nullable();

            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'codigo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
