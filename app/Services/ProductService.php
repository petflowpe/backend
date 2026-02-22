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

            $product = $this->repository->create($data);

            // Crear stock inicial si se proporciona área
            if (isset($data['area_id']) && isset($data['stock'])) {
                ProductStock::create([
                    'product_id' => $product->id,
                    'area_id' => $data['area_id'],
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

    public function adjustStock(Product $product, int $areaId, float $quantity, string $type, ?string $notes = null): ProductStock
    {
        DB::beginTransaction();
        try {
            $productStock = ProductStock::firstOrCreate(
                [
                    'product_id' => $product->id,
                    'area_id' => $areaId,
                ],
                [
                    'quantity' => 0,
                    'min_stock' => $product->min_stock,
                    'max_stock' => $product->max_stock,
                ]
            );

            $oldQuantity = $productStock->quantity;

            if ($type === 'IN') {
                $productStock->quantity += $quantity;
            } elseif ($type === 'OUT') {
                $productStock->quantity = max(0, $productStock->quantity - $quantity);
            } else { // ADJUST
                $productStock->quantity = $quantity;
            }

            $productStock->save();

            // Actualizar stock total del producto
            $totalStock = ProductStock::where('product_id', $product->id)->sum('quantity');
            $product->update(['stock' => $totalStock]);

            // Registrar movimiento
            StockMovement::create([
                'company_id' => $product->company_id,
                'product_id' => $product->id,
                'movement_date' => now(),
                'type' => $type,
                'quantity' => abs($quantity),
                'unit_cost' => $product->cost_price ?? 0,
                'total_cost' => ($product->cost_price ?? 0) * abs($quantity),
                'source_type' => 'adjustment',
                'notes' => $notes ?? "Ajuste de stock: {$oldQuantity} -> {$productStock->quantity}",
                'created_by' => auth()->id(),
            ]);

            DB::commit();
            return $productStock->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al ajustar stock', [
                'product_id' => $product->id,
                'area_id' => $areaId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
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

