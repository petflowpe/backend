<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('vehicle_expenses')) {
            return;
        }

        Schema::create('vehicle_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('companies')->onDelete('cascade');
            $table->foreignId('vehicle_id')->constrained('vehicles')->onDelete('cascade');

            $table->string('category', 80);
            $table->decimal('amount', 10, 2)->default(0);
            $table->date('date');
            $table->text('description')->nullable();
            $table->string('account_code', 20)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['company_id', 'vehicle_id', 'date']);
            $table->index(['vehicle_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_expenses');
    }
};

