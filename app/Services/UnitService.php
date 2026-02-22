<?php

namespace App\Services;

use App\Models\Unit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UnitService
{
    public function list(int $companyId, bool $onlyActive = false)
    {
        $query = Unit::forCompany($companyId);
        
        if ($onlyActive) {
            $query->active();
        }
        
        return $query->orderBy('sort_order')->orderBy('name')->get();
    }

    public function create(array $data): Unit
    {
        DB::beginTransaction();
        try {
            $unit = Unit::create($data);
            DB::commit();
            return $unit;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al crear unidad', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function update(Unit $unit, array $data): Unit
    {
        $unit->update($data);
        return $unit->fresh();
    }

    public function delete(Unit $unit): bool
    {
        // Verificar si tiene productos asociados
        if ($unit->products()->count() > 0) {
            throw new \Exception('No se puede eliminar la unidad porque tiene productos asociados');
        }
        
        return $unit->delete();
    }

    public function toggleActive(Unit $unit): Unit
    {
        $unit->update(['active' => !$unit->active]);
        return $unit->fresh();
    }
}

