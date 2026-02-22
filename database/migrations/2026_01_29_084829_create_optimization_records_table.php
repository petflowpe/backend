<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('optimization_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->integer('appointments_count');
            $table->decimal('original_distance', 10, 2);
            $table->decimal('optimized_distance', 10, 2);
            $table->decimal('distance_saved', 10, 2);
            $table->decimal('time_saved', 10, 2); // minutes
            $table->decimal('fuel_saved', 10, 2); // liters
            $table->decimal('efficiency', 5, 2); // percentage
            $table->decimal('cost_saved', 10, 2); // currency
            $table->decimal('co2_saved', 10, 2); // kg
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('optimization_records');
    }
};
