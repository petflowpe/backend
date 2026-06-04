<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Payment\MercadoPagoService;
use App\Services\Payment\NiubizService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $companyId = $request->attributes->get('scope_company_id')
            ?? $request->user()?->company_id;

        $query = Payment::with([
            'invoice.client',
            'appointment.client',
            'appointment.pet',
            'user',
            'branch',
        ])
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('method'), fn ($q) => $q->where('method', $request->get('method')))
            ->when($request->filled('gateway'), fn ($q) => $q->where('gateway', $request->get('gateway')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->get('status')))
            ->when($request->filled('date_from') && $request->filled('date_to'), function ($q) use ($request) {
                $q->whereBetween('paid_at', [$request->get('date_from'), $request->get('date_to') . ' 23:59:59']);
            })
            ->orderByDesc('created_at');

        $perPage = min(max((int) $request->get('per_page', 20), 1), 200);
        $paginated = $query->paginate($perPage);

        $paginated->getCollection()->transform(fn (Payment $p) => $this->formatPayment($p));

        return response()->json([
            'success' => true,
            'data' => $paginated,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'invoice_id' => ['required_without:appointment_id', 'nullable', 'integer', 'exists:invoices,id'],
            'appointment_id' => ['nullable', 'integer', 'exists:appointments,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'string', 'in:cash,card,transfer,yape,plin,other'],
            'reference' => ['nullable', 'string', 'max:120'],
            'cash_session_id' => ['nullable', 'integer', 'exists:cash_sessions,id'],
            'notes' => ['nullable', 'string'],
            'fee' => ['nullable', 'numeric', 'min:0'],
        ]);

        $companyId = (int) ($request->attributes->get('scope_company_id') ?? $request->user()->company_id);
        $branchId = null;
        $invoiceId = $validated['invoice_id'] ?? null;
        $appointmentId = $validated['appointment_id'] ?? null;

        if ($invoiceId) {
            $invoice = Invoice::findOrFail($invoiceId);
            if ((int) $invoice->company_id !== $companyId) {
                return response()->json(['success' => false, 'message' => 'Factura no pertenece a la empresa'], 403);
            }
            $branchId = $invoice->branch_id;
        }

        if ($appointmentId) {
            $appointment = Appointment::findOrFail($appointmentId);
            if ((int) $appointment->company_id !== $companyId) {
                return response()->json(['success' => false, 'message' => 'Cita no pertenece a la empresa'], 403);
            }
            $branchId = $branchId ?? $appointment->branch_id;
        }

        $amount = (float) $validated['amount'];
        $fee = (float) ($validated['fee'] ?? 0);

        $payment = Payment::create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'invoice_id' => $invoiceId,
            'appointment_id' => $appointmentId,
            'user_id' => Auth::id(),
            'cash_session_id' => $validated['cash_session_id'] ?? null,
            'amount' => $amount,
            'fee' => $fee,
            'net_amount' => max(0, $amount - $fee),
            'currency' => 'PEN',
            'method' => $validated['method'],
            'gateway' => 'manual',
            'status' => 'completed',
            'reference' => $validated['reference'] ?? null,
            'paid_at' => now(),
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->formatPayment($payment->load(['invoice.client', 'appointment.client'])),
        ], 201);
    }

    public function createCheckout(
        Request $request,
        MercadoPagoService $mercadoPago,
        NiubizService $niubiz
    ): JsonResponse {
        $validated = $request->validate([
            'gateway' => ['required', 'string', 'in:mercado_pago,niubiz'],
            'appointment_id' => ['nullable', 'integer', 'exists:appointments,id'],
            'invoice_id' => ['nullable', 'integer', 'exists:invoices,id'],
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:200'],
            'payer_email' => ['nullable', 'email'],
        ]);

        $companyId = (int) ($request->attributes->get('scope_company_id') ?? $request->user()->company_id);
        $gateway = $validated['gateway'];
        $title = $validated['description'] ?? 'Pago SmartPet';
        $amount = null;
        $branchId = null;
        $invoiceId = $validated['invoice_id'] ?? null;
        $appointmentId = $validated['appointment_id'] ?? null;
        $payerEmail = $validated['payer_email'] ?? null;

        if ($appointmentId) {
            $appointment = Appointment::with('client')->findOrFail($appointmentId);
            if ((int) $appointment->company_id !== $companyId) {
                return response()->json(['success' => false, 'message' => 'Sin permisos'], 403);
            }
            $amount = $validated['amount'] ?? (float) $appointment->total;
            $branchId = $appointment->branch_id;
            $title = $appointment->service_name ?? $title;
            $payerEmail = $payerEmail ?? $appointment->client?->email;
        } elseif ($invoiceId) {
            $invoice = Invoice::with('client')->findOrFail($invoiceId);
            if ((int) $invoice->company_id !== $companyId) {
                return response()->json(['success' => false, 'message' => 'Sin permisos'], 403);
            }
            $amount = $validated['amount'] ?? (float) ($invoice->mto_imp_venta ?? 0);
            $branchId = $invoice->branch_id;
            $payerEmail = $payerEmail ?? $invoice->client?->email;
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Indique appointment_id o invoice_id',
            ], 422);
        }

        if ($amount <= 0) {
            return response()->json(['success' => false, 'message' => 'Monto inválido'], 422);
        }

        if ($gateway === 'mercado_pago' && !$mercadoPago->isConfigured($companyId)) {
            return response()->json(['success' => false, 'message' => 'Mercado Pago no está habilitado'], 422);
        }
        if ($gateway === 'niubiz' && !$niubiz->isConfigured($companyId)) {
            return response()->json(['success' => false, 'message' => 'Niubiz no está habilitado'], 422);
        }

        $payment = Payment::create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'invoice_id' => $invoiceId,
            'appointment_id' => $appointmentId,
            'user_id' => Auth::id(),
            'amount' => $amount,
            'fee' => 0,
            'currency' => 'PEN',
            'method' => 'card',
            'gateway' => $gateway,
            'status' => 'pending',
            'paid_at' => null,
            'notes' => $title,
        ]);

        try {
            $checkout = $gateway === 'mercado_pago'
                ? $mercadoPago->createPreference($payment, $title, $payerEmail)
                : $niubiz->createSession($payment, $payerEmail ?? 'cliente@smartpet.pe');

            return response()->json([
                'success' => true,
                'data' => [
                    'payment' => $this->formatPayment($payment->fresh()),
                    'checkout' => $checkout,
                ],
            ]);
        } catch (\Throwable $e) {
            $payment->update(['status' => 'failed', 'notes' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 502);
        }
    }

    protected function formatPayment(Payment $p): array
    {
        $clientName = $p->invoice?->client?->razon_social
            ?? $p->invoice?->client?->nombre_comercial
            ?? $p->appointment?->client?->razon_social
            ?? $p->appointment?->client?->nombre_comercial
            ?? '—';

        $paidAt = $p->paid_at ?? $p->created_at;

        return [
            'id' => $p->id,
            'invoice_id' => $p->invoice_id,
            'appointment_id' => $p->appointment_id,
            'client' => $clientName,
            'amount' => (float) $p->amount,
            'fee' => (float) ($p->fee ?? 0),
            'net' => (float) ($p->net_amount ?? max(0, (float) $p->amount - (float) ($p->fee ?? 0))),
            'currency' => $p->currency,
            'method' => $p->method,
            'gateway' => $p->gateway,
            'status' => $p->status,
            'reference' => $p->reference,
            'external_id' => $p->external_id,
            'date' => $paidAt?->format('Y-m-d'),
            'time' => $paidAt?->format('H:i'),
            'description' => $p->notes,
            'invoice_number' => $p->invoice?->numero_completo,
        ];
    }
}
