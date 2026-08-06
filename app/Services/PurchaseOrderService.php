<?php

namespace App\Services;

use App\Mail\PurchaseOrderMail;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\CompanyPurchaseSetting;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchasePayable;
use App\Models\SupplierProductPriceHistory;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

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
     * @return array{subtotal: float, igv_amount: float, total: float, igv_rate: float, prices_include_igv: bool}
     */
    public function computeTaxes(float $linesTotal, int $companyId, ?array $overrides = null): array
    {
        $settings = CompanyPurchaseSetting::forCompany($companyId);
        $rate = (float) ($overrides['igv_rate'] ?? $settings->default_igv_rate ?? 18);
        $include = array_key_exists('prices_include_igv', $overrides ?? [])
            ? (bool) $overrides['prices_include_igv']
            : (bool) $settings->prices_include_igv;

        if ($include) {
            $total = round($linesTotal, 2);
            $subtotal = round($total / (1 + $rate / 100), 2);
            $igv = round($total - $subtotal, 2);
        } else {
            $subtotal = round($linesTotal, 2);
            $igv = round($subtotal * ($rate / 100), 2);
            $total = round($subtotal + $igv, 2);
        }

        return [
            'subtotal' => $subtotal,
            'igv_amount' => $igv,
            'total' => $total,
            'igv_rate' => $rate,
            'prices_include_igv' => $include,
        ];
    }

    public function resolveApprovalStatus(int $companyId, float $total): string
    {
        $threshold = (float) CompanyPurchaseSetting::forCompany($companyId)->approval_threshold;
        if ($threshold > 0 && $total >= $threshold) {
            return 'pending_approval';
        }

        return 'not_required';
    }

    /**
     * Sugerencias de reposición agrupadas por proveedor preferido.
     *
     * @return array<int, array{supplier_id: int, supplier_name: string, items: array<int, array{product_id: int, name: string, quantity: float, unit_cost: float, stock: float, min_stock: float}>}>
     */
    public function suggestRestock(int $companyId): array
    {
        $products = Product::query()
            ->where('company_id', $companyId)
            ->where('active', true)
            ->whereNotNull('min_stock')
            ->where('min_stock', '>', 0)
            ->whereColumn('stock', '<=', 'min_stock')
            ->whereNotNull('supplier_id')
            ->with('supplier:id,name')
            ->get();

        $groups = [];
        foreach ($products as $product) {
            $sid = (int) $product->supplier_id;
            if (! isset($groups[$sid])) {
                $groups[$sid] = [
                    'supplier_id' => $sid,
                    'supplier_name' => $product->supplier?->name ?? 'Proveedor',
                    'items' => [],
                ];
            }
            $need = max(1, (float) $product->min_stock - (float) $product->stock);
            // pedir hasta cubrir mínimo (o al menos el déficit)
            $qty = max($need, (float) $product->min_stock);
            $groups[$sid]['items'][] = [
                'product_id' => $product->id,
                'name' => $product->name,
                'code' => $product->code,
                'quantity' => round($qty, 3),
                'unit_cost' => (float) ($product->cost_price ?? 0),
                'stock' => (float) $product->stock,
                'min_stock' => (float) $product->min_stock,
            ];
        }

        return array_values($groups);
    }

    /**
     * Crea una o varias OC a partir de sugerencias (una por proveedor).
     *
     * @param  array<int, array{supplier_id: int, items: array<int, array{product_id: int, quantity: float|int, unit_cost?: float}>}>  $groups
     * @return Collection<int, PurchaseOrder>
     */
    public function createFromRestockSuggestions(int $companyId, array $groups, ?int $userId = null): Collection
    {
        $created = collect();

        DB::transaction(function () use ($companyId, $groups, $userId, &$created) {
            foreach ($groups as $group) {
                $supplierId = (int) ($group['supplier_id'] ?? 0);
                $items = $group['items'] ?? [];
                if ($supplierId <= 0 || $items === []) {
                    continue;
                }

                $linesTotal = 0;
                $normalized = [];
                foreach ($items as $row) {
                    $pid = (int) ($row['product_id'] ?? 0);
                    $qty = (float) ($row['quantity'] ?? 0);
                    $cost = (float) ($row['unit_cost'] ?? 0);
                    if ($pid <= 0 || $qty <= 0) {
                        continue;
                    }
                    if ($cost <= 0) {
                        $cost = (float) (Product::find($pid)?->cost_price ?? 0);
                    }
                    $linesTotal += $qty * $cost;
                    $normalized[] = compact('pid', 'qty', 'cost');
                }
                if ($normalized === []) {
                    continue;
                }

                $taxes = $this->computeTaxes($linesTotal, $companyId);
                $approval = $this->resolveApprovalStatus($companyId, $taxes['total']);

                $order = PurchaseOrder::create([
                    'company_id' => $companyId,
                    'supplier_id' => $supplierId,
                    'order_number' => $this->nextOrderNumber($companyId),
                    'order_date' => now()->toDateString(),
                    'delivery_date' => now()->addDays(7)->toDateString(),
                    'status' => 'pending',
                    'approval_status' => $approval,
                    'subtotal' => $taxes['subtotal'],
                    'igv_rate' => $taxes['igv_rate'],
                    'igv_amount' => $taxes['igv_amount'],
                    'prices_include_igv' => $taxes['prices_include_igv'],
                    'total' => $taxes['total'],
                    'payment_status' => 'unpaid',
                    'amount_paid' => 0,
                    'notes' => 'Generada por reposición automática de stock bajo',
                    'created_by' => $userId,
                ]);

                foreach ($normalized as $n) {
                    PurchaseOrderItem::create([
                        'purchase_order_id' => $order->id,
                        'product_id' => $n['pid'],
                        'quantity' => $n['qty'],
                        'quantity_received' => 0,
                        'unit_cost' => $n['cost'],
                        'total_cost' => round($n['qty'] * $n['cost'], 2),
                    ]);
                }

                $created->push($order->fresh(['supplier', 'items.product']));
            }
        });

        return $created;
    }

    /**
     * @param  array<int, array{item_id?: int, product_id?: int, quantity: float|int, area_id?: int|null}>  $lines
     */
    public function receive(
        PurchaseOrder $order,
        array $lines,
        array $invoice = [],
        ?int $userId = null,
        ?int $defaultAreaId = null
    ): PurchaseOrder {
        if (in_array($order->status, ['cancelled'], true)) {
            throw new Exception('No se puede recibir una orden cancelada');
        }
        if (in_array($order->approval_status, ['pending_approval', 'rejected'], true)) {
            throw new Exception('La orden requiere aprobación antes de recibir mercadería');
        }

        return DB::transaction(function () use ($order, $lines, $invoice, $userId, $defaultAreaId) {
            $order->loadMissing('items.product');
            $receivedAny = false;
            $settings = CompanyPurchaseSetting::forCompany((int) $order->company_id);
            $alertPct = (float) $settings->price_alert_percent;

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

                $areaId = ! empty($line['area_id'])
                    ? (int) $line['area_id']
                    : ($defaultAreaId ?: $order->default_area_id);

                $product = Product::find($item->product_id);
                if ($product) {
                    $unitCost = (float) $item->unit_cost;
                    $this->productService->adjustStock(
                        $product,
                        $areaId ? (int) $areaId : null,
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
                    $this->recordPriceHistory($order, $product, $unitCost, $alertPct);
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
            if ($defaultAreaId) {
                $updates['default_area_id'] = $defaultAreaId;
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

            $this->syncPayable($order->fresh());

            return $order->fresh(['supplier', 'items.product']);
        });
    }

    public function receiveAllRemaining(
        PurchaseOrder $order,
        array $invoice = [],
        ?int $userId = null,
        ?int $defaultAreaId = null
    ): PurchaseOrder {
        $order->loadMissing('items');
        $lines = [];
        foreach ($order->items as $item) {
            $pending = (float) $item->quantity - (float) ($item->quantity_received ?? 0);
            if ($pending > 0) {
                $lines[] = [
                    'item_id' => $item->id,
                    'quantity' => $pending,
                    'area_id' => $defaultAreaId,
                ];
            }
        }

        if ($lines === []) {
            if ($order->kardex_registered || $order->status === 'delivered') {
                throw new Exception('Esta orden ya fue recibida por completo');
            }
            throw new Exception('No hay ítems pendientes por recibir');
        }

        return $this->receive($order, $lines, $invoice, $userId, $defaultAreaId);
    }

    /**
     * Anulación controlada: revierte kardex de cantidades recibidas y marca cancelled.
     */
    public function cancel(PurchaseOrder $order, string $reason, ?int $userId = null): PurchaseOrder
    {
        if ($order->status === 'cancelled') {
            throw new Exception('La orden ya está cancelada');
        }

        return DB::transaction(function () use ($order, $reason, $userId) {
            $order->loadMissing('items.product');

            foreach ($order->items as $item) {
                $received = (float) ($item->quantity_received ?? 0);
                if ($received <= 0) {
                    continue;
                }
                $product = Product::find($item->product_id);
                if (! $product) {
                    continue;
                }
                $this->productService->adjustStock(
                    $product,
                    $order->default_area_id ? (int) $order->default_area_id : null,
                    $received,
                    'OUT',
                    'Reverso por anulación OC ' . ($order->order_number ?? '#' . $order->id) . ': ' . $reason,
                    [
                        'wrap_transaction' => false,
                        'source_type' => 'purchase_cancel',
                        'source_id' => $order->id,
                        'unit_cost' => (float) $item->unit_cost,
                        'created_by' => $userId,
                    ]
                );
                $item->update(['quantity_received' => 0]);
            }

            $order->update([
                'status' => 'cancelled',
                'kardex_registered' => false,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);

            PurchasePayable::where('purchase_order_id', $order->id)->update([
                'status' => 'closed',
                'balance' => 0,
            ]);

            return $order->fresh(['supplier', 'items.product']);
        });
    }

    public function approve(PurchaseOrder $order, ?int $userId = null, ?string $notes = null): PurchaseOrder
    {
        if ($order->approval_status !== 'pending_approval') {
            throw new Exception('La orden no está pendiente de aprobación');
        }
        $order->update([
            'approval_status' => 'approved',
            'approved_by' => $userId,
            'approved_at' => now(),
            'approval_notes' => $notes,
        ]);

        return $order->fresh(['supplier', 'items.product']);
    }

    public function reject(PurchaseOrder $order, ?int $userId = null, ?string $notes = null): PurchaseOrder
    {
        if ($order->approval_status !== 'pending_approval') {
            throw new Exception('La orden no está pendiente de aprobación');
        }
        $order->update([
            'approval_status' => 'rejected',
            'approved_by' => $userId,
            'approved_at' => now(),
            'approval_notes' => $notes,
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => 'Rechazada en aprobación: ' . ($notes ?: 'sin notas'),
        ]);

        return $order->fresh(['supplier', 'items.product']);
    }

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

            $this->syncPayable($order->fresh());

            return $order->fresh(['supplier', 'items.product', 'payable']);
        });
    }

    public function syncPayable(PurchaseOrder $order): PurchasePayable
    {
        $order->loadMissing('supplier');
        $original = (float) ($order->invoice_total ?? $order->total);
        $paid = (float) ($order->amount_paid ?? 0);
        $balance = max(0, $original - $paid);
        $status = $balance <= 0.01 ? 'closed' : ($paid > 0 ? 'partial' : 'open');

        $creditDays = (int) ($order->supplier?->credit_days ?? 0);
        $due = $order->invoice_date
            ? $order->invoice_date->copy()->addDays($creditDays)
            : ($order->order_date?->copy()->addDays($creditDays));

        return PurchasePayable::updateOrCreate(
            ['purchase_order_id' => $order->id],
            [
                'company_id' => $order->company_id,
                'supplier_id' => $order->supplier_id,
                'status' => $order->status === 'cancelled' ? 'closed' : $status,
                'original_amount' => $original,
                'paid_amount' => $paid,
                'balance' => $order->status === 'cancelled' ? 0 : $balance,
                'accounting_account_code' => $order->supplier?->accounting_account_code,
                'due_date' => $due?->toDateString(),
                'metadata' => [
                    'order_number' => $order->order_number,
                    'invoice_number' => $order->invoice_number,
                ],
            ]
        );
    }

    /**
     * @return array{overdue: array, due_soon: array}
     */
    public function deliveryAlerts(int $companyId): array
    {
        $days = (int) CompanyPurchaseSetting::forCompany($companyId)->delivery_alert_days;
        $today = now()->startOfDay();
        $soonLimit = now()->addDays(max(1, $days))->endOfDay();

        $open = PurchaseOrder::query()
            ->with(['supplier:id,name'])
            ->where('company_id', $companyId)
            ->whereIn('status', ['pending', 'in_transit', 'partial'])
            ->whereNotNull('delivery_date')
            ->orderBy('delivery_date')
            ->get();

        $overdue = [];
        $dueSoon = [];
        foreach ($open as $order) {
            $delivery = $order->delivery_date?->startOfDay();
            if (! $delivery) {
                continue;
            }
            $row = [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'supplier' => $order->supplier?->name,
                'delivery_date' => $delivery->toDateString(),
                'status' => $order->status,
                'total' => (float) $order->total,
                'days_delta' => $today->diffInDays($delivery, false),
            ];
            if ($delivery->lt($today)) {
                $overdue[] = $row;
            } elseif ($delivery->lte($soonLimit)) {
                $dueSoon[] = $row;
            }
        }

        return compact('overdue', 'dueSoon');
    }

    public function priceHistory(int $companyId, ?int $productId = null, ?int $supplierId = null, int $limit = 50)
    {
        $q = SupplierProductPriceHistory::query()
            ->with(['product:id,name,code', 'supplier:id,name'])
            ->where('company_id', $companyId)
            ->orderByDesc('recorded_at')
            ->limit($limit);

        if ($productId) {
            $q->where('product_id', $productId);
        }
        if ($supplierId) {
            $q->where('supplier_id', $supplierId);
        }

        return $q->get();
    }

    public function sendToSupplier(PurchaseOrder $order): PurchaseOrder
    {
        $order->loadMissing(['supplier', 'company', 'items.product']);
        $email = $order->supplier?->billing_email ?: $order->supplier?->email;
        if (! $email) {
            throw new Exception('El proveedor no tiene email de facturación ni contacto');
        }

        Mail::to($email)->send(new PurchaseOrderMail($order));
        $order->update(['email_sent_at' => now()]);

        return $order->fresh(['supplier', 'items.product']);
    }

    public function findProductByBarcode(int $companyId, string $code): ?Product
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }

        return Product::query()
            ->where('company_id', $companyId)
            ->where(function ($q) use ($code) {
                $q->where('code', $code)->orWhere('sku', $code);
            })
            ->first();
    }

    private function recordPriceHistory(PurchaseOrder $order, Product $product, float $unitCost, float $alertPct): void
    {
        $prev = SupplierProductPriceHistory::query()
            ->where('company_id', $order->company_id)
            ->where('supplier_id', $order->supplier_id)
            ->where('product_id', $product->id)
            ->orderByDesc('recorded_at')
            ->value('unit_cost');

        $prev = $prev !== null ? (float) $prev : null;
        $variation = null;
        $alert = false;
        if ($prev !== null && $prev > 0) {
            $variation = round((($unitCost - $prev) / $prev) * 100, 2);
            $alert = $variation >= $alertPct;
        }

        SupplierProductPriceHistory::create([
            'company_id' => $order->company_id,
            'supplier_id' => $order->supplier_id,
            'product_id' => $product->id,
            'purchase_order_id' => $order->id,
            'unit_cost' => $unitCost,
            'previous_unit_cost' => $prev,
            'variation_percent' => $variation,
            'price_alert' => $alert,
            'recorded_at' => now(),
        ]);
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
