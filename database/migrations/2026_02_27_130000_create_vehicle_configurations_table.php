<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_configurations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('companies')->onDelete('cascade');
            $table->string('type', 60)->index(); // vehicle_brand, vehicle_model, vehicle_maintenance_type, vehicle_workshop
            $table->string('name');
            $table->json('meta')->nullable(); // { brand, ruc, address, phone, ... }
            $table->integer('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'type', 'name']);
            $table->index(['type', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_configurations');
    }
};

