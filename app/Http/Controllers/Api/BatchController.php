<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BatchController extends Controller
{
    public function __construct(private ProductService $productService)
    {
    }

    public function index(Request $request, Product $product): JsonResponse
    {
        $batches = ProductBatch::with('area:id,name')
            ->where('product_id', $product->id)
            ->when(! $request->boolean('include_empty'), fn ($q) => $q->where('quantity_available', '>', 0))
            ->orderByRaw('expiry_date IS NULL')
            ->orderBy('expiry_date')
            ->orderBy('id')
            ->get();

        return response()->json(['success' => true, 'data' => $batches]);
    }

    /**
     * Lotes con saldo vencidos o que vencen dentro de `days` días (por defecto 30).
     */
    public function expiring(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);
        if (! $companyId) {
            return response()->json(['success' => false, 'message' => 'company_id requerido'], 422);
        }

        $days = min(max($request->integer('days', 30), 0), 365);
        $limit = now()->addDays($days)->toDateString();
        $today = now()->toDateString();

        $batches = ProductBatch::with(['product:id,name,code,cost_price', 'area:id,name'])
            ->where('company_id', $companyId)
            ->where('quantity_available', '>', 0)
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<=', $limit)
            ->orderBy('expiry_date')
            ->get();

        $value = fn ($rows) => round($rows->sum(fn (ProductBatch $b) => (float) $b->quantity_available
            * (float) ($b->unit_cost ?? $b->product?->cost_price ?? 0)), 2);
        $expired = $batches->filter(fn (ProductBatch $b) => $b->expiry_date->toDateString() < $today);
        $expiring = $batches->reject(fn (ProductBatch $b) => $b->expiry_date->toDateString() < $today);

        return response()->json([
            'success' => true,
            'data' => $batches->values(),
            'meta' => [
                'days' => $days,
                'expired_count' => $expired->count(),
                'expired_value' => $value($expired),
                'expiring_count' => $expiring->count(),
                'expiring_value' => $value($expiring),
            ],
        ]);
    }

    /**
     * Baja de un lote (vencido, dañado, etc.). Queda en el kardex como merma (source_type expiry/shrinkage).
     */
    public function writeOff(Request $request, ProductBatch $batch): JsonResponse
    {
        $data = $request->validate([
            'quantity' => 'nullable|numeric|min:0.001',
            'reason' => 'required|string|max:300',
            'kind' => 'nullable|in:expiry,shrinkage',
        ]);

        $qty = (float) ($data['quantity'] ?? $batch->quantity_available);
        if ($qty <= 0 || $qty > (float) $batch->quantity_available + 0.0005) {
            return response()->json(['success' => false, 'message' => 'Cantidad inválida para este lote'], 422);
        }

        $product = Product::findOrFail($batch->product_id);
        $kind = $data['kind'] ?? ($batch->isExpired() ? 'expiry' : 'shrinkage');

        try {
            $this->productService->adjustStock(
                $product,
                (int) $batch->area_id,
                $qty,
                'OUT',
                ($kind === 'expiry' ? 'Baja por vencimiento' : 'Baja por merma')
                    . " lote {$batch->batch_number}: {$data['reason']}",
                [
                    'source_type' => $kind,
                    'source_id' => $batch->id,
                    'unit_cost' => (float) ($batch->unit_cost ?? $product->cost_price ?? 0),
                    'batch_id' => $batch->id,
                    'allow_expired' => true,
                ]
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Lote dado de baja',
            'data' => $batch->fresh(['area:id,name']),
        ]);
    }

    private function companyId(Request $request): ?int
    {
        $id = (int) ($request->attributes->get('scope_company_id')
            ?? $request->integer('company_id')
            ?: $request->user()?->company_id);

        return $id > 0 ? $id : null;
    }
}
