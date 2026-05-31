<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('products');
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            // Relación con empresa (catálogo por empresa)
            $table->foreignId('company_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // Identificación básica
            $table->string('code', 50); // Código interno (ej: AL-ROY-001)
            $table->string('name', 255);
            $table->text('description')->nullable();

            // Tipo de ítem (bien / servicio) para efectos SUNAT
            $table->string('item_type', 20)->default('PRODUCTO'); // PRODUCTO | SERVICIO

            // Información SUNAT / tributaria
            $table->string('unit', 10)->default('NIU'); // Unidad de medida SUNAT
            $table->string('currency', 3)->default('PEN');
            $table->decimal('unit_price', 12, 2)->default(0); // Precio de venta unitario
            $table->string('tax_affection', 2)->default('10'); // Código de afectación al IGV (10: gravado)
            $table->decimal('igv_rate', 5, 2)->default(18.00); // % IGV

            // Inventario básico (opcional, para productos físicos)
            $table->decimal('stock', 12, 3)->nullable();
            $table->decimal('min_stock', 12, 3)->nullable();
            $table->decimal('max_stock', 12, 3)->nullable();

            // Estado
            $table->boolean('active')->default(true);

            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};


