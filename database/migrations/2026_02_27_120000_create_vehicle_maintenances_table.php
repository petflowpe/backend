<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('vehicle_maintenances')) {
            return;
        }

        Schema::create('vehicle_maintenances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('companies')->onDelete('cascade');
            $table->foreignId('vehicle_id')->constrained('vehicles')->onDelete('cascade');
            $table->string('type', 120);
            $table->enum('status', ['completed', 'in_progress'])->default('completed');
            $table->text('description')->nullable();
            $table->date('date');
            $table->decimal('cost', 10, 2)->default(0);

            $table->string('workshop_ruc', 11)->nullable();
            $table->string('workshop_name', 255)->nullable();
            $table->string('workshop_address', 255)->nullable();
            $table->string('workshop_phone', 50)->nullable();

            $table->date('next_due')->nullable();
            $table->string('account_code', 20)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['company_id', 'vehicle_id', 'date']);
            $table->index(['vehicle_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_maintenances');
    }
};

