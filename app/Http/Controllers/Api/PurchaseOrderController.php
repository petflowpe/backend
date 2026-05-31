<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockMovement;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class PurchaseOrderController extends Controller
{
    /**
     * Listar órdenes de compra
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $companyId = $request->integer('company_id', 1);
            $query = PurchaseOrder::with(['supplier:id,name,company_id', 'items.product:id,name,code,stock'])
                ->byCompany($companyId)
                ->orderByDesc('order_date')
                ->orderByDesc('id');

            if ($request->filled('status')) {
                $query->byStatus($request->get('status'));
            }
            if ($request->filled('search')) {
                $search = $request->get('search');
                $query->where(function ($q) use ($search) {
                    $q->where('id', 'like', "%{$search}%")
                        ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', "%{$search}%"));
                });
            }

            $perPage = $request->integer('per_page', 20);
            $orders = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $orders->items(),
                'meta' => [
                    'total' => $orders->total(),
                    'per_page' => $orders->perPage(),
                    'current_page' => $orders->currentPage(),
                    'last_page' => $orders->lastPage(),
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Error al listar órdenes de compra', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener órdenes de compra',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Crear orden de compra
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_id' => 'nullable|integer|exists:companies,id',
            'supplier_id' => 'required|integer|exists:suppliers,id',
            'order_date' => 'required|date',
            'delivery_date' => 'nullable|date',
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.001',
            'items.*.unit_cost' => 'required|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();
            $companyId = (int) ($validated['company_id'] ?? \App\Helpers\ScopeHelper::companyId($request) ?? $request->user()?->company_id);
            if (!$companyId) {
                return response()->json(['message' => 'company_id es requerido o el usuario debe tener empresa asignada.'], 422);
            }
            $total = 0;
            foreach ($validated['items'] as $row) {
                $total += (float) $row['quantity'] * (float) $row['unit_cost'];
            }

            $order = PurchaseOrder::create([
                'company_id' => $companyId,
                'supplier_id' => $validated['supplier_id'],
                'order_date' => $validated['order_date'],
                'delivery_date' => $validated['delivery_date'] ?? null,
                'status' => 'pending',
                'total' => round($total, 2),
                'notes' => $validated['notes'] ?? null,
                'created_by' => $request->user()?->id,
            ]);

            foreach ($validated['items'] as $row) {
                $qty = (float) $row['quantity'];
                $unitCost = (float) $row['unit_cost'];
                PurchaseOrderItem::create([
                    'purchase_order_id' => $order->id,
                    'product_id' => $row['product_id'],
                    'quantity' => $qty,
                    'unit_cost' => $unitCost,
                    'total_cost' => round($qty * $unitCost, 2),
                ]);
            }

            DB::commit();
            $order->load(['supplier', 'items.product:id,name,code']);
            return response()->json([
                'success' => true,
                'message' => 'Orden de compra creada',
                'data' => $order,
            ], 201);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error al crear orden de compra', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error al crear orden de compra',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Ver una orden
     */
    public function show(PurchaseOrder $purchase_order): JsonResponse
    {
        $purchase_order->load(['supplier', 'items.product:id,name,code,stock,unit_price']);
        return response()->json([
            'success' => true,
            'data' => $purchase_order,
        ]);
    }

    /**
     * Actualizar orden (solo si está pending)
     */
    public function update(Request $request, PurchaseOrder $purchase_order): JsonResponse
    {
        if ($purchase_order->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Solo se pueden editar órdenes en estado pendiente',
            ], 422);
        }

        $validated = $request->validate([
            'delivery_date' => 'nullable|date',
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.001',
            'items.*.unit_cost' => 'required|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();
            $total = 0;
            foreach ($validated['items'] as $row) {
                $total += (float) $row['quantity'] * (float) $row['unit_cost'];
            }

            $purchase_order->update([
                'delivery_date' => $validated['delivery_date'] ?? $purchase_order->delivery_date,
                'notes' => $validated['notes'] ?? $purchase_order->notes,
                'total' => round($total, 2),
            ]);

            $purchase_order->items()->delete();
            foreach ($validated['items'] as $row) {
                $qty = (float) $row['quantity'];
                $unitCost = (float) $row['unit_cost'];
                PurchaseOrderItem::create([
                    'purchase_order_id' => $purchase_order->id,
                    'product_id' => $row['product_id'],
                    'quantity' => $qty,
                    'unit_cost' => $unitCost,
                    'total_cost' => round($qty * $unitCost, 2),
                ]);
            }

            DB::commit();
            $purchase_order->load(['supplier', 'items.product:id,name,code']);
            return response()->json([
                'success' => true,
                'message' => 'Orden actualizada',
                'data' => $purchase_order,
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error al actualizar orden de compra', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar orden',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Cambiar estado (pending -> in_transit -> delivered; o cancelled)
     */
    public function changeStatus(Request $request, PurchaseOrder $purchase_order): JsonResponse
    {
        $status = $request->validate(['status' => 'required|string|in:pending,in_transit,delivered,cancelled'])['status'];
        $purchase_order->update(['status' => $status]);
        $purchase_order->load(['supplier', 'items.product:id,name,code']);
        return response()->json([
            'success' => true,
            'message' => 'Estado actualizado',
            'data' => $purchase_order,
        ]);
    }

    /**
     * Completar orden: registrar factura, kardex (stock IN) y marcar como entregada
     */
    public function complete(Request $request, PurchaseOrder $purchase_order): JsonResponse
    {
        if ($purchase_order->kardex_registered) {
            return response()->json([
                'success' => false,
                'message' => 'Esta orden ya fue registrada en kardex',
            ], 422);
        }

        $validated = $request->validate([
            'invoice_number' => 'nullable|string|max:50',
            'invoice_date' => 'nullable|date',
            'invoice_total' => 'nullable|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();
            $purchase_order->update([
                'status' => 'delivered',
                'invoice_number' => $validated['invoice_number'] ?? null,
                'invoice_date' => isset($validated['invoice_date']) ? $validated['invoice_date'] : null,
                'invoice_total' => isset($validated['invoice_total']) ? (float) $validated['invoice_total'] : $purchase_order->total,
                'kardex_registered' => true,
            ]);

            $userId = $request->user()?->id;
            foreach ($purchase_order->items as $item) {
                $product = Product::find($item->product_id);
                if (!$product) {
                    continue;
                }
                $qty = (float) $item->quantity;
                $unitCost = (float) $item->unit_cost;
                $totalCost = $qty * $unitCost;

                StockMovement::create([
                    'company_id' => $purchase_order->company_id,
                    'branch_id' => null,
                    'product_id' => $product->id,
                    'movement_date' => now(),
                    'type' => 'IN',
                    'quantity' => $qty,
                    'unit_cost' => $unitCost,
                    'total_cost' => $totalCost,
                    'source_type' => 'purchase',
                    'source_id' => $purchase_order->id,
                    'notes' => 'Entrada por orden de compra #' . $purchase_order->id,
                    'created_by' => $userId,
                ]);

                $product->increment('stock', $qty);
                $product->update(['cost_price' => $unitCost]);
            }

            DB::commit();
            $purchase_order->load(['supplier', 'items.product:id,name,code,stock']);
            return response()->json([
                'success' => true,
                'message' => 'Orden completada y stock actualizado',
                'data' => $purchase_order,
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error al completar orden de compra', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error al completar orden',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Eliminar orden (solo si pending y no kardex_registered)
     */
    public function destroy(PurchaseOrder $purchase_order): JsonResponse
    {
        if ($purchase_order->kardex_registered) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar una orden ya registrada en kardex',
            ], 422);
        }
        $purchase_order->items()->delete();
        $purchase_order->delete();
        return response()->json([
            'success' => true,
            'message' => 'Orden eliminada',
        ]);
    }
}
