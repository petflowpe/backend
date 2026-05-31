<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ScopeHelper;
use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Models\VehicleMaintenance;
use App\Models\VehicleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class VehicleServiceController extends Controller
{
    private function assertScopedVehicle(Request $request, Vehicle $vehicle): void
    {
        $companyId = ScopeHelper::companyId($request);
        if ($companyId && (int) $vehicle->company_id !== (int) $companyId) {
            abort(403, 'No autorizado');
        }
    }

    private function assertScopedService(Request $request, VehicleService $service): void
    {
        $companyId = ScopeHelper::companyId($request);
        if ($companyId && (int) $service->company_id !== (int) $companyId) {
            abort(403, 'No autorizado');
        }
    }

    /**
     * Listado global (opcionalmente filtrado por vehicle_id/status).
     */
    public function list(Request $request): JsonResponse
    {
        $companyId = ScopeHelper::companyId($request);

        $items = VehicleService::query()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->when($request->filled('vehicle_id'), fn ($q) => $q->where('vehicle_id', $request->integer('vehicle_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderBy('due_date')
            ->orderBy('id')
            ->paginate($request->integer('per_page', 500));

        return response()->json([
            'success' => true,
            'data' => $items->items(),
            'meta' => [
                'total' => $items->total(),
                'per_page' => $items->perPage(),
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
            ],
        ]);
    }

    public function index(Request $request, Vehicle $vehicle): JsonResponse
    {
        $this->assertScopedVehicle($request, $vehicle);

        $items = VehicleService::query()
            ->where('vehicle_id', $vehicle->id)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderBy('due_date')
            ->orderBy('id')
            ->paginate($request->integer('per_page', 200));

        return response()->json([
            'success' => true,
            'data' => $items->items(),
            'meta' => [
                'total' => $items->total(),
                'per_page' => $items->perPage(),
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
            ],
        ]);
    }

    public function store(Request $request, Vehicle $vehicle): JsonResponse
    {
        $this->assertScopedVehicle($request, $vehicle);

        $validator = Validator::make($request->all(), [
            'type' => 'required|string|max:120',
            'description' => 'nullable|string',
            'dueDate' => 'required|date',
            'priority' => 'required|string|in:low,medium,high',
            'estimatedCost' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $companyId = ScopeHelper::companyId($request) ?? $vehicle->company_id;

        $row = VehicleService::create([
            'company_id' => $companyId,
            'vehicle_id' => $vehicle->id,
            'type' => $data['type'],
            'description' => $data['description'] ?? null,
            'due_date' => $data['dueDate'],
            'priority' => $data['priority'],
            'estimated_cost' => $data['estimatedCost'] ?? 0,
            'status' => 'pending',
            'created_by' => $request->user()?->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Servicio programado',
            'data' => $row,
        ], 201);
    }

    public function show(Request $request, VehicleService $vehicleService): JsonResponse
    {
        $this->assertScopedService($request, $vehicleService);
        return response()->json(['success' => true, 'data' => $vehicleService]);
    }

    public function update(Request $request, VehicleService $vehicleService): JsonResponse
    {
        $this->assertScopedService($request, $vehicleService);

        $validator = Validator::make($request->all(), [
            'type' => 'sometimes|string|max:120',
            'description' => 'nullable|string',
            'dueDate' => 'sometimes|date',
            'priority' => 'sometimes|string|in:low,medium,high',
            'estimatedCost' => 'nullable|numeric|min:0',
            'status' => 'sometimes|string|in:pending,completed,cancelled',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $vehicleService->update([
            'type' => $data['type'] ?? $vehicleService->type,
            'description' => array_key_exists('description', $data) ? $data['description'] : $vehicleService->description,
            'due_date' => $data['dueDate'] ?? $vehicleService->due_date,
            'priority' => $data['priority'] ?? $vehicleService->priority,
            'estimated_cost' => array_key_exists('estimatedCost', $data) ? ($data['estimatedCost'] ?? 0) : $vehicleService->estimated_cost,
            'status' => $data['status'] ?? $vehicleService->status,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Servicio actualizado',
            'data' => $vehicleService->fresh(),
        ]);
    }

    public function destroy(Request $request, VehicleService $vehicleService): JsonResponse
    {
        $this->assertScopedService($request, $vehicleService);
        $vehicleService->delete();
        return response()->json(['success' => true, 'message' => 'Servicio eliminado']);
    }

    /**
     * Enviar a mantenimiento: crea un mantenimiento en progreso y saca el servicio de "pendientes".
     *
     * Flujo:
     * Programas un servicio -> llega la fecha -> lo envías a mantenimiento -> aparece en Mantenimientos (En Progreso).
     */
    public function sendToMaintenance(Request $request, VehicleService $vehicleService): JsonResponse
    {
        $this->assertScopedService($request, $vehicleService);

        if ($vehicleService->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'El servicio ya no está pendiente',
            ], 409);
        }

        $userId = $request->user()?->id;

        $maintenance = null;
        DB::transaction(function () use ($vehicleService, $userId, &$maintenance) {
            // Sacar de pendientes para que no siga apareciendo en "Próximos Servicios".
            $vehicleService->update([
                'status' => 'cancelled',
            ]);

            $date = $vehicleService->due_date ? $vehicleService->due_date->toDateString() : now()->toDateString();

            $maintenance = VehicleMaintenance::create([
                'company_id' => $vehicleService->company_id,
                'vehicle_id' => $vehicleService->vehicle_id,
                'type' => $vehicleService->type,
                'status' => 'in_progress',
                'description' => $vehicleService->description,
                'date' => $date,
                'cost' => $vehicleService->estimated_cost ?? 0,
                'account_code' => null,
                'created_by' => $userId,
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Enviado a mantenimiento',
            'data' => [
                'service' => $vehicleService->fresh(),
                'maintenance' => $maintenance,
            ],
        ], 201);
    }

    /**
     * Completar servicio: lo marca como completado y crea un mantenimiento (mínimo).
     */
    public function complete(Request $request, VehicleService $vehicleService): JsonResponse
    {
        $this->assertScopedService($request, $vehicleService);

        if ($vehicleService->status === 'completed') {
            return response()->json(['success' => true, 'data' => $vehicleService]);
        }

        $userId = $request->user()?->id;

        DB::transaction(function () use ($vehicleService, $userId) {
            $vehicleService->update([
                'status' => 'completed',
                'completed_at' => now(),
                'completed_by' => $userId,
            ]);

            VehicleMaintenance::create([
                'company_id' => $vehicleService->company_id,
                'vehicle_id' => $vehicleService->vehicle_id,
                'type' => $vehicleService->type,
                'status' => 'completed',
                'description' => $vehicleService->description,
                'date' => now()->toDateString(),
                'cost' => $vehicleService->estimated_cost ?? 0,
                'account_code' => null,
                'created_by' => $userId,
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Servicio completado',
            'data' => $vehicleService->fresh(),
        ]);
    }
}

