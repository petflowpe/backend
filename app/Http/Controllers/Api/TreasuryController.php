<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ScopeHelper;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TreasuryController extends Controller
{
    /**
     * Cuentas por cobrar (Facturas) con saldo calculado desde Payments.
     */
    public function receivables(Request $request): JsonResponse
    {
        $companyId = ScopeHelper::companyId($request) ?: ($request->filled('company_id') ? (int) $request->integer('company_id') : null);
        if (! $companyId) {
            return response()->json(['success' => false, 'message' => 'company_id requerido'], 422);
        }

        $query = Invoice::query()
            ->with(['client:id,razon_social,nombre_comercial,numero_documento'])
            ->where('company_id', $companyId)
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('estado_sunat'), fn ($q) => $q->where('estado_sunat', $request->get('estado_sunat')))
            ->when($request->filled('date_from') && $request->filled('date_to'), function ($q) use ($request) {
                $q->whereBetween('fecha_emision', [$request->get('date_from'), $request->get('date_to')]);
            })
            ->withSum([
                'payments as paid_amount' => fn ($q) => $q->where('status', 'completed'),
            ], 'amount')
            ->orderByDesc('fecha_emision')
            ->orderByDesc('id');

        $perPage = min(max((int) $request->get('per_page', 50), 1), 200);
        $paginated = $query->paginate($perPage);

        $today = now()->startOfDay();

        $paginated->getCollection()->transform(function (Invoice $inv) use ($today) {
            $total = (float) ($inv->mto_imp_venta ?? 0);
            $paid = (float) ($inv->paid_amount ?? 0);
            $balance = max(0, $total - $paid);

            $forma = (string) ($inv->forma_pago_tipo ?? 'Contado');
            $due = $inv->fecha_vencimiento;
            if (! $due && $forma === 'Credito') {
                $cuotas = is_array($inv->forma_pago_cuotas) ? $inv->forma_pago_cuotas : [];
                $max = null;
                foreach ($cuotas as $c) {
                    $f = $c['fecha_pago'] ?? null;
                    if (! $f) continue;
                    try {
                        $dt = \Illuminate\Support\Carbon::parse($f)->startOfDay();
                        if (! $max || $dt->gt($max)) $max = $dt;
                    } catch (\Throwable) {
                        // ignore
                    }
                }
                $due = $max;
            }

            $status = $balance <= 0.01 ? 'paid' : ($paid > 0 ? 'partial' : 'open');
            $isOverdue = $balance > 0.01 && $due && $due->startOfDay()->lt($today);

            $clientName = $inv->client?->razon_social
                ?: ($inv->client?->nombre_comercial ?: '—');

            return [
                'id' => $inv->id,
                'numero_completo' => $inv->numero_completo,
                'fecha_emision' => optional($inv->fecha_emision)->toDateString(),
                'fecha_vencimiento' => $due ? $due->toDateString() : null,
                'forma_pago_tipo' => $forma,
                'estado_sunat' => $inv->estado_sunat,
                'client' => [
                    'id' => $inv->client_id,
                    'name' => $clientName,
                    'document' => $inv->client?->numero_documento,
                ],
                'total' => $total,
                'paid' => $paid,
                'balance' => $balance,
                'status' => $status,
                'overdue' => (bool) $isOverdue,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $paginated->getCollection()->values()->all(),
            'meta' => [
                'total' => $paginated->total(),
                'per_page' => $paginated->perPage(),
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
            ],
        ]);
    }
}

