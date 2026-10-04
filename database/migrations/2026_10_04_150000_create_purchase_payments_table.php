<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('purchase_payments')) {
            Schema::create('purchase_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
                $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
                $table->decimal('amount', 12, 2);
                $table->string('payment_method', 40)->default('cash');
                $table->string('reference', 100)->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('paid_at');
                $table->foreignId('cash_movement_id')->nullable()->constrained('cash_movements')->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['company_id', 'paid_at'], 'purchase_payments_company_date_idx');
                $table->index('purchase_order_id');
            });
        }

        // Órdenes con pagos previos al historial: un registro consolidado para que la suma cuadre con amount_paid.
        $now = now();
        DB::table('purchase_orders')
            ->where('amount_paid', '>', 0)
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')->from('purchase_payments')->whereColumn('purchase_payments.purchase_order_id', 'purchase_orders.id');
            })
            ->orderBy('id')
            ->chunk(500, function ($orders) use ($now) {
                $rows = [];
                foreach ($orders as $order) {
                    $rows[] = [
                        'company_id' => $order->company_id,
                        'purchase_order_id' => $order->id,
                        'supplier_id' => $order->supplier_id,
                        'amount' => $order->amount_paid,
                        'payment_method' => 'other',
                        'reference' => 'Saldo histórico',
                        'notes' => 'Pagos registrados antes del historial detallado',
                        'paid_at' => $order->paid_at ?? $order->updated_at ?? $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                if ($rows) {
                    DB::table('purchase_payments')->insert($rows);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_payments');
    }
};
