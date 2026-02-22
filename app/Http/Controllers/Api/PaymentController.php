<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Payment::with(['company', 'branch', 'invoice', 'user', 'cashSession'])
            ->when($request->filled('company_id'), fn ($q) => $q->where('company_id', $request->integer('company_id')))
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('method'), fn ($q) => $q->where('method', $request->get('method')))
            ->when($request->filled('date_from') && $request->filled('date_to'), function ($q) use ($request) {
                $q->whereBetween('paid_at', [$request->get('date_from'), $request->get('date_to')]);
            })
            ->orderByDesc('paid_at');

        $perPage = min(max((int) $request->get('per_page', 20), 1), 200);

        return response()->json([
            'success' => true,
            'data' => $query->paginate($perPage),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'invoice_id' => ['required', 'integer', 'exists:invoices,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'string', 'in:cash,card,transfer,yape,plin,other'],
            'reference' => ['nullable', 'string', 'max:100'],
            'cash_session_id' => ['nullable', 'integer', 'exists:cash_sessions,id'],
            'notes' => ['nullable', 'string'],
        ]);

        $invoice = Invoice::findOrFail($validated['invoice_id']);

        $payment = Payment::create([
            'company_id' => $invoice->company_id,
            'branch_id' => $invoice->branch_id,
            'invoice_id' => $invoice->id,
            'user_id' => Auth::id(),
            'cash_session_id' => $validated['cash_session_id'] ?? null,
            'amount' => $validated['amount'],
            'currency' => $invoice->moneda ?? 'PEN',
            'method' => $validated['method'],
            'reference' => $validated['reference'] ?? null,
            'paid_at' => now(),
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'data' => $payment->load(['invoice', 'company', 'branch']),
        ], 201);
    }
}


