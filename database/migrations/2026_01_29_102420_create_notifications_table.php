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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade'); // Target user
            $table->string('type', 50); // appointment, payment, inventory, etc.
            $table->string('priority', 20)->default('medium'); // low, medium, high, critical
            $table->string('category', 50)->nullable();
            $table->string('title');
            $table->text('message');
            $table->boolean('read')->default(false);
            $table->boolean('action_required')->default(false);
            $table->string('related_module', 50)->nullable();
            $table->string('related_id', 50)->nullable();
            $table->json('data')->nullable(); // Additional context
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
