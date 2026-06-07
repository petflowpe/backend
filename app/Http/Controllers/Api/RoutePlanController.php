<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Route as RouteModel;
use App\Models\RouteStop;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class RoutePlanController extends Controller
{
    private function resolveCompanyId(Request $request): int
    {
        return (int) (
            $request->integer('company_id')
            ?: \App\Helpers\ScopeHelper::companyId($request)
            ?: $request->user()?->company_id
            ?: 1
        );
    }

    private function formatAppointmentStop(Appointment $apt, int $order): array
    {
        $time = $apt->time;
        if ($time instanceof \DateTimeInterface) {
            $time = $time->format('H:i');
        } else {
            $time = substr((string) $time, 0, 5);
        }

        return [
            'order' => $order,
            'appointment_id' => $apt->id,
            'client_id' => $apt->client_id,
            'time' => $time,
            'duration' => (int) ($apt->duration ?? 60),
            'status' => $apt->status,
            'service_name' => $apt->service_name,
            'service_category' => $apt->service_category,
            'address' => $apt->address,
            'district' => $apt->district,
            'tracking_code' => $apt->tracking_code,
            'client' => [
                'id' => $apt->client_id,
                'name' => $apt->client?->razon_social ?? $apt->client?->nombre_comercial,
                'phone' => $apt->client?->telefono,
            ],
            'pet' => [
                'id' => $apt->pet_id,
                'name' => $apt->pet?->name,
                'species' => $apt->pet?->species,
                'breed' => $apt->pet?->breed,
            ],
        ];
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $companyId = $this->resolveCompanyId($request);
            $query = RouteModel::with(['zone', 'vehicle', 'stops.client', 'stops.appointment.pet'])
                ->where('company_id', $companyId)
                ->orderByDesc('date')
                ->orderByDesc('id');

            if ($request->filled('date')) {
                $query->whereDate('date', $request->date);
            }
            if ($request->filled('vehicle_id')) {
                $query->where('vehicle_id', $request->integer('vehicle_id'));
            }
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }
            if ($request->filled('zone_id')) {
                $query->where('zone_id', $request->zone_id);
            }

            $perPage = $request->integer('per_page', 20);
            $routes = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $routes->items(),
                'meta' => [
                    'total' => $routes->total(),
                    'per_page' => $routes->perPage(),
                    'current_page' => $routes->currentPage(),
                    'last_page' => $routes->lastPage(),
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Error listar rutas', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Citas del día por vehículo (base para planificar ruta).
     */
    public function dailySchedule(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => 'nullable|date',
            'vehicle_id' => 'required|integer|exists:vehicles,id',
            'company_id' => 'nullable|integer|exists:companies,id',
        ]);

        $companyId = $this->resolveCompanyId($request);
        $date = $validated['date'] ?? now()->toDateString();
        $vehicleId = (int) $validated['vehicle_id'];

        $vehicle = Vehicle::where('company_id', $companyId)->findOrFail($vehicleId);

        $appointments = Appointment::with(['client', 'pet'])
            ->where('company_id', $companyId)
            ->where('vehicle_id', $vehicleId)
            ->whereDate('date', $date)
            ->whereNotIn('status', ['Cancelada'])
            ->orderBy('time')
            ->get();

        $stops = $appointments->values()->map(
            fn (Appointment $apt, int $index) => $this->formatAppointmentStop($apt, $index)
        );

        $route = RouteModel::with(['stops.client', 'stops.appointment'])
            ->where('company_id', $companyId)
            ->where('vehicle_id', $vehicleId)
            ->whereDate('date', $date)
            ->first();

        $completed = $appointments->where('status', 'Completada')->count();
        $pending = $appointments->whereIn('status', ['Pendiente', 'Confirmada'])->count();
        $inProgress = $appointments->where('status', 'En Proceso')->count();

        return response()->json([
            'success' => true,
            'data' => [
                'date' => $date,
                'vehicle' => [
                    'id' => $vehicle->id,
                    'name' => $vehicle->name,
                    'placa' => $vehicle->placa,
                    'driver_name' => $vehicle->driver_name,
                ],
                'stops' => $stops,
                'route_plan' => $route,
                'stats' => [
                    'total' => $appointments->count(),
                    'completed' => $completed,
                    'pending' => $pending,
                    'in_progress' => $inProgress,
                ],
            ],
        ]);
    }

    /**
     * Día de trabajo del chofer autenticado (vehículo asignado por driver_id).
     */
    public function driverDay(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'No autenticado'], 401);
        }

        $date = $request->input('date', now()->toDateString());
        $companyId = $this->resolveCompanyId($request);

        $vehicle = Vehicle::query()
            ->where('company_id', $companyId)
            ->where('activo', true)
            ->where('driver_id', $user->id)
            ->first();

        if (!$vehicle) {
            return response()->json([
                'success' => true,
                'data' => [
                    'date' => $date,
                    'vehicle' => null,
                    'stops' => [],
                    'route_plan' => null,
                    'stats' => ['total' => 0, 'completed' => 0, 'pending' => 0, 'in_progress' => 0],
                    'message' => 'No tienes un vehículo asignado. Contacta a operaciones.',
                ],
            ]);
        }

        $request->merge(['vehicle_id' => $vehicle->id, 'date' => $date]);

        return $this->dailySchedule($request);
    }

    /**
     * Guardar plan de ruta desde orden de citas.
     */
    public function saveFromAppointments(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_id' => 'nullable|integer|exists:companies,id',
            'vehicle_id' => 'required|integer|exists:vehicles,id',
            'date' => 'required|date',
            'name' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:planned,in_progress,completed,cancelled',
            'appointment_ids' => 'required|array|min:1',
            'appointment_ids.*' => 'integer|exists:appointments,id',
        ]);

        $companyId = $this->resolveCompanyId($request);
        $vehicleId = (int) $validated['vehicle_id'];
        $date = $validated['date'];

        try {
            DB::beginTransaction();

            $route = RouteModel::query()
                ->where('company_id', $companyId)
                ->where('vehicle_id', $vehicleId)
                ->whereDate('date', $date)
                ->first();

            $vehicle = Vehicle::find($vehicleId);
            $routeName = $validated['name'] ?? ('Ruta ' . ($vehicle?->name ?? $vehicleId) . ' ' . $date);

            if ($route) {
                $route->update([
                    'name' => $routeName,
                    'status' => $validated['status'] ?? $route->status,
                ]);
                $route->stops()->delete();
            } else {
                $route = RouteModel::create([
                    'company_id' => $companyId,
                    'vehicle_id' => $vehicleId,
                    'name' => $routeName,
                    'date' => $date,
                    'status' => $validated['status'] ?? 'planned',
                    'auto_optimize' => false,
                ]);
            }

            foreach ($validated['appointment_ids'] as $order => $appointmentId) {
                $appointment = Appointment::where('company_id', $companyId)->findOrFail($appointmentId);
                RouteStop::create([
                    'route_id' => $route->id,
                    'client_id' => $appointment->client_id,
                    'appointment_id' => $appointment->id,
                    'order' => $order,
                ]);
            }

            DB::commit();

            $route->load(['vehicle', 'stops.client', 'stops.appointment.pet']);

            return response()->json([
                'success' => true,
                'message' => 'Ruta guardada correctamente',
                'data' => $route,
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error guardar ruta desde citas', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo guardar la ruta: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_id' => 'nullable|integer|exists:companies,id',
            'zone_id' => 'nullable|integer|exists:zones,id',
            'vehicle_id' => 'nullable|integer|exists:vehicles,id',
            'name' => 'required|string|max:255',
            'date' => 'required|date',
            'start_time' => 'nullable|string',
            'end_time' => 'nullable|string',
            'status' => 'nullable|string|in:planned,in_progress,completed,cancelled',
            'auto_optimize' => 'boolean',
            'stops' => 'nullable|array',
            'stops.*.client_id' => 'required_with:stops|integer|exists:clients,id',
            'stops.*.appointment_id' => 'nullable|integer|exists:appointments,id',
            'stops.*.order' => 'nullable|integer|min:0',
        ]);
        $companyId = $this->resolveCompanyId($request);
        if (!$companyId) {
            return response()->json(['message' => 'company_id es requerido o el usuario debe tener empresa asignada.'], 422);
        }
        $route = RouteModel::create([
            'company_id' => $companyId,
            'zone_id' => $validated['zone_id'] ?? null,
            'vehicle_id' => $validated['vehicle_id'] ?? null,
            'name' => $validated['name'],
            'date' => $validated['date'],
            'start_time' => $validated['start_time'] ?? null,
            'end_time' => $validated['end_time'] ?? null,
            'status' => $validated['status'] ?? 'planned',
            'auto_optimize' => $validated['auto_optimize'] ?? true,
        ]);
        if (!empty($validated['stops'])) {
            foreach ($validated['stops'] as $i => $stop) {
                RouteStop::create([
                    'route_id' => $route->id,
                    'client_id' => $stop['client_id'],
                    'appointment_id' => $stop['appointment_id'] ?? null,
                    'order' => $stop['order'] ?? $i,
                ]);
            }
        }
        $route->load(['zone', 'vehicle', 'stops.client', 'stops.appointment']);
        return response()->json(['success' => true, 'data' => $route], 201);
    }

    public function show(RouteModel $route): JsonResponse
    {
        $route->load(['zone', 'vehicle', 'stops.client', 'stops.appointment.pet']);
        return response()->json(['success' => true, 'data' => $route]);
    }

    public function update(Request $request, RouteModel $route): JsonResponse
    {
        $validated = $request->validate([
            'zone_id' => 'nullable|integer|exists:zones,id',
            'vehicle_id' => 'nullable|integer|exists:vehicles,id',
            'name' => 'sometimes|string|max:255',
            'date' => 'sometimes|date',
            'start_time' => 'nullable|string',
            'end_time' => 'nullable|string',
            'status' => 'nullable|string|in:planned,in_progress,completed,cancelled',
            'auto_optimize' => 'boolean',
            'stops' => 'nullable|array',
            'stops.*.client_id' => 'required_with:stops|integer|exists:clients,id',
            'stops.*.appointment_id' => 'nullable|integer|exists:appointments,id',
            'stops.*.order' => 'nullable|integer|min:0',
        ]);
        $route->update(array_filter([
            'zone_id' => $validated['zone_id'] ?? null,
            'vehicle_id' => $validated['vehicle_id'] ?? null,
            'name' => $validated['name'] ?? null,
            'date' => $validated['date'] ?? null,
            'start_time' => $validated['start_time'] ?? null,
            'end_time' => $validated['end_time'] ?? null,
            'status' => $validated['status'] ?? null,
            'auto_optimize' => $validated['auto_optimize'] ?? null,
        ], fn ($v) => $v !== null));
        if (array_key_exists('stops', $validated)) {
            $route->stops()->delete();
            foreach ($validated['stops'] ?? [] as $i => $stop) {
                RouteStop::create([
                    'route_id' => $route->id,
                    'client_id' => $stop['client_id'],
                    'appointment_id' => $stop['appointment_id'] ?? null,
                    'order' => $stop['order'] ?? $i,
                ]);
            }
        }
        $route->load(['zone', 'vehicle', 'stops.client', 'stops.appointment.pet']);
        return response()->json(['success' => true, 'data' => $route]);
    }

    public function destroy(RouteModel $route): JsonResponse
    {
        $route->stops()->delete();
        $route->delete();
        return response()->json(['success' => true, 'message' => 'Ruta eliminada']);
    }
}
