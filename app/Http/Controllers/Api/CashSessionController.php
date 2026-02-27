<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CashSessionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = CashSession::with(['company', 'branch', 'user'])
            ->when($request->filled('company_id'), fn ($q) => $q->where('company_id', $request->integer('company_id')))
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->get('status')))
            ->orderByDesc('opened_at');

        $perPage = min(max((int) $request->get('per_page', 20), 1), 200);

        return response()->json([
            'success' => true,
            'data' => $query->paginate($perPage),
        ]);
    }

    public function open(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'opening_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $companyId = (int) $validated['company_id'];
        $branchId = (int) $validated['branch_id'];
        if (!\App\Helpers\ScopeHelper::branchBelongsToCompany($branchId, $companyId)) {
            return response()->json(['success' => false, 'message' => 'La sucursal no pertenece a la empresa indicada.'], 422);
        }

        $userId = Auth::id();

        $session = CashSession::create([
            'company_id' => $validated['company_id'],
            'branch_id' => $validated['branch_id'],
            'user_id' => $userId,
            'opening_amount' => $validated['opening_amount'] ?? 0,
            'opened_at' => now(),
            'status' => 'OPEN',
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'data' => $session->load(['company', 'branch', 'user']),
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
            'data' => $cashSession->fresh()->load(['company', 'branch', 'user']),
        ]);
    }
}


