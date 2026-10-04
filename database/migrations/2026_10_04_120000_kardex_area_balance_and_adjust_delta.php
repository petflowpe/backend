<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            if (! Schema::hasColumn('stock_movements', 'area_id')) {
                $table->foreignId('area_id')->nullable()->after('branch_id')
                    ->constrained('areas')->cascadeOnUpdate()->nullOnDelete();
            }
            if (! Schema::hasColumn('stock_movements', 'balance_after')) {
                $table->decimal('balance_after', 12, 3)->nullable()->after('total_cost');
            }
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->index(['company_id', 'movement_date'], 'stock_movements_company_date_idx');
            $table->index(['product_id', 'movement_date'], 'stock_movements_product_date_idx');
        });

        // Ajustes antiguos guardaban el saldo absoluto del área; se convierte a diferencia
        // cuando la nota automática "Ajuste de stock: X -> Y" lo permite.
        DB::table('stock_movements')
            ->where('type', 'ADJUST')
            ->where('notes', 'like', 'Ajuste de stock:%->%')
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    if (! preg_match('/Ajuste de stock:\s*(-?[\d.]+)\s*->\s*(-?[\d.]+)/', (string) $row->notes, $m)) {
                        continue;
                    }
                    $delta = (float) $m[2] - (float) $m[1];
                    $unitCost = (float) ($row->unit_cost ?? 0);
                    DB::table('stock_movements')->where('id', $row->id)->update([
                        'quantity' => $delta,
                        'total_cost' => $unitCost * $delta,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex('stock_movements_company_date_idx');
            $table->dropIndex('stock_movements_product_date_idx');
            if (Schema::hasColumn('stock_movements', 'area_id')) {
                $table->dropConstrainedForeignId('area_id');
            }
            if (Schema::hasColumn('stock_movements', 'balance_after')) {
                $table->dropColumn('balance_after');
            }
        });
    }
};
