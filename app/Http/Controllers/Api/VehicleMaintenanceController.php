<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ScopeHelper;
use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Models\VehicleMaintenance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class VehicleMaintenanceController extends Controller
{
    private function assertScopedVehicle(Request $request, Vehicle $vehicle): void
    {
        $companyId = ScopeHelper::companyId($request);
        if ($companyId && (int) $vehicle->company_id !== (int) $companyId) {
            abort(403, 'No autorizado');
        }
    }

    private function assertScopedMaintenance(Request $request, VehicleMaintenance $maintenance): void
    {
        $companyId = ScopeHelper::companyId($request);
        if ($companyId && (int) $maintenance->company_id !== (int) $companyId) {
            abort(403, 'No autorizado');
        }
    }

    /**
     * Listado global (opcionalmente filtrado por vehicle_id).
     */
    public function list(Request $request): JsonResponse
    {
        $companyId = ScopeHelper::companyId($request);

        $items = VehicleMaintenance::query()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->when($request->filled('vehicle_id'), fn ($q) => $q->where('vehicle_id', $request->integer('vehicle_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('date')
            ->orderByDesc('id')
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

    public function index(Request $request, Vehicle $vehicle): JsonResponse
    {
        $this->assertScopedVehicle($request, $vehicle);

        $items = VehicleMaintenance::query()
            ->where('vehicle_id', $vehicle->id)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 100));

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
            'status' => 'required|string|in:completed,in_progress',
            'description' => 'nullable|string',
            'date' => 'required|date',
            'cost' => 'nullable|numeric|min:0',
            'workshopRuc' => 'nullable|string|regex:/^\d{11}$/',
            'workshop' => 'nullable|string|max:255',
            'workshopAddress' => 'nullable|string|max:255',
            'workshopPhone' => 'nullable|string|max:50',
            'nextDue' => 'nullable|date',
            'accountCode' => 'nullable|string|max:20',
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

        $row = VehicleMaintenance::create([
            'company_id' => $companyId,
            'vehicle_id' => $vehicle->id,
            'type' => $data['type'],
            'status' => $data['status'],
            'description' => $data['description'] ?? null,
            'date' => $data['date'],
            'cost' => $data['cost'] ?? 0,
            'workshop_ruc' => $data['workshopRuc'] ?? null,
            'workshop_name' => $data['workshop'] ?? null,
            'workshop_address' => $data['workshopAddress'] ?? null,
            'workshop_phone' => $data['workshopPhone'] ?? null,
            'next_due' => $data['nextDue'] ?? null,
            'account_code' => $data['accountCode'] ?? null,
            'created_by' => $request->user()?->id,
        ]);

        // Actualizar fechas del vehículo (best-effort)
        $vehicle->update([
            'fecha_ultimo_mantenimiento' => $data['date'],
            'fecha_proximo_mantenimiento' => $data['nextDue'] ?? $vehicle->fecha_proximo_mantenimiento,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Mantenimiento registrado',
            'data' => $row,
        ], 201);
    }

    public function show(Request $request, VehicleMaintenance $vehicleMaintenance): JsonResponse
    {
        $this->assertScopedMaintenance($request, $vehicleMaintenance);
        return response()->json(['success' => true, 'data' => $vehicleMaintenance]);
    }

    public function update(Request $request, VehicleMaintenance $vehicleMaintenance): JsonResponse
    {
        $this->assertScopedMaintenance($request, $vehicleMaintenance);

        $validator = Validator::make($request->all(), [
            'type' => 'sometimes|string|max:120',
            'status' => 'sometimes|string|in:completed,in_progress',
            'description' => 'nullable|string',
            'date' => 'sometimes|date',
            'cost' => 'nullable|numeric|min:0',
            'workshopRuc' => 'nullable|string|regex:/^\d{11}$/',
            'workshop' => 'nullable|string|max:255',
            'workshopAddress' => 'nullable|string|max:255',
            'workshopPhone' => 'nullable|string|max:50',
            'nextDue' => 'nullable|date',
            'accountCode' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        $vehicleMaintenance->update([
            'type' => $data['type'] ?? $vehicleMaintenance->type,
            'status' => $data['status'] ?? $vehicleMaintenance->status,
            'description' => array_key_exists('description', $data) ? $data['description'] : $vehicleMaintenance->description,
            'date' => $data['date'] ?? $vehicleMaintenance->date,
            'cost' => array_key_exists('cost', $data) ? ($data['cost'] ?? 0) : $vehicleMaintenance->cost,
            'workshop_ruc' => array_key_exists('workshopRuc', $data) ? ($data['workshopRuc'] ?? null) : $vehicleMaintenance->workshop_ruc,
            'workshop_name' => array_key_exists('workshop', $data) ? ($data['workshop'] ?? null) : $vehicleMaintenance->workshop_name,
            'workshop_address' => array_key_exists('workshopAddress', $data) ? ($data['workshopAddress'] ?? null) : $vehicleMaintenance->workshop_address,
            'workshop_phone' => array_key_exists('workshopPhone', $data) ? ($data['workshopPhone'] ?? null) : $vehicleMaintenance->workshop_phone,
            'next_due' => array_key_exists('nextDue', $data) ? ($data['nextDue'] ?? null) : $vehicleMaintenance->next_due,
            'account_code' => array_key_exists('accountCode', $data) ? ($data['accountCode'] ?? null) : $vehicleMaintenance->account_code,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Mantenimiento actualizado',
            'data' => $vehicleMaintenance->fresh(),
        ]);
    }

    public function destroy(Request $request, VehicleMaintenance $vehicleMaintenance): JsonResponse
    {
        $this->assertScopedMaintenance($request, $vehicleMaintenance);
        $vehicleMaintenance->delete();
        return response()->json(['success' => true, 'message' => 'Mantenimiento eliminado']);
    }
}

