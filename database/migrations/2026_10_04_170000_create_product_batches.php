<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'track_batches')) {
            Schema::table('products', function (Blueprint $table) {
                $table->boolean('track_batches')->default(false)->after('max_stock');
            });
        }

        if (! Schema::hasTable('product_batches')) {
            Schema::create('product_batches', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->foreignId('area_id')->constrained('areas')->cascadeOnDelete();
                $table->string('batch_number', 60);
                $table->date('expiry_date')->nullable();
                $table->decimal('quantity_initial', 12, 3)->default(0);
                $table->decimal('quantity_available', 12, 3)->default(0);
                $table->decimal('unit_cost', 12, 4)->nullable();
                $table->timestamp('received_at')->nullable();
                $table->string('source_type', 40)->nullable();
                $table->unsignedBigInteger('source_id')->nullable();
                $table->timestamps();

                $table->index(['product_id', 'area_id', 'expiry_date'], 'product_batches_fefo_idx');
                $table->index(['company_id', 'expiry_date'], 'product_batches_company_expiry_idx');
                $table->index(['source_type', 'source_id'], 'product_batches_source_idx');
            });
        }

        if (! Schema::hasTable('stock_movement_batches')) {
            Schema::create('stock_movement_batches', function (Blueprint $table) {
                $table->id();
                $table->foreignId('stock_movement_id')->constrained('stock_movements')->cascadeOnDelete();
                $table->foreignId('product_batch_id')->constrained('product_batches')->cascadeOnDelete();
                $table->decimal('quantity', 12, 3);
                $table->timestamps();

                $table->index('product_batch_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movement_batches');
        Schema::dropIfExists('product_batches');
        if (Schema::hasColumn('products', 'track_batches')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('track_batches');
            });
        }
    }
};
