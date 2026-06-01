<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ScopeHelper;
use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Models\VehicleCoverageRule;
use App\Models\Zone;
use App\Services\VehicleCoverageService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class VehicleCoverageRuleController extends Controller
{
    public function __construct(
        private readonly VehicleCoverageService $coverageService
    ) {
    }

    private function assertScopedVehicle(Request $request, Vehicle $vehicle): void
    {
        $companyId = ScopeHelper::companyId($request);
        if ($companyId && (int) $vehicle->company_id !== (int) $companyId) {
            abort(403, 'No autorizado');
        }
    }

    private function assertScopedRule(Request $request, VehicleCoverageRule $rule): void
    {
        $companyId = ScopeHelper::companyId($request);
        if ($companyId && (int) $rule->company_id !== (int) $companyId) {
            abort(403, 'No autorizado');
        }
    }

    private function validationRules(bool $isUpdate = false): array
    {
        $required = $isUpdate ? 'sometimes' : 'required';

        return [
            'zone_id' => "{$required}|integer|exists:zones,id",
            'districts' => "{$required}|array|min:1",
            'districts.*' => 'string|max:100',
            'days' => "{$required}|array|min:1",
            'days.*' => 'string|in:' . implode(',', VehicleCoverageService::VALID_DAYS),
            'start_time' => "{$required}|date_format:H:i",
            'end_time' => "{$required}|date_format:H:i|after:start_time",
            'priority' => 'nullable|integer|min:0|max:255',
            'max_daily_appointments' => 'nullable|integer|min:1|max:500',
            'active' => 'nullable|boolean',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    public function index(Request $request, Vehicle $vehicle): JsonResponse
    {
        $this->assertScopedVehicle($request, $vehicle);

        $rules = VehicleCoverageRule::query()
            ->where('vehicle_id', $vehicle->id)
            ->with('zone:id,name,color,districts')
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $rules,
        ]);
    }

    public function store(Request $request, Vehicle $vehicle): JsonResponse
    {
        $this->assertScopedVehicle($request, $vehicle);

        $validator = Validator::make($request->all(), $this->validationRules(false));
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $zone = Zone::findOrFail($data['zone_id']);
        $companyId = ScopeHelper::companyId($request) ?? $vehicle->company_id;

        if ((int) $zone->company_id !== (int) $companyId) {
            return response()->json(['success' => false, 'message' => 'La zona no pertenece a la empresa.'], 422);
        }

        try {
            $this->coverageService->validateDistrictsBelongToZone($zone, $data['districts']);
            $this->coverageService->assertNoOverlap(
                $vehicle,
                $data['districts'],
                $data['days'],
                $data['start_time'],
                $data['end_time']
            );
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $e->errors(),
            ], 422);
        }

        $rule = VehicleCoverageRule::create([
            'company_id' => $vehicle->company_id,
            'vehicle_id' => $vehicle->id,
            'zone_id' => $data['zone_id'],
            'districts' => array_values($data['districts']),
            'days' => array_values($data['days']),
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'priority' => $data['priority'] ?? 0,
            'max_daily_appointments' => $data['max_daily_appointments'] ?? null,
            'active' => $data['active'] ?? true,
            'notes' => $data['notes'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Regla de cobertura creada',
            'data' => $rule->load('zone:id,name,color,districts'),
        ], 201);
    }

    public function update(Request $request, VehicleCoverageRule $vehicleCoverageRule): JsonResponse
    {
        $this->assertScopedRule($request, $vehicleCoverageRule);
        $vehicle = $vehicleCoverageRule->vehicle;

        $validator = Validator::make($request->all(), $this->validationRules(true));
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $zoneId = $data['zone_id'] ?? $vehicleCoverageRule->zone_id;
        $zone = Zone::findOrFail($zoneId);
        $companyId = ScopeHelper::companyId($request) ?? $vehicleCoverageRule->company_id;

        if ((int) $zone->company_id !== (int) $companyId) {
            return response()->json(['success' => false, 'message' => 'La zona no pertenece a la empresa.'], 422);
        }

        $districts = $data['districts'] ?? $vehicleCoverageRule->districts ?? [];
        $days = $data['days'] ?? $vehicleCoverageRule->days ?? [];
        $startTime = $data['start_time'] ?? substr((string) $vehicleCoverageRule->start_time, 0, 5);
        $endTime = $data['end_time'] ?? substr((string) $vehicleCoverageRule->end_time, 0, 5);

        try {
            $this->coverageService->validateDistrictsBelongToZone($zone, $districts);
            $this->coverageService->assertNoOverlap(
                $vehicle,
                $districts,
                $days,
                $startTime,
                $endTime,
                $vehicleCoverageRule->id
            );
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $e->errors(),
            ], 422);
        }

        $vehicleCoverageRule->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Regla de cobertura actualizada',
            'data' => $vehicleCoverageRule->fresh()->load('zone:id,name,color,districts'),
        ]);
    }

    public function destroy(Request $request, VehicleCoverageRule $vehicleCoverageRule): JsonResponse
    {
        $this->assertScopedRule($request, $vehicleCoverageRule);
        $vehicleCoverageRule->delete();

        return response()->json([
            'success' => true,
            'message' => 'Regla de cobertura eliminada',
        ]);
    }

    public function availableVehicles(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'district' => 'required|string|max:100',
            'date' => 'required|date',
            'time' => 'required|date_format:H:i',
            'company_id' => 'nullable|integer|exists:companies,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $companyId = $data['company_id'] ?? ScopeHelper::companyId($request) ?? $request->user()?->company_id;
        if (!$companyId) {
            return response()->json(['success' => false, 'message' => 'company_id es requerido.'], 422);
        }

        $date = Carbon::parse($data['date']);
        $vehicles = $this->coverageService->getAvailableVehicles(
            (int) $companyId,
            $data['district'],
            $date,
            $data['time']
        );

        return response()->json([
            'success' => true,
            'data' => $vehicles->map(fn (Vehicle $vehicle) => [
                'id' => $vehicle->id,
                'name' => $vehicle->name,
                'placa' => $vehicle->placa,
                'type' => $vehicle->type,
            ])->values(),
        ]);
    }
}
