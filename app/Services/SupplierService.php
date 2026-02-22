<?php

namespace App\Services;

use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SupplierService
{
    public function list(int $companyId, bool $onlyActive = false, ?string $search = null, int $perPage = 15)
    {
        $query = Supplier::forCompany($companyId);
        
        if ($onlyActive) {
            $query->active();
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('business_name', 'like', "%{$search}%")
                  ->orWhere('document_number', 'like', "%{$search}%");
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

    public function create(array $data): Supplier
    {
        DB::beginTransaction();
        try {
            $supplier = Supplier::create($data);
            DB::commit();
            return $supplier;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al crear proveedor', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function update(Supplier $supplier, array $data): Supplier
    {
        $supplier->update($data);
        return $supplier->fresh();
    }

    public function delete(Supplier $supplier): bool
    {
        // Verificar si tiene productos asociados
        if ($supplier->products()->count() > 0) {
            throw new \Exception('No se puede eliminar el proveedor porque tiene productos asociados');
        }
        
        return $supplier->delete();
    }

    public function toggleActive(Supplier $supplier): Supplier
    {
        $supplier->update(['active' => !$supplier->active]);
        return $supplier->fresh();
    }

    public function getKPIs(int $companyId, ?string $search = null, ?bool $onlyActive = null, ?bool $hasLogo = null, ?string $documentType = null): array
    {
        $baseQuery = Supplier::forCompany($companyId);

        if ($search) {
            $baseQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('business_name', 'like', "%{$search}%")
                  ->orWhere('document_number', 'like', "%{$search}%");
            });
        }

        if ($documentType && $documentType !== 'all') {
            $baseQuery->where('document_type', $documentType);
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

        // Query para con email
        $withEmailQuery = clone $baseQuery;
        $withEmail = $withEmailQuery->whereNotNull('email')->count();

        // Query para con teléfono
        $withPhoneQuery = clone $baseQuery;
        $withPhone = $withPhoneQuery->whereNotNull('phone')->count();

        return [
            'total' => $total,
            'active' => $active,
            'inactive' => $inactive,
            'with_logo' => $withLogo,
            'without_logo' => $withoutLogo,
            'with_email' => $withEmail,
            'with_phone' => $withPhone,
        ];
    }
}

