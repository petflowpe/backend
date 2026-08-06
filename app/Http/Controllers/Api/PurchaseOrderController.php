<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Services\PurchaseOrderService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;

class PurchaseOrderController extends Controller
{
    public function __construct(
        private PurchaseOrderService $purchaseOrderService
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $companyId = $request->attributes->get('scope_company_id')
                ?? $request->integer('company_id')
                ?: ($request->user()?->company_id);
            $query = PurchaseOrder::with(['supplier:id,name,company_id', 'items.product:id,name,code,stock'])
                ->byCompany($companyId)
                ->orderByDesc('order_date')
                ->orderByDesc('id');

            if ($request->filled('status')) {
                $query->byStatus($request->get('status'));
            }
            if ($request->filled('supplier_id')) {
                $query->where('supplier_id', $request->integer('supplier_id'));
            }
            if ($request->filled('payment_status')) {
                $query->where('payment_status', $request->get('payment_status'));
            }
            if ($request->filled('from')) {
                $query->whereDate('order_date', '>=', $request->get('from'));
            }
            if ($request->filled('to')) {
                $query->whereDate('order_date', '<=', $request->get('to'));
            }
            if ($request->filled('search')) {
                $search = $request->get('search');
                $query->where(function ($q) use ($search) {
                    $q->where('id', 'like', "%{$search}%")
                        ->orWhere('order_number', 'like', "%{$search}%")
                        ->orWhere('invoice_number', 'like', "%{$search}%")
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
                'order_number' => $this->purchaseOrderService->nextOrderNumber($companyId),
                'order_date' => $validated['order_date'],
                'delivery_date' => $validated['delivery_date'] ?? null,
                'status' => 'pending',
                'total' => round($total, 2),
                'payment_status' => 'unpaid',
                'amount_paid' => 0,
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
                    'quantity_received' => 0,
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

    public function show(PurchaseOrder $purchase_order): JsonResponse
    {
        $purchase_order->load(['supplier', 'items.product:id,name,code,stock,unit_price']);
        return response()->json([
            'success' => true,
            'data' => $purchase_order,
        ]);
    }

    /**
     * PDF de orden de compra (Dompdf).
     */
    public function downloadPdf(PurchaseOrder $purchase_order)
    {
        try {
            $purchase_order->load([
                'company',
                'supplier',
                'items.product:id,name,code',
            ]);

            $statusLabels = [
                'pending' => 'Pendiente',
                'in_transit' => 'En tránsito',
                'partial' => 'Recepción parcial',
                'delivered' => 'Entregado',
                'cancelled' => 'Cancelado',
            ];

            $html = View::make('pdf.purchase-order', [
                'order' => $purchase_order,
                'company' => $purchase_order->company,
                'statusLabel' => $statusLabels[$purchase_order->status] ?? $purchase_order->status,
            ])->render();

            $options = new Options();
            $options->set('defaultFont', 'DejaVu Sans');
            $options->set('isHtml5ParserEnabled', true);
            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            $filename = 'OC_' . ($purchase_order->order_number ?: $purchase_order->id) . '.pdf';
            $filename = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $filename);

            return response()->streamDownload(
                fn () => print($dompdf->output()),
                $filename,
                ['Content-Type' => 'application/pdf']
            );
        } catch (Exception $e) {
            Log::error('Error al generar PDF de OC', [
                'purchase_order_id' => $purchase_order->id,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al generar PDF: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, PurchaseOrder $purchase_order): JsonResponse
    {
        if (in_array($purchase_order->status, ['delivered', 'cancelled'], true) || $purchase_order->kardex_registered) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede editar una orden entregada, cancelada o ya ingresada a kardex',
            ], 422);
        }
        if ((float) $purchase_order->items()->sum('quantity_received') > 0) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede editar una orden con recepción parcial',
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
                'delivery_date' => $validated['delivery_date'] ?? null,
                'notes' => $validated['notes'] ?? null,
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
                    'quantity_received' => 0,
                    'unit_cost' => $unitCost,
                    'total_cost' => round($qty * $unitCost, 2),
                ]);
            }

            DB::commit();
            $purchase_order->load(['supplier', 'items.product:id,name,code,stock']);
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

    public function changeStatus(Request $request, PurchaseOrder $purchase_order): JsonResponse
    {
        $status = $request->validate([
            'status' => 'required|string|in:pending,in_transit,partial,delivered,cancelled',
        ])['status'];

        if ($status === 'delivered') {
            return response()->json([
                'success' => false,
                'message' => 'Para marcar entregado use recepción / complete (ingresa stock al kardex)',
            ], 422);
        }

        $purchase_order->update(['status' => $status]);
        $purchase_order->load(['supplier', 'items.product:id,name,code']);
        return response()->json([
            'success' => true,
            'message' => 'Estado actualizado',
            'data' => $purchase_order,
        ]);
    }

    /**
     * Recepción parcial o total (IN kardex + costo promedio ponderado).
     */
    public function receive(Request $request, PurchaseOrder $purchase_order): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'nullable|integer',
            'items.*.product_id' => 'nullable|integer',
            'items.*.quantity' => 'required|numeric|min:0.001',
            'invoice_number' => 'nullable|string|max:50',
            'invoice_date' => 'nullable|date',
            'invoice_total' => 'nullable|numeric|min:0',
        ]);

        try {
            $order = $this->purchaseOrderService->receive(
                $purchase_order,
                $validated['items'],
                [
                    'invoice_number' => $validated['invoice_number'] ?? null,
                    'invoice_date' => $validated['invoice_date'] ?? null,
                    'invoice_total' => $validated['invoice_total'] ?? null,
                ],
                $request->user()?->id
            );

            return response()->json([
                'success' => true,
                'message' => 'Recepción registrada y stock actualizado',
                'data' => $order,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Completar: recibe todo lo pendiente (compat).
     */
    public function complete(Request $request, PurchaseOrder $purchase_order): JsonResponse
    {
        $validated = $request->validate([
            'invoice_number' => 'nullable|string|max:50',
            'invoice_date' => 'nullable|date',
            'invoice_total' => 'nullable|numeric|min:0',
        ]);

        try {
            $order = $this->purchaseOrderService->receiveAllRemaining(
                $purchase_order,
                $validated,
                $request->user()?->id
            );

            return response()->json([
                'success' => true,
                'message' => 'Orden completada y stock actualizado',
                'data' => $order,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function pay(Request $request, PurchaseOrder $purchase_order): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'nullable|string|max:40',
            'post_to_cash' => 'nullable|boolean',
            'cash_session_id' => 'nullable|integer',
        ]);

        try {
            $order = $this->purchaseOrderService->registerPayment(
                $purchase_order,
                (float) $validated['amount'],
                [
                    'payment_method' => $validated['payment_method'] ?? 'cash',
                    'post_to_cash' => (bool) ($validated['post_to_cash'] ?? false),
                    'cash_session_id' => $validated['cash_session_id'] ?? null,
                ],
                $request->user()?->id
            );

            return response()->json([
                'success' => true,
                'message' => 'Pago registrado',
                'data' => $order,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function destroy(PurchaseOrder $purchase_order): JsonResponse
    {
        if ($purchase_order->kardex_registered || (float) $purchase_order->items()->sum('quantity_received') > 0) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar una orden con recepción registrada',
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
