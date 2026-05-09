<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ScopeHelper;
use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Models\VehicleExpense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class VehicleExpenseController extends Controller
{
    private function assertScopedVehicle(Request $request, Vehicle $vehicle): void
    {
        $companyId = ScopeHelper::companyId($request);
        if ($companyId && (int) $vehicle->company_id !== (int) $companyId) {
            abort(403, 'No autorizado');
        }
    }

    private function assertScopedExpense(Request $request, VehicleExpense $expense): void
    {
        $companyId = ScopeHelper::companyId($request);
        if ($companyId && (int) $expense->company_id !== (int) $companyId) {
            abort(403, 'No autorizado');
        }
    }

    /**
     * Listado global (opcionalmente filtrado por vehicle_id).
     */
    public function list(Request $request): JsonResponse
    {
        $companyId = ScopeHelper::companyId($request);

        $items = VehicleExpense::query()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->when($request->filled('vehicle_id'), fn ($q) => $q->where('vehicle_id', $request->integer('vehicle_id')))
            ->orderByDesc('date')
            ->orderByDesc('id')
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

        $items = VehicleExpense::query()
            ->where('vehicle_id', $vehicle->id)
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

    public function store(Request $request, Vehicle $vehicle): JsonResponse
    {
        $this->assertScopedVehicle($request, $vehicle);

        $validator = Validator::make($request->all(), [
            'category' => 'required|string|max:80',
            'amount' => 'required|numeric|min:0',
            'date' => 'required|date',
            'description' => 'nullable|string',
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

        $row = VehicleExpense::create([
            'company_id' => $companyId,
            'vehicle_id' => $vehicle->id,
            'category' => $data['category'],
            'amount' => $data['amount'],
            'date' => $data['date'],
            'description' => $data['description'] ?? null,
            'account_code' => $data['accountCode'] ?? null,
            'created_by' => $request->user()?->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Gasto registrado',
            'data' => $row,
        ], 201);
    }

    public function show(Request $request, VehicleExpense $vehicleExpense): JsonResponse
    {
        $this->assertScopedExpense($request, $vehicleExpense);
        return response()->json(['success' => true, 'data' => $vehicleExpense]);
    }

    public function update(Request $request, VehicleExpense $vehicleExpense): JsonResponse
    {
        $this->assertScopedExpense($request, $vehicleExpense);

        $validator = Validator::make($request->all(), [
            'category' => 'sometimes|string|max:80',
            'amount' => 'sometimes|numeric|min:0',
            'date' => 'sometimes|date',
            'description' => 'nullable|string',
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
        $vehicleExpense->update([
            'category' => $data['category'] ?? $vehicleExpense->category,
            'amount' => array_key_exists('amount', $data) ? $data['amount'] : $vehicleExpense->amount,
            'date' => $data['date'] ?? $vehicleExpense->date,
            'description' => array_key_exists('description', $data) ? $data['description'] : $vehicleExpense->description,
            'account_code' => array_key_exists('accountCode', $data) ? $data['accountCode'] : $vehicleExpense->account_code,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Gasto actualizado',
            'data' => $vehicleExpense->fresh(),
        ]);
    }

    public function destroy(Request $request, VehicleExpense $vehicleExpense): JsonResponse
    {
        $this->assertScopedExpense($request, $vehicleExpense);
        $vehicleExpense->delete();
        return response()->json(['success' => true, 'message' => 'Gasto eliminado']);
    }
}

