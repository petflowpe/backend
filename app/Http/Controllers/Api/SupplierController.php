<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Supplier\StoreSupplierRequest;
use App\Http\Requests\Supplier\UpdateSupplierRequest;
use App\Models\Supplier;
use App\Services\SupplierService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

class SupplierController extends Controller
{
    public function __construct(
        private SupplierService $supplierService
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

            $result = $this->supplierService->list($companyId, $onlyActive, $search, $perPage);

            $response = [
                'success' => true,
                'data' => $result['data'],
            ];

            if (isset($result['pagination'])) {
                $response['pagination'] = $result['pagination'];
            }

            return response()->json($response);
        } catch (Exception $e) {
            Log::error('Error al listar proveedores', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener proveedores',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function store(StoreSupplierRequest $request): JsonResponse
    {
        try {
            $supplier = $this->supplierService->create($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Proveedor creado exitosamente',
                'data' => $supplier,
            ], 201);
        } catch (Exception $e) {
            Log::error('Error al crear proveedor', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error al crear proveedor',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): JsonResponse
    {
        try {
            $supplier = $this->supplierService->update($supplier, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Proveedor actualizado exitosamente',
                'data' => $supplier,
            ]);
        } catch (Exception $e) {
            Log::error('Error al actualizar proveedor', [
                'supplier_id' => $supplier->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar proveedor',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function destroy(Supplier $supplier): JsonResponse
    {
        try {
            $this->supplierService->delete($supplier);

            return response()->json([
                'success' => true,
                'message' => 'Proveedor eliminado exitosamente',
            ]);
        } catch (Exception $e) {
            Log::error('Error al eliminar proveedor', [
                'supplier_id' => $supplier->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 400);
        }
    }

    public function toggleActive(Supplier $supplier): JsonResponse
    {
        try {
            $supplier = $this->supplierService->toggleActive($supplier);

            return response()->json([
                'success' => true,
                'message' => 'Estado de proveedor actualizado',
                'data' => $supplier,
            ]);
        } catch (Exception $e) {
            Log::error('Error al cambiar estado de proveedor', [
                'supplier_id' => $supplier->id,
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
            $documentType = $request->input('document_type');

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

            $kpis = $this->supplierService->getKPIs($companyId, $search, $onlyActive, $hasLogo, $documentType);

            return response()->json([
                'success' => true,
                'message' => 'KPIs de proveedores obtenidos exitosamente',
                'data' => $kpis,
            ]);
        } catch (Exception $e) {
            Log::error('Error al obtener KPIs de proveedores', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener KPIs',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}

