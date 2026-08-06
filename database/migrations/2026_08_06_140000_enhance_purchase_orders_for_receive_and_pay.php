<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_orders', 'order_number')) {
                $table->string('order_number', 40)->nullable()->after('supplier_id')->index();
            }
            if (! Schema::hasColumn('purchase_orders', 'payment_status')) {
                $table->string('payment_status', 20)->default('unpaid')->after('kardex_registered');
            }
            if (! Schema::hasColumn('purchase_orders', 'amount_paid')) {
                $table->decimal('amount_paid', 12, 2)->default(0)->after('payment_status');
            }
            if (! Schema::hasColumn('purchase_orders', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('amount_paid');
            }
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_order_items', 'quantity_received')) {
                $table->decimal('quantity_received', 12, 3)->default(0)->after('quantity');
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            foreach (['order_number', 'payment_status', 'amount_paid', 'paid_at'] as $col) {
                if (Schema::hasColumn('purchase_orders', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_order_items', 'quantity_received')) {
                $table->dropColumn('quantity_received');
            }
        });
    }
};
