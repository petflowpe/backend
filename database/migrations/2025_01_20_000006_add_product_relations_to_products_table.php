<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('company_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->after('unit')
                ->constrained('units')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->string('brand', 100)->nullable()->after('name');
            $table->string('barcode', 50)->nullable()->after('code');
            $table->string('supplier', 255)->nullable()->after('description');
            $table->decimal('cost_price', 12, 2)->nullable()->after('unit_price');
            $table->decimal('rating', 3, 2)->default(0)->after('max_stock');
            $table->integer('sold_count')->default(0)->after('rating');
            $table->date('last_restocked_at')->nullable()->after('sold_count');
            $table->json('metadata')->nullable()->after('active'); // Para datos adicionales flexibles
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropForeign(['unit_id']);
            $table->dropColumn([
                'category_id',
                'unit_id',
                'brand',
                'barcode',
                'supplier',
                'cost_price',
                'rating',
                'sold_count',
                'last_restocked_at',
                'metadata'
            ]);
        });
    }
};

