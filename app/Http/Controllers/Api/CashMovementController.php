<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CashMovementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = CashMovement::with(['company', 'branch', 'user', 'cashSession'])
            ->when($request->filled('company_id'), fn($q) => $q->where('company_id', $request->integer('company_id')))
            ->when($request->filled('branch_id'), fn($q) => $q->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('type'), fn($q) => $q->where('type', $request->get('type')))
            ->when($request->filled('date_from') && $request->filled('date_to'), function ($q) use ($request) {
                $q->whereBetween('movement_date', [$request->get('date_from'), $request->get('date_to')]);
            })
            ->orderByDesc('movement_date');

        $perPage = min(max((int) $request->get('per_page', 20), 1), 200);

        return response()->json([
            'success' => true,
            'data' => $query->paginate($perPage),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'cash_session_id' => ['nullable', 'integer', 'exists:cash_sessions,id'],
            'type' => ['required', 'string', 'in:INCOME,EXPENSE'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['required', 'string'],
            'payment_method' => ['nullable', 'string'],
            'reference' => ['nullable', 'string'],
            'movement_date' => ['nullable', 'date'],
            'metadata' => ['nullable', 'array'],
        ]);

        $movement = CashMovement::create([
            'company_id' => $validated['company_id'],
            'branch_id' => $validated['branch_id'] ?? null,
            'user_id' => Auth::id(),
            'cash_session_id' => $validated['cash_session_id'] ?? null,
            'type' => $validated['type'],
            'amount' => $validated['amount'],
            'description' => $validated['description'],
            'payment_method' => $validated['payment_method'] ?? 'Efectivo',
            'reference' => $validated['reference'] ?? null,
            'movement_date' => $validated['movement_date'] ?? now(),
            'metadata' => $validated['metadata'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Movimiento de caja registrado',
            'data' => $movement->load(['company', 'branch', 'user']),
        ], 201);
    }

    public function show(CashMovement $cashMovement): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $cashMovement->load(['company', 'branch', 'user', 'cashSession']),
        ]);
    }

    public function destroy(CashMovement $cashMovement): JsonResponse
    {
        $cashMovement->delete();

        return response()->json([
            'success' => true,
            'message' => 'Movimiento de caja eliminado',
        ]);
    }
}
