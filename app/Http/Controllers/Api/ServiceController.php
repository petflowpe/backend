<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Service\StoreServiceRequest;
use App\Http\Requests\Service\UpdateServiceRequest;
use App\Models\Service;
use App\Services\ServiceCatalogService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ServiceController extends Controller
{
    public function __construct(
        private ServiceCatalogService $serviceCatalog
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $companyId = $request->integer('company_id')
                ?: ($request->attributes->get('scope_company_id') ? (int) $request->attributes->get('scope_company_id') : null)
                ?: ($request->user()?->company_id);

            if (! $companyId) {
                return response()->json([
                    'success' => false,
                    'message' => 'company_id es requerido',
                ], 422);
            }

            $services = $this->serviceCatalog->list(
                (int) $companyId,
                $request->boolean('only_active', false),
                $request->get('search')
            );

            return response()->json([
                'success' => true,
                'data' => $services,
            ]);
        } catch (Exception $e) {
            Log::error('Error al listar servicios', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener servicios',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function store(StoreServiceRequest $request): JsonResponse
    {
        try {
            $service = $this->serviceCatalog->create($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Servicio creado exitosamente',
                'data' => $service,
            ], 201);
        } catch (Exception $e) {
            Log::error('Error al crear servicio', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error al crear servicio',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function show(Service $service): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $service,
        ]);
    }

    public function update(UpdateServiceRequest $request, Service $service): JsonResponse
    {
        try {
            $service = $this->serviceCatalog->update($service, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Servicio actualizado exitosamente',
                'data' => $service,
            ]);
        } catch (Exception $e) {
            Log::error('Error al actualizar servicio', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar servicio',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function destroy(Service $service): JsonResponse
    {
        try {
            $this->serviceCatalog->delete($service);

            return response()->json([
                'success' => true,
                'message' => 'Servicio desactivado',
            ]);
        } catch (Exception $e) {
            Log::error('Error al desactivar servicio', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error al desactivar servicio',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function activate(Service $service): JsonResponse
    {
        try {
            $service = $this->serviceCatalog->activate($service);

            return response()->json([
                'success' => true,
                'message' => 'Servicio activado',
                'data' => $service,
            ]);
        } catch (Exception $e) {
            Log::error('Error al activar servicio', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error al activar servicio',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
