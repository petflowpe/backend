<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Area\StoreAreaRequest;
use App\Http\Requests\Area\UpdateAreaRequest;
use App\Models\Area;
use App\Services\AreaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

class AreaController extends Controller
{
    public function __construct(
        private AreaService $areaService
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $companyId = $request->integer('company_id');
            $branchId = $request->integer('branch_id');
            $onlyActive = $request->boolean('only_active', false);

            if (!$companyId) {
                return response()->json([
                    'success' => false,
                    'message' => 'company_id es requerido',
                ], 400);
            }

            $areas = $this->areaService->list($companyId, $branchId, $onlyActive);

            return response()->json([
                'success' => true,
                'data' => $areas,
            ]);
        } catch (Exception $e) {
            Log::error('Error al listar áreas', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener áreas',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function store(StoreAreaRequest $request): JsonResponse
    {
        try {
            $area = $this->areaService->create($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Área creada exitosamente',
                'data' => $area,
            ], 201);
        } catch (Exception $e) {
            Log::error('Error al crear área', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error al crear área',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function update(UpdateAreaRequest $request, Area $area): JsonResponse
    {
        try {
            $area = $this->areaService->update($area, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Área actualizada exitosamente',
                'data' => $area,
            ]);
        } catch (Exception $e) {
            Log::error('Error al actualizar área', [
                'area_id' => $area->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar área',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function destroy(Area $area): JsonResponse
    {
        try {
            $this->areaService->delete($area);

            return response()->json([
                'success' => true,
                'message' => 'Área eliminada exitosamente',
            ]);
        } catch (Exception $e) {
            Log::error('Error al eliminar área', [
                'area_id' => $area->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 400);
        }
    }

    public function toggleActive(Area $area): JsonResponse
    {
        try {
            $area = $this->areaService->toggleActive($area);

            return response()->json([
                'success' => true,
                'message' => 'Estado de área actualizado',
                'data' => $area,
            ]);
        } catch (Exception $e) {
            Log::error('Error al cambiar estado de área', [
                'area_id' => $area->id,
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

