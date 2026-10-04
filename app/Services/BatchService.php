<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductStock;
use App\Models\StockMovement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lotes y vencimientos. Solo actúa sobre productos con track_batches.
 * Invariante: suma de quantity_available por producto/área = product_stocks.quantity.
 */
class BatchService
{
    private const EPS = 0.0005;

    /**
     * Reparte un movimiento ya registrado entre lotes.
     *
     * @param  array{batch?: array{batch_number?: string|null, expiry_date?: string|null}, batch_id?: int,
     *               restore_batches?: array<int, float>, consume_source?: array{0: string, 1: int},
     *               allow_expired?: bool}  $options
     */
    public function apply(Product $product, int $areaId, StockMovement $movement, float $areaQtyBefore, array $options = []): void
    {
        if (! $product->track_batches) {
            return;
        }

        $this->ensureCoverage($product, $areaId, $areaQtyBefore);

        $type = strtoupper((string) $movement->type);
        $qty = (float) $movement->quantity;
        $signed = match ($type) {
            'IN' => abs($qty),
            'OUT' => -abs($qty),
            default => $qty,
        };

        if ($signed > self::EPS) {
            $this->addIn($product, $areaId, $movement, $signed, $options);
        } elseif ($signed < -self::EPS) {
            $allowExpired = ($options['allow_expired'] ?? false) || $type === 'ADJUST';
            $this->consume($product, $areaId, $movement, abs($signed), $options, $allowExpired);
        }
    }

    /**
     * Al activar el control de lotes, el stock existente queda como SIN-LOTE en cada área.
     */
    public function initializeFor(Product $product): void
    {
        ProductStock::where('product_id', $product->id)->get()->each(function (ProductStock $stock) use ($product) {
            $this->ensureCoverage($product, (int) $stock->area_id, (float) $stock->quantity);
        });
    }

    /**
     * Lotes con saldo de un área, en orden de consumo FEFO (vence antes primero, sin fecha al final).
     */
    public function fefoQuery(int $productId, int $areaId)
    {
        return ProductBatch::where('product_id', $productId)
            ->where('area_id', $areaId)
            ->where('quantity_available', '>', self::EPS)
            ->orderByRaw('expiry_date IS NULL')
            ->orderBy('expiry_date')
            ->orderBy('received_at')
            ->orderBy('id');
    }

    private function ensureCoverage(Product $product, int $areaId, float $areaQty): void
    {
        $covered = (float) ProductBatch::where('product_id', $product->id)->where('area_id', $areaId)->sum('quantity_available');
        $gap = $areaQty - $covered;
        if ($gap <= self::EPS) {
            return;
        }

        $batch = ProductBatch::firstOrNew([
            'product_id' => $product->id,
            'area_id' => $areaId,
            'batch_number' => ProductBatch::NO_BATCH,
            'expiry_date' => null,
        ], [
            'company_id' => $product->company_id,
            'quantity_initial' => 0,
            'quantity_available' => 0,
            'unit_cost' => $product->cost_price,
            'received_at' => now(),
            'source_type' => 'initial',
        ]);
        $batch->quantity_initial = (float) $batch->quantity_initial + $gap;
        $batch->quantity_available = (float) $batch->quantity_available + $gap;
        $batch->save();
    }

    private function addIn(Product $product, int $areaId, StockMovement $movement, float $qty, array $options): void
    {
        $remaining = $qty;

        foreach ($options['restore_batches'] ?? [] as $batchId => $restoreQty) {
            if ($remaining <= self::EPS) {
                break;
            }
            $batch = ProductBatch::where('product_id', $product->id)->where('area_id', $areaId)->lockForUpdate()->find($batchId);
            if (! $batch) {
                continue;
            }
            $take = min($remaining, (float) $restoreQty);
            $batch->increment('quantity_available', $take);
            $this->link($movement, $batch, $take);
            $remaining -= $take;
        }

        if ($remaining <= self::EPS) {
            return;
        }

        $data = $options['batch'] ?? [];
        $number = trim((string) ($data['batch_number'] ?? '')) ?: ProductBatch::NO_BATCH;
        $expiry = ! empty($data['expiry_date']) ? \Illuminate\Support\Carbon::parse($data['expiry_date'])->toDateString() : null;

        $batch = ProductBatch::where('product_id', $product->id)
            ->where('area_id', $areaId)
            ->where('batch_number', $number)
            ->when($expiry, fn ($q) => $q->whereDate('expiry_date', $expiry), fn ($q) => $q->whereNull('expiry_date'))
            ->lockForUpdate()
            ->first();

        if ($batch) {
            $batch->quantity_initial = (float) $batch->quantity_initial + $remaining;
            $batch->quantity_available = (float) $batch->quantity_available + $remaining;
            $batch->save();
        } else {
            $batch = ProductBatch::create([
                'company_id' => $product->company_id,
                'product_id' => $product->id,
                'area_id' => $areaId,
                'batch_number' => $number,
                'expiry_date' => $expiry,
                'quantity_initial' => $remaining,
                'quantity_available' => $remaining,
                'unit_cost' => $movement->unit_cost,
                'received_at' => now(),
                'source_type' => $movement->source_type,
                'source_id' => $movement->source_id,
            ]);
        }

        $this->link($movement, $batch, $remaining);
    }

    private function consume(Product $product, int $areaId, StockMovement $movement, float $qty, array $options, bool $allowExpired): void
    {
        if (! empty($options['batch_id'])) {
            $batch = ProductBatch::where('product_id', $product->id)->where('area_id', $areaId)->lockForUpdate()->find($options['batch_id']);
            if (! $batch || (float) $batch->quantity_available + self::EPS < $qty) {
                throw new \InvalidArgumentException('El lote indicado no tiene saldo suficiente en este almacén');
            }
            $batch->decrement('quantity_available', $qty);
            $this->link($movement, $batch, -$qty);

            return;
        }

        $candidates = $this->orderedCandidates($product->id, $areaId, $options['consume_source'] ?? null);
        $usable = $allowExpired ? $candidates : $candidates->reject(fn (ProductBatch $b) => $b->isExpired());

        $available = (float) $usable->sum(fn ($b) => (float) $b->quantity_available);
        if ($available + self::EPS < $qty) {
            $expired = (float) $candidates->filter(fn (ProductBatch $b) => $b->isExpired())->sum(fn ($b) => (float) $b->quantity_available);
            $msg = "Stock vigente insuficiente de {$product->name}: disponible {$available}, solicitado {$qty}";
            if ($expired > self::EPS) {
                $msg .= ". Hay {$expired} unidad(es) vencidas que deben darse de baja";
            }
            throw new \InvalidArgumentException($msg);
        }

        $remaining = $qty;
        foreach ($usable as $batch) {
            if ($remaining <= self::EPS) {
                break;
            }
            $take = min($remaining, (float) $batch->quantity_available);
            if ($take <= self::EPS) {
                continue;
            }
            $batch->decrement('quantity_available', $take);
            $this->link($movement, $batch, -$take);
            $remaining -= $take;
        }
    }

    /**
     * @return Collection<int, ProductBatch>
     */
    private function orderedCandidates(int $productId, int $areaId, ?array $preferSource): Collection
    {
        $batches = $this->fefoQuery($productId, $areaId)->lockForUpdate()->get();
        if (! $preferSource) {
            return $batches;
        }

        [$type, $id] = $preferSource;
        [$preferred, $rest] = $batches->partition(fn (ProductBatch $b) => $b->source_type === $type && (int) $b->source_id === (int) $id);

        return $preferred->concat($rest)->values();
    }

    private function link(StockMovement $movement, ProductBatch $batch, float $signedQty): void
    {
        DB::table('stock_movement_batches')->insert([
            'stock_movement_id' => $movement->id,
            'product_batch_id' => $batch->id,
            'quantity' => $signedQty,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Lotes y cantidades que consumieron unos movimientos de salida, para devolverlos al mismo lote.
     *
     * @param  array<int>  $movementIds
     * @return array<int, float> batch_id => cantidad consumida
     */
    public function consumedBatches(array $movementIds): array
    {
        if ($movementIds === []) {
            return [];
        }

        return DB::table('stock_movement_batches')
            ->whereIn('stock_movement_id', $movementIds)
            ->where('quantity', '<', 0)
            ->selectRaw('product_batch_id, SUM(ABS(quantity)) as qty')
            ->groupBy('product_batch_id')
            ->pluck('qty', 'product_batch_id')
            ->map(fn ($q) => (float) $q)
            ->all();
    }
}
