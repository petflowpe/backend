<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

class CategoryController extends Controller
{
    public function __construct(
        private CategoryService $categoryService
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $companyId = $request->integer('company_id');
            $onlyActive = $request->boolean('only_active', false);

            if (!$companyId) {
                return response()->json([
                    'success' => false,
                    'message' => 'company_id es requerido',
                ], 400);
            }

            $categories = $this->categoryService->list($companyId, $onlyActive);

            return response()->json([
                'success' => true,
                'data' => $categories,
            ]);
        } catch (Exception $e) {
            Log::error('Error al listar categorías', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener categorías',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        try {
            $category = $this->categoryService->create($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Categoría creada exitosamente',
                'data' => $category,
            ], 201);
        } catch (Exception $e) {
            Log::error('Error al crear categoría', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error al crear categoría',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        try {
            $category = $this->categoryService->update($category, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Categoría actualizada exitosamente',
                'data' => $category,
            ]);
        } catch (Exception $e) {
            Log::error('Error al actualizar categoría', [
                'category_id' => $category->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar categoría',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function destroy(Category $category): JsonResponse
    {
        try {
            $this->categoryService->delete($category);

            return response()->json([
                'success' => true,
                'message' => 'Categoría eliminada exitosamente',
            ]);
        } catch (Exception $e) {
            Log::error('Error al eliminar categoría', [
                'category_id' => $category->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 400);
        }
    }

    public function toggleActive(Category $category): JsonResponse
    {
        try {
            $category = $this->categoryService->toggleActive($category);

            return response()->json([
                'success' => true,
                'message' => 'Estado de categoría actualizado',
                'data' => $category,
            ]);
        } catch (Exception $e) {
            Log::error('Error al cambiar estado de categoría', [
                'category_id' => $category->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al cambiar estado',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}

