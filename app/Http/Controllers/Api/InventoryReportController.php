<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Boleta;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\StockMovement;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryReportController extends Controller
{
    private const SHRINK_SOURCES = ['expiry', 'shrinkage'];
    private const ADJUST_SOURCES = ['adjustment', 'reconciliation'];
    private const SALE_OUT_SOURCES = ['invoice', 'boleta', 'appointment_item'];
    private const SALE_RETURN_SOURCES = ['credit_note', 'voided_invoice', 'voided_boleta'];
    private const SUPPLY_SOURCES = ['appointment', 'invoice_supply', 'boleta_supply'];
    private const EXCLUDED_STATES = ['ANULADO', 'RECHAZADO'];

    /**
     * Mermas: bajas por vencimiento, mermas declaradas y ajustes negativos (faltantes) del período.
     */
    public function shrinkage(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);
        if (! $companyId) {
            return response()->json(['success' => false, 'message' => 'company_id es requerido'], 422);
        }
        [$from, $to] = $this->range($request);

        $movements = StockMovement::where('company_id', $companyId)
            ->whereBetween('movement_date', [$from, $to])
            ->whereIn('source_type', array_merge(self::SHRINK_SOURCES, self::ADJUST_SOURCES))
            ->when($request->filled('area_id'), fn ($q) => $q->where('area_id', $request->integer('area_id')))
            ->with(['product:id,name,code', 'area:id,name'])
            ->get(['id', 'product_id', 'area_id', 'type', 'quantity', 'unit_cost', 'total_cost', 'source_type', 'movement_date']);

        $kinds = [
            'expiry' => ['label' => 'Vencimientos', 'qty' => 0.0, 'value' => 0.0, 'count' => 0],
            'shrinkage' => ['label' => 'Mermas declaradas', 'qty' => 0.0, 'value' => 0.0, 'count' => 0],
            'shortage' => ['label' => 'Faltantes por ajuste', 'qty' => 0.0, 'value' => 0.0, 'count' => 0],
            'surplus' => ['label' => 'Sobrantes por ajuste', 'qty' => 0.0, 'value' => 0.0, 'count' => 0],
        ];
        $byProduct = [];
        $byMonth = [];

        foreach ($movements as $m) {
            $signed = $this->signedQuantity($m);
            $unitCost = (float) ($m->unit_cost ?? 0);
            $value = abs((float) ($m->total_cost ?? 0)) ?: abs($signed) * $unitCost;

            $kind = in_array($m->source_type, self::SHRINK_SOURCES, true)
                ? $m->source_type
                : ($signed < 0 ? 'shortage' : 'surplus');
            if (abs($signed) < 0.0005) {
                continue;
            }

            $kinds[$kind]['qty'] += abs($signed);
            $kinds[$kind]['value'] += $value;
            $kinds[$kind]['count']++;

            if ($kind === 'surplus') {
                continue;
            }

            $pid = (int) $m->product_id;
            $byProduct[$pid] ??= [
                'product_id' => $pid,
                'name' => $m->product?->name ?? "#{$pid}",
                'code' => $m->product?->code,
                'expiry' => 0.0,
                'shrinkage' => 0.0,
                'shortage' => 0.0,
                'qty' => 0.0,
                'value' => 0.0,
            ];
            $byProduct[$pid][$kind] += $value;
            $byProduct[$pid]['qty'] += abs($signed);
            $byProduct[$pid]['value'] += $value;

            $month = Carbon::parse($m->movement_date)->format('Y-m');
            $byMonth[$month] ??= ['month' => $month, 'expiry' => 0.0, 'shrinkage' => 0.0, 'shortage' => 0.0];
            $byMonth[$month][$kind] += $value;
        }

        $lossValue = $kinds['expiry']['value'] + $kinds['shrinkage']['value'] + $kinds['shortage']['value'];
        $saleCost = $this->movementCost($companyId, $from, $to, self::SALE_OUT_SOURCES)
            - $this->movementCost($companyId, $from, $to, self::SALE_RETURN_SOURCES, 'IN');

        return response()->json([
            'success' => true,
            'data' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'kinds' => collect($kinds)->map(fn ($k, $key) => [
                    'kind' => $key,
                    'label' => $k['label'],
                    'qty' => round($k['qty'], 3),
                    'value' => round($k['value'], 2),
                    'count' => $k['count'],
                ])->values(),
                'loss_value' => round($lossValue, 2),
                'net_loss_value' => round($lossValue - $kinds['surplus']['value'], 2),
                'sale_cost' => round($saleCost, 2),
                'loss_rate' => $saleCost > 0 ? round($lossValue / $saleCost * 100, 2) : null,
                'by_product' => collect($byProduct)
                    ->sortByDesc('value')
                    ->take(50)
                    ->map(fn ($r) => array_merge($r, [
                        'qty' => round($r['qty'], 3),
                        'value' => round($r['value'], 2),
                        'expiry' => round($r['expiry'], 2),
                        'shrinkage' => round($r['shrinkage'], 2),
                        'shortage' => round($r['shortage'], 2),
                    ]))
                    ->values(),
                'by_month' => collect($byMonth)->sortKeys()->values(),
            ],
        ]);
    }

    /**
     * Margen bruto por producto: venta neta (sin IGV, menos NC) vs costo de lo vendido según kardex.
     * Los servicios se muestran agregados contra el costo de sus insumos.
     */
    public function margin(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);
        if (! $companyId) {
            return response()->json(['success' => false, 'message' => 'company_id es requerido'], 422);
        }
        [$from, $to] = $this->range($request);

        $products = Product::where('company_id', $companyId)
            ->get(['id', 'name', 'code', 'item_type', 'cost_price'])
            ->keyBy('id');

        $rows = [];
        $serviceRevenue = 0.0;
        $unlinkedRevenue = 0.0;

        $addRevenue = function (array $detalles, float $sign) use (&$rows, &$serviceRevenue, &$unlinkedRevenue, $products) {
            foreach ($detalles as $line) {
                $qty = (float) ($line['cantidad'] ?? 0);
                $net = isset($line['mto_valor_venta'])
                    ? (float) $line['mto_valor_venta']
                    : $qty * (float) ($line['mto_valor_unitario'] ?? 0);
                $product = $products->get((int) ($line['product_id'] ?? 0));

                if (! $product) {
                    $unlinkedRevenue += $sign * $net;
                    continue;
                }
                if (strtoupper((string) $product->item_type) !== 'PRODUCTO') {
                    $serviceRevenue += $sign * $net;
                    continue;
                }

                $rows[$product->id] ??= $this->emptyMarginRow($product);
                $rows[$product->id]['qty_sold'] += $sign * $qty;
                $rows[$product->id]['revenue'] += $sign * $net;
            }
        };

        foreach ([Invoice::class, Boleta::class] as $model) {
            $model::where('company_id', $companyId)
                ->whereBetween('fecha_emision', [$from->toDateString(), $to->toDateString()])
                ->where(fn ($q) => $q->whereNull('estado_sunat')->orWhereNotIn('estado_sunat', self::EXCLUDED_STATES))
                ->select(['id', 'detalles'])
                ->chunkById(500, function ($docs) use ($addRevenue) {
                    foreach ($docs as $doc) {
                        $addRevenue($doc->detalles ?? [], 1.0);
                    }
                });
        }

        CreditNote::where('company_id', $companyId)
            ->whereBetween('fecha_emision', [$from->toDateString(), $to->toDateString()])
            ->where(fn ($q) => $q->whereNull('estado_sunat')->orWhereNotIn('estado_sunat', self::EXCLUDED_STATES))
            ->select(['id', 'detalles'])
            ->chunkById(500, function ($docs) use ($addRevenue) {
                foreach ($docs as $doc) {
                    $addRevenue($doc->detalles ?? [], -1.0);
                }
            });

        $costs = StockMovement::where('company_id', $companyId)
            ->whereBetween('movement_date', [$from, $to])
            ->whereIn('source_type', array_merge(self::SALE_OUT_SOURCES, self::SALE_RETURN_SOURCES))
            ->selectRaw("product_id,
                SUM(CASE WHEN UPPER(type) = 'OUT' THEN ABS(COALESCE(total_cost, 0)) ELSE -ABS(COALESCE(total_cost, 0)) END) as cost")
            ->groupBy('product_id')
            ->pluck('cost', 'product_id');

        foreach ($costs as $productId => $cost) {
            $product = $products->get((int) $productId);
            if (! $product) {
                continue;
            }
            $rows[$product->id] ??= $this->emptyMarginRow($product);
            $rows[$product->id]['cost'] += (float) $cost;
        }

        $rows = collect($rows)->map(function ($r) {
            $r['qty_sold'] = round($r['qty_sold'], 3);
            $r['revenue'] = round($r['revenue'], 2);
            $r['cost'] = round($r['cost'], 2);
            $r['margin'] = round($r['revenue'] - $r['cost'], 2);
            $r['margin_pct'] = $r['revenue'] > 0 ? round($r['margin'] / $r['revenue'] * 100, 2) : null;

            return $r;
        })->sortByDesc('margin')->values();

        $supplyCost = $this->movementCost($companyId, $from, $to, self::SUPPLY_SOURCES);
        $revenue = (float) $rows->sum('revenue');
        $cost = (float) $rows->sum('cost');

        return response()->json([
            'success' => true,
            'data' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'products' => [
                    'revenue' => round($revenue, 2),
                    'cost' => round($cost, 2),
                    'margin' => round($revenue - $cost, 2),
                    'margin_pct' => $revenue > 0 ? round(($revenue - $cost) / $revenue * 100, 2) : null,
                ],
                'services' => [
                    'revenue' => round($serviceRevenue, 2),
                    'supply_cost' => round($supplyCost, 2),
                    'margin' => round($serviceRevenue - $supplyCost, 2),
                    'margin_pct' => $serviceRevenue > 0 ? round(($serviceRevenue - $supplyCost) / $serviceRevenue * 100, 2) : null,
                ],
                'unlinked_revenue' => round($unlinkedRevenue, 2),
                'rows' => $rows,
            ],
        ]);
    }

    private function emptyMarginRow(Product $product): array
    {
        return [
            'product_id' => $product->id,
            'name' => $product->name,
            'code' => $product->code,
            'qty_sold' => 0.0,
            'revenue' => 0.0,
            'cost' => 0.0,
        ];
    }

    private function movementCost(int $companyId, Carbon $from, Carbon $to, array $sources, string $type = 'OUT'): float
    {
        return (float) StockMovement::where('company_id', $companyId)
            ->whereBetween('movement_date', [$from, $to])
            ->whereIn('source_type', $sources)
            ->whereRaw('UPPER(type) = ?', [$type])
            ->selectRaw('SUM(ABS(COALESCE(total_cost, 0))) as v')
            ->value('v');
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
     * @return array{0: Carbon, 1: Carbon}
     */
    private function range(Request $request): array
    {
        $from = $request->filled('date_from')
            ? Carbon::parse($request->get('date_from'))->startOfDay()
            : now()->startOfMonth();
        $to = $request->filled('date_to')
            ? Carbon::parse($request->get('date_to'))->endOfDay()
            : now()->endOfDay();

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
