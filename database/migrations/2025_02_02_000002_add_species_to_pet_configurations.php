<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Cambiar enum a string para permitir 'species' sin alterar enum en MySQL
        if (Schema::hasTable('pet_configurations') && Schema::hasColumn('pet_configurations', 'type')) {
            DB::statement("ALTER TABLE pet_configurations MODIFY COLUMN type VARCHAR(50) NOT NULL");
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pet_configurations') && Schema::hasColumn('pet_configurations', 'type')) {
            DB::statement("ALTER TABLE pet_configurations MODIFY COLUMN type ENUM('dog_breed', 'cat_breed', 'temperament', 'behavior') NOT NULL");
        }
    }
};
