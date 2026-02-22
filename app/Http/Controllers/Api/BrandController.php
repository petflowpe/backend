<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Brand\StoreBrandRequest;
use App\Http\Requests\Brand\UpdateBrandRequest;
use App\Models\Brand;
use App\Services\BrandService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

class BrandController extends Controller
{
    public function __construct(
        private BrandService $brandService
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $companyId = $request->integer('company_id');
            $onlyActive = $request->boolean('only_active', false);
            $search = $request->get('search');
            $perPage = $request->integer('per_page', 15);

            if (!$companyId) {
                return response()->json([
                    'success' => false,
                    'message' => 'company_id es requerido',
                ], 400);
            }

            $result = $this->brandService->list($companyId, $onlyActive, $search, $perPage);

            $response = [
                'success' => true,
                'data' => $result['data'],
            ];

            if (isset($result['pagination'])) {
                $response['pagination'] = $result['pagination'];
            }

            return response()->json($response);
        } catch (Exception $e) {
            Log::error('Error al listar marcas', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener marcas',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function store(StoreBrandRequest $request): JsonResponse
    {
        try {
            $brand = $this->brandService->create($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Marca creada exitosamente',
                'data' => $brand,
            ], 201);
        } catch (Exception $e) {
            Log::error('Error al crear marca', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error al crear marca',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function update(UpdateBrandRequest $request, Brand $brand): JsonResponse
    {
        try {
            $brand = $this->brandService->update($brand, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Marca actualizada exitosamente',
                'data' => $brand,
            ]);
        } catch (Exception $e) {
            Log::error('Error al actualizar marca', [
                'brand_id' => $brand->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar marca',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function destroy(Brand $brand): JsonResponse
    {
        try {
            $this->brandService->delete($brand);

            return response()->json([
                'success' => true,
                'message' => 'Marca eliminada exitosamente',
            ]);
        } catch (Exception $e) {
            Log::error('Error al eliminar marca', [
                'brand_id' => $brand->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 400);
        }
    }

    public function toggleActive(Brand $brand): JsonResponse
    {
        try {
            $brand = $this->brandService->toggleActive($brand);

            return response()->json([
                'success' => true,
                'message' => 'Estado de marca actualizado',
                'data' => $brand,
            ]);
        } catch (Exception $e) {
            Log::error('Error al cambiar estado de marca', [
                'brand_id' => $brand->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al cambiar estado',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function getKPIs(Request $request): JsonResponse
    {
        try {
            $companyId = $request->input('company_id');
            
            if (!$companyId) {
                return response()->json([
                    'success' => false,
                    'message' => 'company_id es requerido',
                ], 400);
            }

            $search = $request->input('search');
            $onlyActive = $request->input('only_active');
            $hasLogo = $request->input('has_logo');

            // Convertir only_active a boolean o null
            if ($onlyActive === null || $onlyActive === '') {
                $onlyActive = null;
            } else {
                $onlyActive = filter_var($onlyActive, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            }

            // Convertir has_logo a boolean o null
            if ($hasLogo === null || $hasLogo === '') {
                $hasLogo = null;
            } else {
                $hasLogo = filter_var($hasLogo, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            }

            $kpis = $this->brandService->getKPIs($companyId, $search, $onlyActive, $hasLogo);

            return response()->json([
                'success' => true,
                'message' => 'KPIs de marcas obtenidos exitosamente',
                'data' => $kpis,
            ]);
        } catch (Exception $e) {
            Log::error('Error al obtener KPIs de marcas', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener KPIs',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}

