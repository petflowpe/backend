<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ScopeHelper;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CashSessionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = CashSession::with(['company', 'branch', 'user', 'vehicle'])
            ->when($request->filled('company_id'), fn ($q) => $q->where('company_id', $request->integer('company_id')))
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('vehicle_id'), fn ($q) => $q->where('vehicle_id', $request->integer('vehicle_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->get('status')))
            ->orderByDesc('opened_at');

        $perPage = min(max((int) $request->get('per_page', 20), 1), 200);

        return response()->json([
            'success' => true,
            'data' => $query->paginate($perPage),
        ]);
    }

    public function current(Request $request): JsonResponse
    {
        $request->validate([
            'company_id' => 'required|integer|exists:companies,id',
            'branch_id' => 'nullable|integer|exists:branches,id',
            'vehicle_id' => 'nullable|integer|exists:vehicles,id',
        ]);

        $query = CashSession::with(['branch', 'user', 'vehicle'])
            ->where('company_id', $request->integer('company_id'))
            ->where('status', 'OPEN');

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->integer('branch_id'));
        }
        if ($request->filled('vehicle_id')) {
            $query->where('vehicle_id', $request->integer('vehicle_id'));
        }

        $session = $query->orderByDesc('opened_at')->first();

        return response()->json([
            'success' => true,
            'data' => $session,
        ]);
    }

    public function daySummary(Request $request): JsonResponse
    {
        $request->validate([
            'company_id' => 'required|integer|exists:companies,id',
            'date' => 'nullable|date_format:Y-m-d',
            'vehicle_id' => 'nullable|integer|exists:vehicles,id',
            'cash_session_id' => 'nullable|integer|exists:cash_sessions,id',
        ]);

        $companyId = $request->integer('company_id');
        $date = $request->input('date', now()->toDateString());
        $vehicleId = $request->filled('vehicle_id') ? $request->integer('vehicle_id') : null;

        $appointmentsQuery = Appointment::query()
            ->where('company_id', $companyId)
            ->where('date', $date)
            ->whereNotIn('status', ['Cancelada']);

        if ($vehicleId) {
            $appointmentsQuery->where('vehicle_id', $vehicleId);
        }

        $appointments = $appointmentsQuery
            ->with('client:id,razon_social,nombre_comercial')
            ->get([
                'id', 'client_id', 'vehicle_id', 'service_name', 'status', 'payment_status',
                'payment_method', 'total', 'time', 'district', 'boleta_id', 'invoice_id',
            ]);

        $methodTotals = [
            'efectivo' => 0.0,
            'tarjeta' => 0.0,
            'transferencia' => 0.0,
            'yape_plin' => 0.0,
            'otro' => 0.0,
        ];

        $pending = [];
        $pendingInvoicing = [];
        foreach ($appointments as $apt) {
            $clientName = $apt->client?->razon_social
                ?? $apt->client?->nombre_comercial
                ?? null;
            $invoiced = !empty($apt->boleta_id) || !empty($apt->invoice_id);
            $aptRow = [
                'id' => $apt->id,
                'service_name' => $apt->service_name,
                'client_name' => $clientName,
                'total' => (float) $apt->total,
                'time' => $apt->time,
                'district' => $apt->district,
                'vehicle_id' => $apt->vehicle_id,
                'status' => $apt->status,
                'payment_status' => $apt->payment_status,
                'invoiced' => $invoiced,
                'boleta_id' => $apt->boleta_id,
                'invoice_id' => $apt->invoice_id,
            ];

            if ($apt->payment_status === 'Pagado') {
                $bucket = $this->paymentMethodBucket($apt->payment_method);
                $methodTotals[$bucket] += (float) $apt->total;
                if ($apt->status === 'Completada' && !$invoiced) {
                    $pendingInvoicing[] = $aptRow;
                }
            } elseif (in_array($apt->status, ['Completada', 'En Proceso'], true)) {
                $pending[] = $aptRow;
                if ($apt->status === 'Completada' && !$invoiced) {
                    $pendingInvoicing[] = $aptRow;
                }
            }
        }

        $pendingInvoicing = collect($pendingInvoicing)
            ->unique('id')
            ->values()
            ->all();

        $movementsQuery = CashMovement::query()
            ->where('company_id', $companyId)
            ->whereDate('movement_date', $date);

        if ($request->filled('cash_session_id')) {
            $movementsQuery->where('cash_session_id', $request->integer('cash_session_id'));
        }
        if ($vehicleId) {
            $movementsQuery->where('vehicle_id', $vehicleId);
        }

        $movements = $movementsQuery->orderByDesc('movement_date')->get();
        $expensesTotal = (float) $movements->where('type', 'EXPENSE')->sum('amount');

        $byVehicle = $appointments->groupBy('vehicle_id')->map(function ($group, $vid) {
            $paid = $group->where('payment_status', 'Pagado')->sum('total');
            $pendingCount = $group->where('payment_status', '!=', 'Pagado')
                ->whereIn('status', ['Completada', 'En Proceso'])
                ->count();

            return [
                'vehicle_id' => $vid ? (int) $vid : null,
                'appointments' => $group->count(),
                'paid_total' => (float) $paid,
                'pending_count' => $pendingCount,
            ];
        })->values();

        $vehicles = Vehicle::where('company_id', $companyId)
            ->get(['id', 'name', 'placa'])
            ->keyBy('id');

        $byVehicle = $byVehicle->map(function ($row) use ($vehicles) {
            $v = $vehicles->get($row['vehicle_id']);
            $row['vehicle_name'] = $v?->name ?? ($row['vehicle_id'] ? "Móvil #{$row['vehicle_id']}" : 'Sin móvil');
            $row['plate'] = $v?->placa;

            return $row;
        });

        return response()->json([
            'success' => true,
            'data' => [
                'date' => $date,
                'sales' => [
                    'cash' => $methodTotals['efectivo'],
                    'card' => $methodTotals['tarjeta'],
                    'transfer' => $methodTotals['transferencia'],
                    'qr' => $methodTotals['yape_plin'],
                    'other' => $methodTotals['otro'],
                    'total' => array_sum($methodTotals),
                ],
                'pending_collections' => $pending,
                'pending_invoicing' => $pendingInvoicing,
                'expenses_total' => $expensesTotal,
                'movements' => $movements,
                'by_vehicle' => $byVehicle,
            ],
        ]);
    }

    public function open(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'vehicle_id' => ['nullable', 'integer', 'exists:vehicles,id'],
            'opening_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $companyId = (int) $validated['company_id'];
        $branchId = (int) $validated['branch_id'];
        if (!ScopeHelper::branchBelongsToCompany($branchId, $companyId)) {
            return response()->json(['success' => false, 'message' => 'La sucursal no pertenece a la empresa indicada.'], 422);
        }

        $openExists = CashSession::where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->where('status', 'OPEN')
            ->when(!empty($validated['vehicle_id']), fn ($q) => $q->where('vehicle_id', $validated['vehicle_id']))
            ->when(empty($validated['vehicle_id']), fn ($q) => $q->whereNull('vehicle_id'))
            ->exists();

        if ($openExists) {
            return response()->json([
                'success' => false,
                'message' => 'Ya existe una sesión de caja abierta para este turno.',
            ], 409);
        }

        $session = CashSession::create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'vehicle_id' => $validated['vehicle_id'] ?? null,
            'user_id' => Auth::id(),
            'opening_amount' => $validated['opening_amount'] ?? 0,
            'opened_at' => now(),
            'status' => 'OPEN',
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'data' => $session->load(['company', 'branch', 'user', 'vehicle']),
        ], 201);
    }

    public function close(Request $request, CashSession $cashSession): JsonResponse
    {
        $validated = $request->validate([
            'closing_amount' => ['required', 'numeric'],
            'expected_cash' => ['nullable', 'numeric'],
            'notes' => ['nullable', 'string'],
        ]);

        $expected = $validated['expected_cash'] ?? $cashSession->opening_amount;

        $cashSession->update([
            'closing_amount' => $validated['closing_amount'],
            'expected_cash' => $expected,
            'difference' => $validated['closing_amount'] - $expected,
            'closed_at' => now(),
            'status' => 'CLOSED',
            'notes' => $validated['notes'] ?? $cashSession->notes,
        ]);

        return response()->json([
            'success' => true,
            'data' => $cashSession->fresh()->load(['company', 'branch', 'user', 'vehicle']),
        ]);
    }

    private function paymentMethodBucket(?string $method): string
    {
        $m = strtolower($method ?? 'efectivo');

        return match (true) {
            str_contains($m, 'tarjeta'), str_contains($m, 'card') => 'tarjeta',
            str_contains($m, 'transfer') => 'transferencia',
            str_contains($m, 'yape'), str_contains($m, 'plin') => 'yape_plin',
            str_contains($m, 'efectivo'), str_contains($m, 'cash') => 'efectivo',
            default => 'otro',
        };
    }
}
