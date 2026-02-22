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
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('code')->unique();
            $table->string('description')->nullable();
            $table->string('category')->nullable();
            $table->string('area')->nullable();
            $table->boolean('active')->default(true);

            // Pricing logic
            $table->boolean('pricing_by_size')->default(false);
            $table->json('pricing')->nullable(); // {toy: {price, cost, duration}, ...}
            $table->json('breed_exceptions')->nullable(); // [{breed, type, value, note}, ...]

            // Operations logic
            $table->json('required_products')->nullable(); // [{product_id, quantity}, ...]

            $table->timestamps();

            $table->index(['company_id', 'active']);
            $table->index('code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
