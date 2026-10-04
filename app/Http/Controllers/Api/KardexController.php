<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockMovement;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KardexController extends Controller
{
    private const MODULES = [
        'purchase' => 'compra',
        'purchase_cancel' => 'devolucion',
        'invoice' => 'venta',
        'boleta' => 'venta',
        'sale' => 'venta',
        'appointment' => 'servicio',
        'appointment_item' => 'servicio',
        'invoice_supply' => 'servicio',
        'boleta_supply' => 'servicio',
        'initial' => 'inicial',
        'return' => 'devolucion',
        'credit_note' => 'devolucion',
        'voided_invoice' => 'devolucion',
        'voided_boleta' => 'devolucion',
        'adjustment' => 'ajuste',
        'reconciliation' => 'ajuste',
        'expiry' => 'merma',
        'shrinkage' => 'merma',
    ];

    public function index(Request $request, Product $product): JsonResponse
    {
        [$from, $to] = $this->dateRange($request);

        $base = StockMovement::where('product_id', $product->id)
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('area_id'), fn ($q) => $q->where('area_id', $request->integer('area_id')));

        $opening = $from ? $this->signedTotals((clone $base)->where('movement_date', '<', $from)) : ['qty' => 0.0, 'value' => 0.0];

        $movements = (clone $base)
            ->with(['user:id,name', 'area:id,name'])
            ->when($from, fn ($q) => $q->where('movement_date', '>=', $from))
            ->when($to, fn ($q) => $q->where('movement_date', '<=', $to))
            ->orderBy('movement_date')
            ->orderBy('id')
            ->get();

        $balanceQuantity = $opening['qty'];
        $balanceValue = $opening['value'];

        $entries = $movements->map(function (StockMovement $m) use (&$balanceQuantity, &$balanceValue) {
            $signedQty = $this->signedQuantity($m);
            $qty = (float) $m->quantity;
            $totalCost = (float) ($m->total_cost ?? ((float) ($m->unit_cost ?? 0)) * $qty);
            $signedValue = $signedQty >= 0 ? abs($totalCost) : -abs($totalCost);

            $balanceQuantity += $signedQty;
            $balanceValue += $signedValue;

            return [
                'id' => $m->id,
                'movement_date' => $m->movement_date,
                'type' => strtoupper((string) $m->type),
                'quantity' => $qty,
                'signed_quantity' => $signedQty,
                'unit_cost' => (float) ($m->unit_cost ?? 0),
                'total_cost' => $totalCost,
                'balance' => round($balanceQuantity, 3),
                'balance_value' => round($balanceValue, 2),
                'source_type' => $m->source_type,
                'source_module' => $this->module($m->source_type),
                'source_id' => $m->source_id,
                'area' => $m->area?->name,
                'notes' => $m->notes,
                'created_by' => $m->user?->name,
            ];
        });

        $byArea = $this->balancesByArea($product);
        $areaId = $request->filled('area_id') ? $request->integer('area_id') : null;

        // Saldo de kardex sin filtro de fecha final, para comparar contra el stock real.
        if ($areaId) {
            $areaRow = collect($byArea)->firstWhere('area_id', $areaId);
            $kardexTotal = (float) ($areaRow['kardex'] ?? 0);
            $actualStock = (float) ($areaRow['stock'] ?? 0);
        } else {
            $kardexTotal = $this->signedTotals(StockMovement::where('product_id', $product->id))['qty'];
            $actualStock = (float) $product->stock;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'product' => $product,
                'opening_balance' => round($opening['qty'], 3),
                'opening_value' => round($opening['value'], 2),
                'entries' => $entries,
                'closing_balance' => round($balanceQuantity, 3),
                'current_stock' => $actualStock,
                'current_value' => round($actualStock * (float) ($product->cost_price ?? 0), 2),
                'kardex_balance' => round($kardexTotal, 3),
                'difference' => round($actualStock - $kardexTotal, 3),
                'area_id' => $areaId,
                'by_area' => $byArea,
            ],
        ]);
    }

    /**
     * Stock físico (product_stocks) vs saldo de kardex por almacén.
     *
     * @return array<int, array{area_id: ?int, area: ?string, stock: float, reserved: float, kardex: float, difference: float}>
     */
    private function balancesByArea(Product $product): array
    {
        $kardex = StockMovement::where('product_id', $product->id)
            ->selectRaw("area_id, SUM(CASE WHEN UPPER(type) = 'OUT' THEN -ABS(quantity) WHEN UPPER(type) = 'IN' THEN ABS(quantity) ELSE quantity END) as qty")
            ->groupBy('area_id')
            ->pluck('qty', 'area_id');

        $stocks = $product->productStocks()->with('area:id,name')->get()->keyBy('area_id');

        $areaIds = collect($stocks->keys())->merge($kardex->keys())->unique();

        return $areaIds->map(function ($areaId) use ($stocks, $kardex) {
            $stock = $stocks->get($areaId);
            $stockQty = (float) ($stock->quantity ?? 0);
            $kardexQty = (float) ($kardex[$areaId] ?? 0);

            return [
                'area_id' => $areaId ? (int) $areaId : null,
                'area' => $stock?->area?->name ?? ($areaId ? \App\Models\Area::find($areaId)?->name : 'Sin almacén'),
                'stock' => round($stockQty, 3),
                'reserved' => round((float) ($stock->reserved_quantity ?? 0), 3),
                'kardex' => round($kardexQty, 3),
                'difference' => round($stockQty - $kardexQty, 3),
            ];
        })->sortBy('area')->values()->all();
    }

    public function summary(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);
        if (! $companyId) {
            return response()->json(['success' => false, 'message' => 'company_id es requerido'], 422);
        }

        $today = StockMovement::where('company_id', $companyId)
            ->whereBetween('movement_date', [now()->startOfDay(), now()->endOfDay()]);

        $byType = (clone $today)
            ->selectRaw('UPPER(type) as t, COUNT(*) as c, SUM(ABS(quantity)) as q')
            ->groupBy('t')
            ->get()
            ->keyBy('t');

        $products = Product::where('company_id', $companyId)->where('item_type', 'PRODUCTO');
        $mismatched = $this->mismatchedProducts($companyId);

        return response()->json([
            'success' => true,
            'data' => [
                'movements_today' => (int) $byType->sum('c'),
                'in_today' => (float) ($byType['IN']->q ?? 0),
                'out_today' => (float) ($byType['OUT']->q ?? 0),
                'adjustments_today' => (int) ($byType['ADJUST']->c ?? 0),
                'total_products' => (clone $products)->count(),
                'stock_value' => round((float) (clone $products)->selectRaw('SUM(stock * COALESCE(cost_price, 0)) as v')->value('v'), 2),
                'products_out_of_sync' => count($mismatched),
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse|JsonResponse
    {
        $companyId = $this->companyId($request);
        if (! $companyId) {
            return response()->json(['success' => false, 'message' => 'company_id es requerido'], 422);
        }

        [$from, $to] = $this->dateRange($request);

        $query = StockMovement::where('company_id', $companyId)
            ->with(['product:id,code,name', 'user:id,name', 'area:id,name'])
            ->when($request->filled('product_id'), fn ($q) => $q->where('product_id', $request->integer('product_id')))
            ->when($request->filled('area_id'), fn ($q) => $q->where('area_id', $request->integer('area_id')))
            ->when($from, fn ($q) => $q->where('movement_date', '>=', $from))
            ->when($to, fn ($q) => $q->where('movement_date', '<=', $to))
            ->orderBy('product_id')
            ->orderBy('movement_date')
            ->orderBy('id');

        $filename = 'kardex_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Fecha', 'Código', 'Producto', 'Almacén', 'Tipo', 'Módulo', 'Cantidad', 'Costo unit.', 'Costo total', 'Saldo después', 'Referencia', 'Detalle', 'Usuario']);

            $query->chunk(1000, function ($rows) use ($out) {
                foreach ($rows as $m) {
                    fputcsv($out, [
                        optional($m->movement_date)->format('Y-m-d H:i'),
                        $m->product?->code,
                        $m->product?->name,
                        $m->area?->name,
                        strtoupper((string) $m->type),
                        $this->module($m->source_type),
                        $this->signedQuantity($m),
                        (float) ($m->unit_cost ?? 0),
                        (float) ($m->total_cost ?? 0),
                        $m->balance_after,
                        $m->source_id ? ($m->source_type . ' #' . $m->source_id) : $m->source_type,
                        $m->notes,
                        $m->user?->name,
                    ]);
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Productos cuyo stock no coincide con la suma de su kardex.
     *
     * @return array<int, array{product_id: int, stock: float, kardex: float}>
     */
    public function mismatchedProducts(int $companyId): array
    {
        $kardex = StockMovement::where('company_id', $companyId)
            ->selectRaw("product_id, SUM(CASE WHEN UPPER(type) = 'OUT' THEN -ABS(quantity) WHEN UPPER(type) = 'IN' THEN ABS(quantity) ELSE quantity END) as qty")
            ->groupBy('product_id')
            ->pluck('qty', 'product_id');

        return Product::where('company_id', $companyId)
            ->where('item_type', 'PRODUCTO')
            ->get(['id', 'stock'])
            ->map(fn ($p) => ['product_id' => $p->id, 'stock' => (float) $p->stock, 'kardex' => (float) ($kardex[$p->id] ?? 0)])
            ->filter(fn ($r) => abs($r['stock'] - $r['kardex']) > 0.0005)
            ->values()
            ->all();
    }

    private function signedQuantity(StockMovement $m): float
    {
        $qty = (float) $m->quantity;

        return match (strtoupper((string) $m->type)) {
            'IN' => abs($qty),
            'OUT' => -abs($qty),
            default => $qty,
        };
    }

    /**
     * @return array{qty: float, value: float}
     */
    private function signedTotals(Builder $query): array
    {
        $row = $query->selectRaw(
            "SUM(CASE WHEN UPPER(type) = 'OUT' THEN -ABS(quantity) WHEN UPPER(type) = 'IN' THEN ABS(quantity) ELSE quantity END) as qty,
             SUM(CASE WHEN UPPER(type) = 'OUT' THEN -ABS(COALESCE(total_cost, 0)) WHEN UPPER(type) = 'IN' THEN ABS(COALESCE(total_cost, 0)) ELSE COALESCE(total_cost, 0) END) as val"
        )->first();

        return ['qty' => (float) ($row->qty ?? 0), 'value' => (float) ($row->val ?? 0)];
    }

    private function module(?string $sourceType): string
    {
        return self::MODULES[strtolower((string) $sourceType)] ?? 'ajuste';
    }

    /**
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    private function dateRange(Request $request): array
    {
        $from = $request->filled('date_from') ? Carbon::parse($request->get('date_from'))->startOfDay() : null;
        $to = $request->filled('date_to') ? Carbon::parse($request->get('date_to'))->endOfDay() : null;

        return [$from, $to];
    }

    private function companyId(Request $request): ?int
    {
        $id = $request->integer('company_id')
            ?: (int) ($request->attributes->get('scope_company_id') ?? 0)
            ?: (int) ($request->user()?->company_id ?? 0);

        return $id > 0 ? $id : null;
    }
}
