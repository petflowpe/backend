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
use Illuminate\Support\Facades\Storage;
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
            'default_area_id' => 'nullable|integer',
            'igv_rate' => 'nullable|numeric|min:0|max:100',
            'prices_include_igv' => 'nullable|boolean',
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
            $linesTotal = 0;
            foreach ($validated['items'] as $row) {
                $linesTotal += (float) $row['quantity'] * (float) $row['unit_cost'];
            }

            $taxOverrides = [];
            if (array_key_exists('igv_rate', $validated) && $validated['igv_rate'] !== null) {
                $taxOverrides['igv_rate'] = $validated['igv_rate'];
            }
            if (array_key_exists('prices_include_igv', $validated)) {
                $taxOverrides['prices_include_igv'] = $validated['prices_include_igv'];
            }
            $taxes = $this->purchaseOrderService->computeTaxes($linesTotal, $companyId, $taxOverrides ?: null);
            $approval = $this->purchaseOrderService->resolveApprovalStatus($companyId, $taxes['total']);

            $order = PurchaseOrder::create([
                'company_id' => $companyId,
                'supplier_id' => $validated['supplier_id'],
                'default_area_id' => $validated['default_area_id'] ?? null,
                'order_number' => $this->purchaseOrderService->nextOrderNumber($companyId),
                'order_date' => $validated['order_date'],
                'delivery_date' => $validated['delivery_date'] ?? null,
                'status' => 'pending',
                'approval_status' => $approval,
                'subtotal' => $taxes['subtotal'],
                'igv_rate' => $taxes['igv_rate'],
                'igv_amount' => $taxes['igv_amount'],
                'prices_include_igv' => $taxes['prices_include_igv'],
                'total' => $taxes['total'],
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

            $this->purchaseOrderService->syncPayable($order->fresh(['supplier']));

            DB::commit();
            $order->load(['supplier', 'items.product:id,name,code', 'payable']);
            return response()->json([
                'success' => true,
                'message' => $approval === 'pending_approval'
                    ? 'Orden creada — pendiente de aprobación'
                    : 'Orden de compra creada',
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

    /**
     * Adjuntar factura PDF/imagen del proveedor.
     */
    public function uploadInvoiceAttachment(Request $request, PurchaseOrder $purchase_order): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
        ]);

        try {
            $file = $request->file('file');
            $dir = 'purchase-invoices/' . $purchase_order->company_id . '/' . $purchase_order->id;

            if ($purchase_order->invoice_attachment_path && Storage::disk('local')->exists($purchase_order->invoice_attachment_path)) {
                Storage::disk('local')->delete($purchase_order->invoice_attachment_path);
            }

            $path = $file->storeAs(
                $dir,
                'factura_' . now()->format('Ymd_His') . '.' . $file->getClientOriginalExtension(),
                'local'
            );

            $purchase_order->update([
                'invoice_attachment_path' => $path,
                'invoice_attachment_name' => $file->getClientOriginalName(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Factura adjunta',
                'data' => $purchase_order->fresh(['supplier', 'items.product']),
            ]);
        } catch (Exception $e) {
            Log::error('Error al adjuntar factura de OC', [
                'purchase_order_id' => $purchase_order->id,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al subir adjunto',
            ], 500);
        }
    }

    public function downloadInvoiceAttachment(PurchaseOrder $purchase_order)
    {
        if (! $purchase_order->invoice_attachment_path || ! Storage::disk('local')->exists($purchase_order->invoice_attachment_path)) {
            return response()->json([
                'success' => false,
                'message' => 'No hay factura adjunta',
            ], 404);
        }

        $name = $purchase_order->invoice_attachment_name
            ?: basename($purchase_order->invoice_attachment_path);

        return Storage::disk('local')->download($purchase_order->invoice_attachment_path, $name);
    }

    public function deleteInvoiceAttachment(PurchaseOrder $purchase_order): JsonResponse
    {
        try {
            if ($purchase_order->invoice_attachment_path && Storage::disk('local')->exists($purchase_order->invoice_attachment_path)) {
                Storage::disk('local')->delete($purchase_order->invoice_attachment_path);
            }
            $purchase_order->update([
                'invoice_attachment_path' => null,
                'invoice_attachment_name' => null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Adjunto eliminado',
                'data' => $purchase_order->fresh(['supplier', 'items.product']),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar adjunto',
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
            $linesTotal = 0;
            foreach ($validated['items'] as $row) {
                $linesTotal += (float) $row['quantity'] * (float) $row['unit_cost'];
            }
            $taxes = $this->purchaseOrderService->computeTaxes($linesTotal, (int) $purchase_order->company_id);

            $purchase_order->update([
                'delivery_date' => $validated['delivery_date'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'subtotal' => $taxes['subtotal'],
                'igv_rate' => $taxes['igv_rate'],
                'igv_amount' => $taxes['igv_amount'],
                'prices_include_igv' => $taxes['prices_include_igv'],
                'total' => $taxes['total'],
                'approval_status' => $this->purchaseOrderService->resolveApprovalStatus(
                    (int) $purchase_order->company_id,
                    $taxes['total']
                ),
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

            $this->purchaseOrderService->syncPayable($purchase_order->fresh(['supplier']));
            DB::commit();
            $purchase_order->load(['supplier', 'items.product:id,name,code,stock', 'payable']);
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
            'items.*.area_id' => 'nullable|integer',
            'area_id' => 'nullable|integer',
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
                $request->user()?->id,
                isset($validated['area_id']) ? (int) $validated['area_id'] : null
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
            'area_id' => 'nullable|integer',
        ]);

        try {
            $order = $this->purchaseOrderService->receiveAllRemaining(
                $purchase_order,
                $validated,
                $request->user()?->id,
                isset($validated['area_id']) ? (int) $validated['area_id'] : null
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
                'message' => 'Pago registrado (CxP actualizada)',
                'data' => $order,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function cancel(Request $request, PurchaseOrder $purchase_order): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        try {
            $order = $this->purchaseOrderService->cancel(
                $purchase_order,
                $validated['reason'],
                $request->user()?->id
            );

            return response()->json([
                'success' => true,
                'message' => 'Orden anulada; stock recibido revertido en kardex',
                'data' => $order,
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function approve(Request $request, PurchaseOrder $purchase_order): JsonResponse
    {
        $notes = $request->validate(['notes' => 'nullable|string|max:500'])['notes'] ?? null;
        try {
            return response()->json([
                'success' => true,
                'message' => 'Orden aprobada',
                'data' => $this->purchaseOrderService->approve($purchase_order, $request->user()?->id, $notes),
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function reject(Request $request, PurchaseOrder $purchase_order): JsonResponse
    {
        $notes = $request->validate(['notes' => 'nullable|string|max:500'])['notes'] ?? null;
        try {
            return response()->json([
                'success' => true,
                'message' => 'Orden rechazada',
                'data' => $this->purchaseOrderService->reject($purchase_order, $request->user()?->id, $notes),
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function suggestRestock(Request $request): JsonResponse
    {
        $companyId = (int) ($request->attributes->get('scope_company_id')
            ?? $request->integer('company_id')
            ?: $request->user()?->company_id);
        if (! $companyId) {
            return response()->json(['success' => false, 'message' => 'company_id requerido'], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $this->purchaseOrderService->suggestRestock($companyId),
        ]);
    }

    public function createFromRestock(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_id' => 'nullable|integer',
            'groups' => 'required|array|min:1',
            'groups.*.supplier_id' => 'required|integer',
            'groups.*.items' => 'required|array|min:1',
            'groups.*.items.*.product_id' => 'required|integer',
            'groups.*.items.*.quantity' => 'required|numeric|min:0.001',
            'groups.*.items.*.unit_cost' => 'nullable|numeric|min:0',
        ]);
        $companyId = (int) ($validated['company_id']
            ?? $request->attributes->get('scope_company_id')
            ?: $request->user()?->company_id);
        if (! $companyId) {
            return response()->json(['success' => false, 'message' => 'company_id requerido'], 422);
        }

        try {
            $orders = $this->purchaseOrderService->createFromRestockSuggestions(
                $companyId,
                $validated['groups'],
                $request->user()?->id
            );

            return response()->json([
                'success' => true,
                'message' => $orders->count() . ' orden(es) generada(s)',
                'data' => $orders->values(),
            ], 201);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function deliveryAlerts(Request $request): JsonResponse
    {
        $companyId = (int) ($request->attributes->get('scope_company_id')
            ?? $request->integer('company_id')
            ?: $request->user()?->company_id);
        if (! $companyId) {
            return response()->json(['success' => false, 'message' => 'company_id requerido'], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $this->purchaseOrderService->deliveryAlerts($companyId),
        ]);
    }

    public function priceHistory(Request $request): JsonResponse
    {
        $companyId = (int) ($request->attributes->get('scope_company_id')
            ?? $request->integer('company_id')
            ?: $request->user()?->company_id);
        if (! $companyId) {
            return response()->json(['success' => false, 'message' => 'company_id requerido'], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $this->purchaseOrderService->priceHistory(
                $companyId,
                $request->filled('product_id') ? $request->integer('product_id') : null,
                $request->filled('supplier_id') ? $request->integer('supplier_id') : null,
                $request->integer('limit', 80)
            ),
        ]);
    }

    public function emailSupplier(PurchaseOrder $purchase_order): JsonResponse
    {
        try {
            $order = $this->purchaseOrderService->sendToSupplier($purchase_order);

            return response()->json([
                'success' => true,
                'message' => 'Orden enviada al proveedor',
                'data' => $order,
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function lookupBarcode(Request $request): JsonResponse
    {
        $validated = $request->validate(['code' => 'required|string|max:100']);
        $companyId = (int) ($request->attributes->get('scope_company_id')
            ?? $request->integer('company_id')
            ?: $request->user()?->company_id);
        $product = $this->purchaseOrderService->findProductByBarcode($companyId, $validated['code']);
        if (! $product) {
            return response()->json(['success' => false, 'message' => 'Producto no encontrado'], 404);
        }

        return response()->json(['success' => true, 'data' => $product]);
    }

    public function settings(Request $request): JsonResponse
    {
        $companyId = (int) ($request->attributes->get('scope_company_id')
            ?? $request->integer('company_id')
            ?: $request->user()?->company_id);
        if (! $companyId) {
            return response()->json(['success' => false, 'message' => 'company_id requerido'], 422);
        }

        if ($request->isMethod('put') || $request->isMethod('post')) {
            $data = $request->validate([
                'approval_threshold' => 'nullable|numeric|min:0',
                'price_alert_percent' => 'nullable|numeric|min:0|max:100',
                'delivery_alert_days' => 'nullable|integer|min:0|max:60',
                'default_igv_rate' => 'nullable|numeric|min:0|max:100',
                'prices_include_igv' => 'nullable|boolean',
            ]);
            $settings = \App\Models\CompanyPurchaseSetting::forCompany($companyId);
            $settings->fill(array_filter($data, fn ($v) => $v !== null));
            $settings->save();

            return response()->json(['success' => true, 'data' => $settings]);
        }

        return response()->json([
            'success' => true,
            'data' => \App\Models\CompanyPurchaseSetting::forCompany($companyId),
        ]);
    }

    public function payables(Request $request): JsonResponse
    {
        $companyId = (int) ($request->attributes->get('scope_company_id')
            ?? $request->integer('company_id')
            ?: $request->user()?->company_id);
        $rows = \App\Models\PurchasePayable::query()
            ->with(['supplier:id,name', 'purchaseOrder:id,order_number,status'])
            ->where('company_id', $companyId)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->get('status')))
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function destroy(PurchaseOrder $purchase_order): JsonResponse
    {
        if ($purchase_order->kardex_registered || (float) $purchase_order->items()->sum('quantity_received') > 0) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar una orden con recepción registrada. Use anulación.',
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
