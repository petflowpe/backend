<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CategoryService
{
    public function list(int $companyId, bool $onlyActive = false)
    {
        $query = Category::forCompany($companyId);
        
        if ($onlyActive) {
            $query->active();
        }
        
        return $query->orderBy('sort_order')->orderBy('name')->get();
    }

    public function create(array $data): Category
    {
        DB::beginTransaction();
        try {
            $category = Category::create($data);
            DB::commit();
            return $category;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al crear categoría', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function update(Category $category, array $data): Category
    {
        $category->update($data);
        return $category->fresh();
    }

    public function delete(Category $category): bool
    {
        // Verificar si tiene productos asociados
        if ($category->products()->count() > 0) {
            throw new \Exception('No se puede eliminar la categoría porque tiene productos asociados');
        }
        
        return $category->delete();
    }

    public function toggleActive(Category $category): Category
    {
        $category->update(['active' => !$category->active]);
        return $category->fresh();
    }
}

