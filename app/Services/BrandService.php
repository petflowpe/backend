<?php

namespace App\Services;

use App\Models\Brand;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BrandService
{
    public function list(int $companyId, bool $onlyActive = false, ?string $search = null, int $perPage = 15)
    {
        $query = Brand::forCompany($companyId);
        
        if ($onlyActive) {
            $query->active();
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }
        
        if ($perPage > 0) {
            $paginated = $query->orderBy('sort_order')->orderBy('name')->paginate($perPage, ['*'], 'page', request()->get('page', 1));
            return [
                'data' => $paginated->items(),
                'pagination' => [
                    'current_page' => $paginated->currentPage(),
                    'per_page' => $paginated->perPage(),
                    'total' => $paginated->total(),
                    'last_page' => $paginated->lastPage(),
                    'from' => $paginated->firstItem(),
                    'to' => $paginated->lastItem(),
                ]
            ];
        }
        
        return [
            'data' => $query->orderBy('sort_order')->orderBy('name')->get()
        ];
    }

    public function create(array $data): Brand
    {
        DB::beginTransaction();
        try {
            $brand = Brand::create($data);
            DB::commit();
            return $brand;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al crear marca', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function update(Brand $brand, array $data): Brand
    {
        $brand->update($data);
        return $brand->fresh();
    }

    public function delete(Brand $brand): bool
    {
        // Verificar si tiene productos asociados
        if ($brand->products()->count() > 0) {
            throw new \Exception('No se puede eliminar la marca porque tiene productos asociados');
        }
        
        return $brand->delete();
    }

    public function toggleActive(Brand $brand): Brand
    {
        $brand->update(['active' => !$brand->active]);
        return $brand->fresh();
    }

    public function getKPIs(int $companyId, ?string $search = null, ?bool $onlyActive = null, ?bool $hasLogo = null): array
    {
        $baseQuery = Brand::forCompany($companyId);

        if ($search) {
            $baseQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Query base para totales
        $totalQuery = clone $baseQuery;
        $total = $totalQuery->count();

        // Query para activos (sin filtro de onlyActive)
        $activeQuery = clone $baseQuery;
        $active = $activeQuery->where('active', true)->count();

        // Query para inactivos (sin filtro de onlyActive)
        $inactiveQuery = clone $baseQuery;
        $inactive = $inactiveQuery->where('active', false)->count();

        // Query para con logo (sin filtro de hasLogo)
        $withLogoQuery = clone $baseQuery;
        $withLogo = $withLogoQuery->whereNotNull('logo')->count();

        $withoutLogo = $total - $withLogo;

        return [
            'total' => $total,
            'active' => $active,
            'inactive' => $inactive,
            'with_logo' => $withLogo,
            'without_logo' => $withoutLogo,
        ];
    }
}

