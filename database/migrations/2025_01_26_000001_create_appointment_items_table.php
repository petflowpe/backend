<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('appointment_items')) {
            return;
        }

        Schema::create('appointment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained('appointments')->onDelete('cascade');
            $table->foreignId('product_id')->nullable()->constrained('products')->onDelete('set null');
            $table->string('item_type', 20); // SERVICIO | PRODUCTO
            $table->string('name', 255);
            $table->integer('quantity')->default(1);
            $table->decimal('price', 10, 2);
            $table->integer('duration')->nullable(); // Solo para servicios (minutos)
            $table->decimal('subtotal', 10, 2);
            $table->timestamps();

            $table->index('appointment_id');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_items');
    }
};
