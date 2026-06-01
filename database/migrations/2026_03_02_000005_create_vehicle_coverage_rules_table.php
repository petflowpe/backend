<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('vehicle_coverage_rules')) {
            return;
        }

        Schema::create('vehicle_coverage_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->foreignId('zone_id')->constrained('zones')->cascadeOnDelete();
            $table->json('districts');
            $table->json('days');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedTinyInteger('priority')->default(0);
            $table->unsignedSmallInteger('max_daily_appointments')->nullable();
            $table->boolean('active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['vehicle_id', 'active']);
            $table->index(['zone_id', 'active']);
            $table->index(['company_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_coverage_rules');
    }
};
