<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('company_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->decimal('quantity_sold', 12, 3)->default(0);
            $table->decimal('total_revenue', 12, 2)->default(0);
            $table->decimal('total_cost', 12, 2)->default(0);
            $table->date('last_sale_date')->nullable();
            $table->integer('sale_count')->default(0); // Número de transacciones
            $table->timestamps();

            $table->unique('product_id');
            $table->index(['company_id', 'quantity_sold']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_sales');
    }
};

