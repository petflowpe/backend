<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_orders', 'subtotal')) {
                $table->decimal('subtotal', 12, 2)->default(0)->after('total');
            }
            if (! Schema::hasColumn('purchase_orders', 'igv_rate')) {
                $table->decimal('igv_rate', 5, 2)->default(18)->after('subtotal');
            }
            if (! Schema::hasColumn('purchase_orders', 'igv_amount')) {
                $table->decimal('igv_amount', 12, 2)->default(0)->after('igv_rate');
            }
            if (! Schema::hasColumn('purchase_orders', 'prices_include_igv')) {
                $table->boolean('prices_include_igv')->default(true)->after('igv_amount');
            }
            if (! Schema::hasColumn('purchase_orders', 'approval_status')) {
                $table->string('approval_status', 30)->default('not_required')->after('status');
            }
            if (! Schema::hasColumn('purchase_orders', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()->after('approval_status')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('purchase_orders', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            }
            if (! Schema::hasColumn('purchase_orders', 'approval_notes')) {
                $table->string('approval_notes', 500)->nullable()->after('approved_at');
            }
            if (! Schema::hasColumn('purchase_orders', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('notes');
            }
            if (! Schema::hasColumn('purchase_orders', 'cancellation_reason')) {
                $table->string('cancellation_reason', 500)->nullable()->after('cancelled_at');
            }
            if (! Schema::hasColumn('purchase_orders', 'email_sent_at')) {
                $table->timestamp('email_sent_at')->nullable()->after('cancellation_reason');
            }
            if (! Schema::hasColumn('purchase_orders', 'default_area_id')) {
                $table->unsignedBigInteger('default_area_id')->nullable()->after('supplier_id');
            }
        });

        if (! Schema::hasTable('supplier_product_price_history')) {
            Schema::create('supplier_product_price_history', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
                $table->decimal('unit_cost', 12, 4);
                $table->decimal('previous_unit_cost', 12, 4)->nullable();
                $table->decimal('variation_percent', 8, 2)->nullable();
                $table->boolean('price_alert')->default(false);
                $table->timestamp('recorded_at')->useCurrent();
                $table->timestamps();
                $table->index(['company_id', 'product_id', 'supplier_id']);
            });
        }

        if (! Schema::hasTable('purchase_payables')) {
            Schema::create('purchase_payables', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
                $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
                $table->string('status', 20)->default('open'); // open|partial|closed
                $table->decimal('original_amount', 12, 2);
                $table->decimal('paid_amount', 12, 2)->default(0);
                $table->decimal('balance', 12, 2);
                $table->string('accounting_account_code', 40)->nullable();
                $table->date('due_date')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->unique('purchase_order_id');
            });
        }

        if (! Schema::hasTable('company_purchase_settings')) {
            Schema::create('company_purchase_settings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete()->unique();
                $table->decimal('approval_threshold', 12, 2)->default(0); // 0 = off
                $table->decimal('price_alert_percent', 8, 2)->default(10);
                $table->unsignedSmallInteger('delivery_alert_days')->default(2);
                $table->decimal('default_igv_rate', 5, 2)->default(18);
                $table->boolean('prices_include_igv')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('company_purchase_settings');
        Schema::dropIfExists('purchase_payables');
        Schema::dropIfExists('supplier_product_price_history');

        Schema::table('purchase_orders', function (Blueprint $table) {
            foreach ([
                'subtotal', 'igv_rate', 'igv_amount', 'prices_include_igv',
                'approval_status', 'approved_by', 'approved_at', 'approval_notes',
                'cancelled_at', 'cancellation_reason', 'email_sent_at', 'default_area_id',
            ] as $col) {
                if (Schema::hasColumn('purchase_orders', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
