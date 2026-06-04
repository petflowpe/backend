<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('client_name');
            $table->string('pet_name')->nullable();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->string('service_name')->nullable();
            $table->string('staff_name')->nullable();
            $table->text('staff_response')->nullable();
            $table->boolean('verified')->default(true);
            $table->timestamps();

            $table->index(['company_id', 'created_at']);
            $table->index('rating');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_reviews');
    }
};
