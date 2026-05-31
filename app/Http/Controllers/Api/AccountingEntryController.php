<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccountingEntry;
use App\Models\AccountingEntryLine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class AccountingEntryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $companyId = $request->integer('company_id', 1);
            $query = AccountingEntry::with('lines')
                ->where('company_id', $companyId)
                ->orderByDesc('date')
                ->orderByDesc('id');

            if ($request->filled('from')) {
                $query->whereDate('date', '>=', $request->from);
            }
            if ($request->filled('to')) {
                $query->whereDate('date', '<=', $request->to);
            }
            if ($request->filled('type')) {
                $query->where('type', $request->type);
            }

            $perPage = $request->integer('per_page', 20);
            $entries = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $entries->items(),
                'meta' => [
                    'total' => $entries->total(),
                    'per_page' => $entries->perPage(),
                    'current_page' => $entries->currentPage(),
                    'last_page' => $entries->lastPage(),
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Error listar asientos', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_id' => 'nullable|integer|exists:companies,id',
            'number' => 'nullable|string|max:50',
            'date' => 'required|date',
            'time' => 'nullable|string',
            'type' => 'nullable|string|max:20',
            'origin' => 'nullable|string|max:50',
            'reference_id' => 'nullable|integer',
            'reference_type' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'lines' => 'required|array|min:1',
            'lines.*.account_code' => 'required|string|max:20',
            'lines.*.account_name' => 'nullable|string|max:255',
            'lines.*.debit' => 'required|numeric|min:0',
            'lines.*.credit' => 'required|numeric|min:0',
        ]);
        $companyId = (int) ($validated['company_id'] ?? \App\Helpers\ScopeHelper::companyId($request) ?? $request->user()?->company_id);
        if (!$companyId) {
            return response()->json(['message' => 'company_id es requerido o el usuario debe tener empresa asignada.'], 422);
        }
        $totalDebit = 0;
        $totalCredit = 0;
        foreach ($validated['lines'] as $line) {
            $totalDebit += (float) $line['debit'];
            $totalCredit += (float) $line['credit'];
        }
        if (abs($totalDebit - $totalCredit) > 0.01) {
            return response()->json([
                'success' => false,
                'message' => 'El asiento debe cuadrar (débitos = créditos)',
            ], 422);
        }
        DB::beginTransaction();
        try {
            $entry = AccountingEntry::create([
                'company_id' => $companyId,
                'number' => $validated['number'] ?? 'AST-' . now()->format('Ymd-His'),
                'date' => $validated['date'],
                'time' => $validated['time'] ?? now()->format('H:i:s'),
                'type' => $validated['type'] ?? 'manual',
                'origin' => $validated['origin'] ?? null,
                'reference_id' => $validated['reference_id'] ?? null,
                'reference_type' => $validated['reference_type'] ?? null,
                'description' => $validated['description'] ?? null,
                'total_debit' => round($totalDebit, 2),
                'total_credit' => round($totalCredit, 2),
                'created_by' => $request->user()?->id,
            ]);
            foreach ($validated['lines'] as $line) {
                AccountingEntryLine::create([
                    'accounting_entry_id' => $entry->id,
                    'account_code' => $line['account_code'],
                    'account_name' => $line['account_name'] ?? null,
                    'debit' => (float) $line['debit'],
                    'credit' => (float) $line['credit'],
                ]);
            }
            DB::commit();
            $entry->load('lines');
            return response()->json(['success' => true, 'data' => $entry], 201);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error crear asiento', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function show(AccountingEntry $accounting_entry): JsonResponse
    {
        $accounting_entry->load('lines');
        return response()->json(['success' => true, 'data' => $accounting_entry]);
    }
}
