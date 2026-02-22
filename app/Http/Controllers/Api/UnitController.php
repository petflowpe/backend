<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Unit\StoreUnitRequest;
use App\Http\Requests\Unit\UpdateUnitRequest;
use App\Models\Unit;
use App\Services\UnitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

class UnitController extends Controller
{
    public function __construct(
        private UnitService $unitService
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

            $units = $this->unitService->list($companyId, $onlyActive);

            return response()->json([
                'success' => true,
                'data' => $units,
            ]);
        } catch (Exception $e) {
            Log::error('Error al listar unidades', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener unidades',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function store(StoreUnitRequest $request): JsonResponse
    {
        try {
            $unit = $this->unitService->create($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Unidad creada exitosamente',
                'data' => $unit,
            ], 201);
        } catch (Exception $e) {
            Log::error('Error al crear unidad', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error al crear unidad',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function update(UpdateUnitRequest $request, Unit $unit): JsonResponse
    {
        try {
            $unit = $this->unitService->update($unit, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Unidad actualizada exitosamente',
                'data' => $unit,
            ]);
        } catch (Exception $e) {
            Log::error('Error al actualizar unidad', [
                'unit_id' => $unit->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar unidad',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function destroy(Unit $unit): JsonResponse
    {
        try {
            $this->unitService->delete($unit);

            return response()->json([
                'success' => true,
                'message' => 'Unidad eliminada exitosamente',
            ]);
        } catch (Exception $e) {
            Log::error('Error al eliminar unidad', [
                'unit_id' => $unit->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 400);
        }
    }

    public function toggleActive(Unit $unit): JsonResponse
    {
        try {
            $unit = $this->unitService->toggleActive($unit);

            return response()->json([
                'success' => true,
                'message' => 'Estado de unidad actualizado',
                'data' => $unit,
            ]);
        } catch (Exception $e) {
            Log::error('Error al cambiar estado de unidad', [
                'unit_id' => $unit->id,
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

