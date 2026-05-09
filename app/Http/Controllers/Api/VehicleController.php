<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Helpers\ScopeHelper;
use App\Models\VehicleMaintenance;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Exception;

class VehicleController extends Controller
{
    private const ALLOWED_STATUS_OVERRIDE = ['active', 'maintenance', 'out_of_service'];

    /**
     * Listar vehículos
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Vehicle::with(['company', 'driver']);

            if ($request->has('company_id')) {
                $query->where('company_id', $request->company_id);
            }

            if ($request->boolean('only_active', false)) {
                $query->where('activo', true);
            }

            $vehicles = $query->paginate($request->integer('per_page', 20));

            // Estado calculado (no existe columna status en la tabla):
            // - out_of_service si activo=false
            // - maintenance si tiene mantenimientos en progreso
            // - active en caso contrario
            $vehicleIds = collect($vehicles->items())->pluck('id')->filter()->values()->all();
            $inProgressIds = [];
            if (!empty($vehicleIds)) {
                $companyId = ScopeHelper::companyId($request);
                $inProgressIds = VehicleMaintenance::query()
                    ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
                    ->whereIn('vehicle_id', $vehicleIds)
                    ->where('status', 'in_progress')
                    ->pluck('vehicle_id')
                    ->map(fn ($id) => (string) $id)
                    ->all();
            }
            $inProgressSet = array_fill_keys($inProgressIds, true);

            $items = array_map(function ($v) use ($inProgressSet) {
                if (!$v) return $v;
                $arr = $v->toArray();
                $idKey = (string) ($arr['id'] ?? '');
                $activo = (bool) ($arr['activo'] ?? true);
                $status = $activo ? 'active' : 'out_of_service';

                $override = $arr['status_override'] ?? null;
                if (is_string($override) && in_array($override, self::ALLOWED_STATUS_OVERRIDE, true)) {
                    // Nunca permitir "maintenance" si el vehículo está inactivo
                    if (!$activo) {
                        $status = 'out_of_service';
                    } else {
                        $status = $override;
                    }
                } else {
                    // Fallback al status calculado por mantenimientos en progreso
                    if ($activo && $idKey !== '' && isset($inProgressSet[$idKey])) {
                        $status = 'maintenance';
                    }
                }
                $arr['status'] = $status;
                return $arr;
            }, $vehicles->items());

            return response()->json([
                'success' => true,
                'data' => $items,
                'meta' => [
                    'total' => $vehicles->total(),
                    'per_page' => $vehicles->perPage(),
                    'current_page' => $vehicles->currentPage(),
                    'last_page' => $vehicles->lastPage()
                ]
            ]);

        } catch (Exception $e) {
            Log::error("Error al listar vehículos", ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener vehículos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear vehículo
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'company_id' => 'nullable|integer|exists:companies,id',
                'driver_id' => 'nullable|integer|exists:users,id',
                'driver_name' => 'nullable|string|max:255',
                'name' => 'required|string|max:255',
                'type' => 'required|string|in:furgoneta_grande,auto_compacto,camioneta,moto',
                'placa' => 'nullable|string|max:20',
                'marca' => 'nullable|string|max:100',
                'modelo' => 'nullable|string|max:100',
                'vin' => 'nullable|string|max:64',
                'zona_operacion' => 'nullable|string|max:255',
                'anio' => 'nullable|integer|min:1900|max:' . date('Y'),
                'kilometraje' => 'nullable|integer|min:0',
                'nivel_combustible' => 'nullable|integer|min:0|max:100',
                'eficiencia' => 'nullable|integer|min:0|max:100',
                'color' => 'nullable|string|max:50',
                'capacidad_slots' => 'nullable|integer|min:1|max:50',
                'capacidad_por_categoria' => 'nullable|array',
                'zonas_asignadas' => 'nullable|array',
                'activo' => 'boolean',
                'status_override' => 'nullable|string|in:active,maintenance,out_of_service',
                'horario_disponibilidad' => 'nullable|array',
                'equipamiento' => 'nullable|array',
                'fecha_ultimo_mantenimiento' => 'nullable|date',
                'fecha_proximo_mantenimiento' => 'nullable|date',
                'fecha_seguro' => 'nullable|date',
                'fecha_itv' => 'nullable|date',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Errores de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $validator->validated();
            if (empty($data['company_id']) && $request->user()) {
                $data['company_id'] = ScopeHelper::companyId($request) ?? $request->user()->company_id;
            }
            if (isset($data['status_override'])) {
                if ($data['status_override'] === 'out_of_service') {
                    $data['activo'] = false;
                } else {
                    $data['activo'] = true;
                }
            }
            $vehicle = Vehicle::create($data);

            return response()->json([
                'success' => true,
                'message' => 'Vehículo creado exitosamente',
                'data' => $vehicle
            ], 201);

        } catch (Exception $e) {
            Log::error("Error al crear vehículo", ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error al crear vehículo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mostrar vehículo
     */
    public function show($id): JsonResponse
    {
        try {
            $vehicle = Vehicle::with(['company', 'appointments'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $vehicle
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Vehículo no encontrado'
            ], 404);
        }
    }

    /**
     * Actualizar vehículo
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $vehicle = Vehicle::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|max:255',
                'type' => 'sometimes|string|in:furgoneta_grande,auto_compacto,camioneta,moto',
                'placa' => 'nullable|string|max:20',
                'marca' => 'nullable|string|max:100',
                'modelo' => 'nullable|string|max:100',
                'vin' => 'nullable|string|max:64',
                'zona_operacion' => 'nullable|string|max:255',
                'anio' => 'nullable|integer|min:1900|max:' . date('Y'),
                'kilometraje' => 'nullable|integer|min:0',
                'nivel_combustible' => 'nullable|integer|min:0|max:100',
                'eficiencia' => 'nullable|integer|min:0|max:100',
                'driver_id' => 'nullable|integer|exists:users,id',
                'driver_name' => 'nullable|string|max:255',
                'activo' => 'boolean',
                'status_override' => 'nullable|string|in:active,maintenance,out_of_service',
                'horario_disponibilidad' => 'nullable|array',
                'equipamiento' => 'nullable|array',
                'current_latitude' => 'nullable|numeric',
                'current_longitude' => 'nullable|numeric',
                'fecha_ultimo_mantenimiento' => 'nullable|date',
                'fecha_proximo_mantenimiento' => 'nullable|date',
                'fecha_seguro' => 'nullable|date',
                'fecha_itv' => 'nullable|date',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Errores de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $validator->validated();

            // Si se actualiza la ubicación, actualizar timestamp
            if (isset($data['current_latitude']) || isset($data['current_longitude'])) {
                $data['last_location_update'] = now();
            }
            if (isset($data['status_override'])) {
                if ($data['status_override'] === 'out_of_service') {
                    $data['activo'] = false;
                } else {
                    $data['activo'] = true;
                }
            }

            $vehicle->update($data);

            return response()->json([
                'success' => true,
                'message' => 'Vehículo actualizado exitosamente',
                'data' => $vehicle
            ]);

        } catch (Exception $e) {
            Log::error("Error al actualizar vehículo", ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar vehículo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar vehículo
     */
    public function destroy($id): JsonResponse
    {
        try {
            $vehicle = Vehicle::findOrFail($id);
            $vehicle->delete();

            return response()->json([
                'success' => true,
                'message' => 'Vehículo eliminado exitosamente'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar vehículo'
            ], 500);
        }
    }
}
