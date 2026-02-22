<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pet_owners')) {
            return;
        }

        Schema::create('pet_owners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pet_id')->constrained('pets')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['pet_id', 'client_id']);
            $table->index(['client_id', 'pet_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pet_owners');
    }
};

