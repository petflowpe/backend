<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->string('name', 50); // Unidad, Kilo, Litro, etc.
            $table->string('abbreviation', 10); // UND, KG, L, etc.
            $table->string('sunat_code', 10)->nullable(); // Código SUNAT (NIU, KGM, LTR, etc.)
            $table->boolean('active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'abbreviation']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};

