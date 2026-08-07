<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockMovement;
use App\Repositories\ProductRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductService
{
    public function __construct(
        private ProductRepository $repository
    ) {}

    public function list(array $filters = [], int $perPage = 15)
    {
        return $this->repository->list($filters, $perPage);
    }

    public function create(array $data): Product
    {
        DB::beginTransaction();
        try {
            // Generar código si no se proporciona
            if (empty($data['code'])) {
                $data['code'] = $this->generateProductCode(
                    $data['name'] ?? '',
                    $data['category_id'] ?? null,
                    $data['company_id']
                );
            }

            // area_id vive en product_stocks, no en products
            $areaId = isset($data['area_id']) ? (int) $data['area_id'] : null;
            unset($data['area_id']);

            $product = $this->repository->create($data);

            // Crear stock inicial si se proporciona área
            if ($areaId && isset($data['stock'])) {
                ProductStock::create([
                    'product_id' => $product->id,
                    'area_id' => $areaId,
                    'quantity' => $data['stock'],
                    'min_stock' => $data['min_stock'] ?? null,
                    'max_stock' => $data['max_stock'] ?? null,
                ]);

                // Registrar movimiento inicial
                StockMovement::create([
                    'company_id' => $product->company_id,
                    'product_id' => $product->id,
                    'movement_date' => now(),
                    'type' => 'IN',
                    'quantity' => $data['stock'],
                    'unit_cost' => $data['cost_price'] ?? 0,
                    'total_cost' => ($data['cost_price'] ?? 0) * $data['stock'],
                    'source_type' => 'initial',
                    'notes' => 'Stock inicial',
                ]);
            }

            DB::commit();
            return $product->fresh(['category', 'unitRelation', 'brandRelation', 'supplierRelation', 'productStocks']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al crear producto', ['error' => $e->getMessage(), 'data' => $data]);
            throw $e;
        }
    }

    public function update(Product $product, array $data): Product
    {
        DB::beginTransaction();
        try {
            $product = $this->repository->update($product, $data);
            DB::commit();
            return $product->fresh(['category', 'unitRelation', 'brandRelation', 'supplierRelation', 'productStocks']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al actualizar producto', [
                'product_id' => $product->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Ajuste unificado de stock (product_stocks + products.stock + kardex).
     * Tipos: IN | OUT | ADJUST. areaId null = primera fila de stock o primera área de la empresa.
     *
     * @param  array{source_type?: string, source_id?: int|null, branch_id?: int|null, unit_cost?: float|null, created_by?: int|null, wrap_transaction?: bool}  $options
     */
    public function adjustStock(
        Product $product,
        ?int $areaId,
        float $quantity,
        string $type,
        ?string $notes = null,
        array $options = []
    ): ProductStock {
        $type = strtoupper($type);
        if (! in_array($type, ['IN', 'OUT', 'ADJUST'], true)) {
            $type = match (strtolower((string) ($options['legacy_type'] ?? $type))) {
                'salida', 'out', 'sale' => 'OUT',
                'entrada', 'in', 'purchase' => 'IN',
                default => 'ADJUST',
            };
        }

        $wrap = $options['wrap_transaction'] ?? true;
        $runner = function () use ($product, $areaId, $quantity, $type, $notes, $options) {
            $resolvedAreaId = $areaId ?: $this->resolveDefaultAreaId($product);
            if (! $resolvedAreaId) {
                throw new \InvalidArgumentException('No hay área de almacén para ajustar stock. Cree un área en el catálogo.');
            }

            $productStock = ProductStock::firstOrCreate(
                [
                    'product_id' => $product->id,
                    'area_id' => $resolvedAreaId,
                ],
                [
                    'quantity' => 0,
                    'min_stock' => $product->min_stock,
                    'max_stock' => $product->max_stock,
                ]
            );

            $oldQuantity = (float) $productStock->quantity;

            if ($type === 'IN') {
                $productStock->quantity = $oldQuantity + $quantity;
            } elseif ($type === 'OUT') {
                if ($oldQuantity < $quantity) {
                    throw new \InvalidArgumentException(
                        "Stock insuficiente de {$product->name}: disponible {$oldQuantity}, solicitado {$quantity}"
                    );
                }
                $productStock->quantity = $oldQuantity - $quantity;
            } else {
                $productStock->quantity = $quantity;
            }

            $productStock->save();

            $totalStock = ProductStock::where('product_id', $product->id)->sum('quantity');
            $product->update(['stock' => $totalStock]);

            $unitCost = isset($options['unit_cost'])
                ? (float) $options['unit_cost']
                : (float) ($product->cost_price ?? 0);

            StockMovement::create([
                'company_id' => $product->company_id,
                'branch_id' => $options['branch_id'] ?? null,
                'product_id' => $product->id,
                'movement_date' => now(),
                'type' => $type,
                'quantity' => abs($quantity),
                'unit_cost' => $unitCost,
                'total_cost' => $unitCost * abs($quantity),
                'source_type' => $options['source_type'] ?? 'adjustment',
                'source_id' => $options['source_id'] ?? null,
                'notes' => $notes ?? "Ajuste de stock: {$oldQuantity} -> {$productStock->quantity}",
                'created_by' => $options['created_by'] ?? auth()->id(),
            ]);

            return $productStock->fresh();
        };

        if (! $wrap) {
            return $runner();
        }

        DB::beginTransaction();
        try {
            $result = $runner();
            DB::commit();

            return $result;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al ajustar stock', [
                'product_id' => $product->id,
                'area_id' => $areaId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function resolveDefaultAreaId(Product $product): ?int
    {
        $fromStock = ProductStock::where('product_id', $product->id)->value('area_id');
        if ($fromStock) {
            return (int) $fromStock;
        }

        if ($product->area_id) {
            return (int) $product->area_id;
        }

        $areaId = \App\Models\Area::query()
            ->where('company_id', $product->company_id)
            ->where('active', true)
            ->orderBy('id')
            ->value('id');

        return $areaId ? (int) $areaId : null;
    }

    public function getKPIs(int $companyId): array
    {
        $products = Product::forCompany($companyId)->get();

        $totalProducts = $products->count();
        $activeProducts = $products->where('active', true)->count();
        $lowStockProducts = $products->filter(fn($p) => $p->isLowStock())->count();

        $totalInventoryValue = $products->sum(function ($product) {
            return ($product->stock ?? 0) * ($product->cost_price ?? 0);
        });

        $totalPotentialRevenue = $products->sum(function ($product) {
            return ($product->stock ?? 0) * $product->unit_price;
        });

        $totalSold = $products->sum('sold_count');

        return [
            'total_products' => $totalProducts,
            'active_products' => $activeProducts,
            'low_stock_products' => $lowStockProducts,
            'total_inventory_value' => round($totalInventoryValue, 2),
            'total_potential_revenue' => round($totalPotentialRevenue, 2),
            'total_profit_potential' => round($totalPotentialRevenue - $totalInventoryValue, 2),
            'average_margin' => $totalPotentialRevenue > 0 
                ? round((($totalPotentialRevenue - $totalInventoryValue) / $totalPotentialRevenue) * 100, 2)
                : 0,
            'total_sold' => $totalSold,
        ];
    }

    private function generateProductCode(string $name, ?int $categoryId, int $companyId): string
    {
        $category = $categoryId ? \App\Models\Category::find($categoryId) : null;
        $categoryPrefix = $category ? strtoupper(substr($category->name, 0, 2)) : 'PR';
        
        $namePrefix = strtoupper(substr(preg_replace('/\s+/', '', $name), 0, 3));
        
        $baseCode = "{$categoryPrefix}-{$namePrefix}";
        
        // Buscar código único
        $counter = 1;
        do {
            $code = "{$baseCode}-" . str_pad((string) $counter, 3, '0', STR_PAD_LEFT);
            $exists = Product::where('company_id', $companyId)
                ->where('code', $code)
                ->exists();
            $counter++;
        } while ($exists && $counter < 1000);
        
        return $code;
    }
}

