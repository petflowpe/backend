<?php

namespace App\Services;

use App\Models\Area;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AreaService
{
    public function list(int $companyId, ?int $branchId = null, bool $onlyActive = false)
    {
        $query = Area::forCompany($companyId);
        
        if ($branchId) {
            $query->where('branch_id', $branchId);
        }
        
        if ($onlyActive) {
            $query->active();
        }
        
        return $query->orderBy('sort_order')->orderBy('name')->get();
    }

    public function create(array $data): Area
    {
        DB::beginTransaction();
        try {
            $area = Area::create($data);
            DB::commit();
            return $area;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al crear área', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function update(Area $area, array $data): Area
    {
        $area->update($data);
        return $area->fresh();
    }

    public function delete(Area $area): bool
    {
        // Verificar si tiene stock asociado
        if ($area->productStocks()->where('quantity', '>', 0)->exists()) {
            throw new \Exception('No se puede eliminar el área porque tiene stock asociado');
        }
        
        return $area->delete();
    }

    public function toggleActive(Area $area): Area
    {
        $area->update(['active' => !$area->active]);
        return $area->fresh();
    }
}

