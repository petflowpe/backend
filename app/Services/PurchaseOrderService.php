<?php

namespace App\Services;

use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Exception;
use Illuminate\Support\Facades\DB;

class PurchaseOrderService
{
    public function __construct(
        private ProductService $productService,
    ) {
    }

    public function nextOrderNumber(int $companyId): string
    {
        $prefix = 'OC-' . now()->format('ymd') . '-';
        $last = PurchaseOrder::query()
            ->where('company_id', $companyId)
            ->where('order_number', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('order_number');

        $seq = 1;
        if ($last && preg_match('/-(\d+)$/', $last, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Recibe mercadería (parcial o total). Descuenta pendiente y hace IN al kardex.
     *
     * @param  array<int, array{item_id?: int, product_id?: int, quantity: float|int}>  $lines
     */
    public function receive(PurchaseOrder $order, array $lines, array $invoice = [], ?int $userId = null): PurchaseOrder
    {
        if (in_array($order->status, ['cancelled'], true)) {
            throw new Exception('No se puede recibir una orden cancelada');
        }

        return DB::transaction(function () use ($order, $lines, $invoice, $userId) {
            $order->loadMissing('items.product');
            $receivedAny = false;

            foreach ($lines as $line) {
                $qty = (float) ($line['quantity'] ?? 0);
                if ($qty <= 0) {
                    continue;
                }

                /** @var PurchaseOrderItem|null $item */
                $item = null;
                if (! empty($line['item_id'])) {
                    $item = $order->items->firstWhere('id', (int) $line['item_id']);
                } elseif (! empty($line['product_id'])) {
                    $item = $order->items->firstWhere('product_id', (int) $line['product_id']);
                }
                if (! $item) {
                    continue;
                }

                $ordered = (float) $item->quantity;
                $already = (float) ($item->quantity_received ?? 0);
                $pending = max(0, $ordered - $already);
                if ($pending <= 0) {
                    continue;
                }

                $toReceive = min($qty, $pending);
                $item->quantity_received = $already + $toReceive;
                $item->save();

                $product = Product::find($item->product_id);
                if ($product) {
                    $unitCost = (float) $item->unit_cost;
                    $this->productService->adjustStock(
                        $product,
                        null,
                        $toReceive,
                        'IN',
                        'Entrada por OC ' . ($order->order_number ?? '#' . $order->id),
                        [
                            'wrap_transaction' => false,
                            'source_type' => 'purchase',
                            'source_id' => $order->id,
                            'unit_cost' => $unitCost,
                            'created_by' => $userId,
                        ]
                    );
                    $this->applyWeightedAverageCost($product, $toReceive, $unitCost);
                }
                $receivedAny = true;
            }

            if (! $receivedAny) {
                throw new Exception('No hay cantidades pendientes por recibir');
            }

            $order->refresh()->load('items');
            $allDone = $order->items->every(function (PurchaseOrderItem $it) {
                return (float) ($it->quantity_received ?? 0) + 0.0001 >= (float) $it->quantity;
            });
            $anyReceived = $order->items->contains(fn (PurchaseOrderItem $it) => (float) ($it->quantity_received ?? 0) > 0);

            $updates = [];
            if (! empty($invoice['invoice_number'])) {
                $updates['invoice_number'] = $invoice['invoice_number'];
            }
            if (! empty($invoice['invoice_date'])) {
                $updates['invoice_date'] = $invoice['invoice_date'];
            }
            if (array_key_exists('invoice_total', $invoice) && $invoice['invoice_total'] !== null) {
                $updates['invoice_total'] = (float) $invoice['invoice_total'];
            }

            if ($allDone) {
                $updates['status'] = 'delivered';
                $updates['kardex_registered'] = true;
            } elseif ($anyReceived) {
                $updates['status'] = 'partial';
                $updates['kardex_registered'] = false;
            }

            if ($updates) {
                $order->update($updates);
            }

            return $order->fresh(['supplier', 'items.product']);
        });
    }

    /**
     * Recibe el saldo pendiente de todas las líneas (compatible con complete anterior).
     */
    public function receiveAllRemaining(PurchaseOrder $order, array $invoice = [], ?int $userId = null): PurchaseOrder
    {
        $order->loadMissing('items');
        $lines = [];
        foreach ($order->items as $item) {
            $pending = (float) $item->quantity - (float) ($item->quantity_received ?? 0);
            if ($pending > 0) {
                $lines[] = [
                    'item_id' => $item->id,
                    'quantity' => $pending,
                ];
            }
        }

        if ($lines === []) {
            if ($order->kardex_registered || $order->status === 'delivered') {
                throw new Exception('Esta orden ya fue recibida por completo');
            }
            throw new Exception('No hay ítems pendientes por recibir');
        }

        return $this->receive($order, $lines, $invoice, $userId);
    }

    /**
     * Registra pago a proveedor. Opcionalmente crea egreso en caja abierta.
     */
    public function registerPayment(
        PurchaseOrder $order,
        float $amount,
        array $options = [],
        ?int $userId = null
    ): PurchaseOrder {
        if ($amount <= 0) {
            throw new Exception('El monto de pago debe ser mayor a 0');
        }

        $total = (float) ($order->invoice_total ?? $order->total);
        $paid = (float) ($order->amount_paid ?? 0);
        $remaining = max(0, $total - $paid);
        if ($amount > $remaining + 0.01) {
            throw new Exception('El monto excede el saldo pendiente (S/ ' . number_format($remaining, 2) . ')');
        }

        return DB::transaction(function () use ($order, $amount, $options, $userId, $total, $paid) {
            $newPaid = $paid + $amount;
            $status = $newPaid + 0.01 >= $total ? 'paid' : 'partial';

            $order->update([
                'amount_paid' => $newPaid,
                'payment_status' => $status,
                'paid_at' => $status === 'paid' ? now() : $order->paid_at,
            ]);

            if (! empty($options['post_to_cash'])) {
                $session = CashSession::query()
                    ->where('company_id', $order->company_id)
                    ->where('status', 'OPEN')
                    ->when(! empty($options['cash_session_id']), fn ($q) => $q->where('id', $options['cash_session_id']))
                    ->orderByDesc('id')
                    ->first();

                if (! $session) {
                    throw new Exception('No hay caja abierta para registrar el egreso');
                }

                CashMovement::create([
                    'company_id' => $order->company_id,
                    'branch_id' => $session->branch_id,
                    'user_id' => $userId,
                    'cash_session_id' => $session->id,
                    'type' => 'EXPENSE',
                    'amount' => $amount,
                    'description' => 'Pago proveedor OC ' . ($order->order_number ?? '#' . $order->id),
                    'payment_method' => $options['payment_method'] ?? 'cash',
                    'reference' => $order->order_number ?? ('PO-' . $order->id),
                    'movement_date' => now(),
                    'metadata' => [
                        'purchase_order_id' => $order->id,
                        'supplier_id' => $order->supplier_id,
                    ],
                ]);
            }

            return $order->fresh(['supplier', 'items.product']);
        });
    }

    private function applyWeightedAverageCost(Product $product, float $qtyIn, float $unitCost): void
    {
        $product->refresh();
        $stockAfter = (float) ($product->stock ?? 0);
        $stockBefore = max(0, $stockAfter - $qtyIn);
        $oldCost = (float) ($product->cost_price ?? 0);

        if ($stockAfter <= 0) {
            $product->update(['cost_price' => $unitCost]);

            return;
        }

        $newCost = (($stockBefore * $oldCost) + ($qtyIn * $unitCost)) / $stockAfter;
        $product->update(['cost_price' => round($newCost, 4)]);
    }
}
