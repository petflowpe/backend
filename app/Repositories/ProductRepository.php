<?php

namespace App\Repositories;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ProductRepository
{
    public function find(int $id): ?Product
    {
        return Product::with(['company', 'category', 'unitRelation', 'brandRelation', 'supplierRelation', 'productStocks.area', 'productSale'])
            ->find($id);
    }

    public function list(array $filters = [], int $perPage = 15): LengthAwarePaginator|Collection
    {
        $query = Product::with(['company', 'category', 'unitRelation', 'brandRelation', 'supplierRelation', 'productSale']);

        // Filtros
        if (isset($filters['company_id'])) {
            $query->where('company_id', $filters['company_id']);
        }

        if (isset($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (isset($filters['only_active'])) {
            $query->where('active', $filters['only_active']);
        }

        if (isset($filters['search'])) {
            $term = $filters['search'];
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('code', 'like', "%{$term}%")
                    ->orWhere('brand', 'like', "%{$term}%")
                    ->orWhere('barcode', 'like', "%{$term}%")
                    ->orWhereHas('brandRelation', function ($q) use ($term) {
                        $q->where('name', 'like', "%{$term}%");
                    })
                    ->orWhereHas('supplierRelation', function ($q) use ($term) {
                        $q->where('name', 'like', "%{$term}%");
                    });
            });
        }

        if (isset($filters['low_stock'])) {
            $query->lowStock();
        }

        if (isset($filters['area_id'])) {
            $query->whereHas('productStocks', function ($q) use ($filters) {
                $q->where('area_id', $filters['area_id']);
            });
        }

        // Ordenamiento
        $orderBy = $filters['order_by'] ?? 'name';
        $orderDir = $filters['order_dir'] ?? 'asc';
        $query->orderBy($orderBy, $orderDir);

        // Paginación
        if (isset($filters['per_page']) && $filters['per_page'] > 0) {
            $perPage = min(max((int) $filters['per_page'], 1), 200);
            return $query->paginate($perPage);
        }

        return $query->get();
    }

    public function create(array $data): Product
    {
        return Product::create($data);
    }

    public function update(Product $product, array $data): Product
    {
        $product->update($data);
        return $product->fresh();
    }

    public function delete(Product $product): bool
    {
        return $product->delete();
    }

    public function getLowStockProducts(int $companyId): Collection
    {
        return Product::forCompany($companyId)
            ->lowStock()
            ->with(['category', 'unitRelation'])
            ->get();
    }

    public function getTopSellingProducts(int $companyId, int $limit = 10): Collection
    {
        return Product::forCompany($companyId)
            ->whereHas('productSale')
            ->with(['productSale', 'category'])
            ->orderByDesc('sold_count')
            ->limit($limit)
            ->get();
    }

    public function getBestMarginProducts(int $companyId, int $limit = 10): Collection
    {
        return Product::forCompany($companyId)
            ->whereNotNull('cost_price')
            ->where('cost_price', '>', 0)
            ->with(['category', 'unitRelation'])
            ->get()
            ->sortByDesc(function ($product) {
                return $product->margin;
            })
            ->take($limit);
    }
}

