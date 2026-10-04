<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockMovement;
use App\Services\BatchService;
use App\Services\ProductService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcileInventoryCommand extends Command
{
    protected $signature = 'inventory:reconcile
                            {--company= : Solo una empresa}
                            {--dry-run : Mostrar diferencias sin corregir}';

    protected $description = 'Cuadra products.stock = Σ product_stocks y registra en kardex la diferencia contra el saldo de movimientos';

    public function handle(ProductService $productService): int
    {
        $companyId = $this->option('company') ? (int) $this->option('company') : null;
        $dryRun = (bool) $this->option('dry-run');

        $query = Product::withoutGlobalScopes()
            ->where('item_type', 'PRODUCTO')
            ->orderBy('company_id')
            ->orderBy('id');
        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        $rows = [];
        $fixedAreas = 0;
        $fixedKardex = 0;
        $skipped = 0;

        foreach ($query->cursor() as $product) {
            $areaSum = (float) ProductStock::where('product_id', $product->id)->sum('quantity');
            $hasAreaRows = ProductStock::where('product_id', $product->id)->exists();
            $stock = (float) $product->stock;

            // 1) Stock total vs filas por almacén
            $targetStock = $hasAreaRows ? $areaSum : $stock;
            $needsAreaRow = ! $hasAreaRows && $stock > 0;
            $needsTotalFix = $hasAreaRows && abs($areaSum - $stock) > 0.0005;

            // 2) Saldo de kardex vs stock real
            $kardex = (float) StockMovement::withoutGlobalScopes()
                ->where('product_id', $product->id)
                ->selectRaw("SUM(CASE WHEN UPPER(type) = 'OUT' THEN -ABS(quantity) WHEN UPPER(type) = 'IN' THEN ABS(quantity) ELSE quantity END) as qty")
                ->value('qty');
            $kardexDiff = $targetStock - $kardex;
            $needsKardexFix = abs($kardexDiff) > 0.0005;

            if (! $needsAreaRow && ! $needsTotalFix && ! $needsKardexFix) {
                continue;
            }

            $rows[] = [
                $product->company_id,
                $product->id,
                mb_strimwidth((string) $product->name, 0, 30, '…'),
                $stock,
                $hasAreaRows ? $areaSum : '—',
                $kardex,
                $needsKardexFix ? round($kardexDiff, 3) : 0,
            ];

            if ($dryRun) {
                continue;
            }

            $areaId = $needsAreaRow ? $productService->resolveDefaultAreaId($product) : null;
            if ($needsAreaRow && ! $areaId) {
                $this->warn("Producto #{$product->id}: sin almacenes en la empresa {$product->company_id}; se omite.");
                $skipped++;
                continue;
            }

            DB::transaction(function () use ($product, $needsAreaRow, $needsTotalFix, $needsKardexFix, $areaId, $targetStock, $kardexDiff, &$fixedAreas, &$fixedKardex) {
                if ($needsAreaRow) {
                    ProductStock::create([
                        'product_id' => $product->id,
                        'area_id' => $areaId,
                        'quantity' => $targetStock,
                        'min_stock' => $product->min_stock,
                        'max_stock' => $product->max_stock,
                    ]);
                    $fixedAreas++;
                }

                if ($needsTotalFix) {
                    $product->update(['stock' => $targetStock]);
                    $fixedAreas++;
                }

                if ($needsKardexFix) {
                    $unitCost = (float) ($product->cost_price ?? 0);
                    StockMovement::withoutGlobalScopes()->create([
                        'company_id' => $product->company_id,
                        'area_id' => $areaId,
                        'product_id' => $product->id,
                        'movement_date' => now(),
                        'type' => 'ADJUST',
                        'quantity' => $kardexDiff,
                        'unit_cost' => $unitCost,
                        'total_cost' => $unitCost * $kardexDiff,
                        'balance_after' => $targetStock,
                        'source_type' => 'reconciliation',
                        'notes' => 'Conciliación de inventario: kardex ajustado al stock real',
                    ]);
                    $fixedKardex++;
                }
            });
        }

        if ($rows === []) {
            $this->info('Inventario cuadrado: no hay diferencias.');
        } else {
            $this->table(['Empresa', 'ID', 'Producto', 'Stock', 'Σ almacenes', 'Kardex', 'Ajuste kardex'], $rows);
            $this->line($dryRun
                ? '[dry-run] ' . count($rows) . ' productos con diferencias. Ejecuta sin --dry-run para corregir.'
                : "Corregidos: {$fixedAreas} saldos de almacén/total, {$fixedKardex} ajustes de kardex, {$skipped} omitidos.");
        }

        $this->reconcileBatches($companyId, $dryRun);

        return self::SUCCESS;
    }

    private function reconcileBatches(?int $companyId, bool $dryRun): void
    {
        $batchService = app(BatchService::class);
        $query = Product::withoutGlobalScopes()
            ->where('item_type', 'PRODUCTO')
            ->where('track_batches', true)
            ->orderBy('id');
        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        $rows = [];
        foreach ($query->cursor() as $product) {
            $diffs = DB::transaction(fn () => $batchService->reconcile($product, $dryRun));
            foreach ($diffs as $d) {
                $rows[] = [
                    $product->company_id,
                    $product->id,
                    mb_strimwidth((string) $product->name, 0, 30, '…'),
                    $d['area_id'],
                    $d['stock'],
                    $d['batches'],
                ];
            }
        }

        if ($rows === []) {
            $this->info('Lotes cuadrados con el stock por almacén.');

            return;
        }

        $this->table(['Empresa', 'ID', 'Producto', 'Almacén', 'Stock', 'Σ lotes'], $rows);
        $this->line($dryRun
            ? '[dry-run] ' . count($rows) . ' almacén(es) con lotes descuadrados.'
            : count($rows) . ' almacén(es) con lotes cuadrados (faltante a SIN-LOTE, sobrante descontado).');
    }
}
