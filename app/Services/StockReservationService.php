<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockReservation;
use Illuminate\Support\Facades\DB;

/**
 * Reservas de stock (citas, pedidos). product_stocks.reserved_quantity es la suma de reservas activas
 * por almacén; el stock disponible para nuevos compromisos es quantity - reserved_quantity.
 */
class StockReservationService
{
    public function __construct(private ProductService $productService)
    {
    }

    /**
     * Reemplaza las reservas activas de un origen por las cantidades indicadas.
     *
     * @param  array<int, float>  $quantities  product_id => cantidad
     */
    public function sync(int $companyId, string $sourceType, int $sourceId, array $quantities): void
    {
        DB::transaction(function () use ($companyId, $sourceType, $sourceId, $quantities) {
            $this->releaseRows($sourceType, $sourceId, StockReservation::RELEASED);

            foreach ($quantities as $productId => $qty) {
                $qty = (float) $qty;
                if ($qty <= 0) {
                    continue;
                }
                $product = Product::where('company_id', $companyId)
                    ->where('item_type', 'PRODUCTO')
                    ->find($productId);
                if (! $product) {
                    continue;
                }

                $areaId = $this->productService->resolveDefaultAreaId($product);
                if (! $areaId) {
                    continue;
                }

                StockReservation::create([
                    'company_id' => $companyId,
                    'product_id' => $product->id,
                    'area_id' => $areaId,
                    'quantity' => $qty,
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                    'status' => StockReservation::ACTIVE,
                ]);
                $this->refreshReserved($product->id, $areaId);
            }
        });
    }

    public function release(string $sourceType, int $sourceId): void
    {
        DB::transaction(fn () => $this->releaseRows($sourceType, $sourceId, StockReservation::RELEASED));
    }

    public function consume(string $sourceType, int $sourceId): void
    {
        DB::transaction(fn () => $this->releaseRows($sourceType, $sourceId, StockReservation::CONSUMED));
    }

    /**
     * Stock libre de un producto (todas las áreas), excluyendo las reservas del propio origen.
     */
    public function available(Product $product, ?string $exceptSourceType = null, ?int $exceptSourceId = null): float
    {
        $reserved = StockReservation::where('product_id', $product->id)
            ->where('status', StockReservation::ACTIVE)
            ->when($exceptSourceType && $exceptSourceId, fn ($q) => $q->where(
                fn ($q) => $q->where('source_type', '!=', $exceptSourceType)->orWhere('source_id', '!=', $exceptSourceId)
            ))
            ->sum('quantity');

        return (float) $product->stock - (float) $reserved;
    }

    private function releaseRows(string $sourceType, int $sourceId, string $status): void
    {
        $rows = StockReservation::where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->where('status', StockReservation::ACTIVE)
            ->lockForUpdate()
            ->get();

        foreach ($rows as $row) {
            $row->update(['status' => $status, 'released_at' => now()]);
        }

        foreach ($rows->unique(fn ($r) => $r->product_id . ':' . $r->area_id) as $row) {
            $this->refreshReserved((int) $row->product_id, $row->area_id ? (int) $row->area_id : null);
        }
    }

    private function refreshReserved(int $productId, ?int $areaId): void
    {
        if (! $areaId) {
            return;
        }

        $total = (float) StockReservation::where('product_id', $productId)
            ->where('area_id', $areaId)
            ->where('status', StockReservation::ACTIVE)
            ->sum('quantity');

        $stock = ProductStock::firstOrCreate(
            ['product_id' => $productId, 'area_id' => $areaId],
            ['quantity' => 0]
        );
        $stock->update(['reserved_quantity' => $total]);
    }
}
