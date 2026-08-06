<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_orders', 'invoice_attachment_path')) {
                $table->string('invoice_attachment_path', 500)->nullable()->after('invoice_total');
            }
            if (! Schema::hasColumn('purchase_orders', 'invoice_attachment_name')) {
                $table->string('invoice_attachment_name', 255)->nullable()->after('invoice_attachment_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            foreach (['invoice_attachment_path', 'invoice_attachment_name'] as $col) {
                if (Schema::hasColumn('purchase_orders', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
