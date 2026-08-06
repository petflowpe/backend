<?php

namespace App\Services;

use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;

/**
 * Conciliación Payment → payment_status de cita + CashMovement (si hay caja abierta).
 */
class PaymentLedgerSyncService
{
    public function __construct(
        private AppointmentPaymentStatusService $paymentStatusService,
    ) {
    }

    /**
     * Tras marcar un pago completed (manual o webhook).
     */
    public function onPaymentCompleted(Payment $payment, array $options = []): Payment
    {
        $payment->refresh();

        if ($payment->status !== 'completed') {
            return $payment;
        }

        $this->syncAppointmentStatus($payment);
        $this->ensureIncomeCashMovement($payment, $options);

        return $payment->fresh(['appointment', 'cashSession']);
    }

    public function syncAppointmentStatus(Payment $payment): void
    {
        if (! $payment->appointment_id) {
            return;
        }

        $appointment = $payment->appointment ?? $payment->appointment()->first();
        if (! $appointment) {
            return;
        }

        // Usar agregación real de payments (soporta parcial).
        $this->paymentStatusService->sync($appointment);

        $methodLabel = match ($payment->method) {
            'cash' => 'Efectivo',
            'card' => 'Tarjeta',
            'yape' => 'Yape',
            'plin' => 'Plin',
            'transfer' => 'Transferencia',
            default => 'Tarjeta',
        };

        $appointment->update([
            'payment_method' => $methodLabel,
        ]);
    }

    /**
     * Crea INCOME en caja abierta si aún no hay movimiento ligado a este payment.
     * No fuerza caja: si no hay OPEN, solo deja el Payment (pasarela online).
     */
    public function ensureIncomeCashMovement(Payment $payment, array $options = []): ?CashMovement
    {
        if ($payment->status !== 'completed') {
            return null;
        }

        $meta = is_array($payment->metadata) ? $payment->metadata : [];
        if (! empty($meta['cash_movement_id'])) {
            return CashMovement::find($meta['cash_movement_id']);
        }

        // Evitar duplicar si ya existe movimiento con reference del payment.
        $existing = CashMovement::query()
            ->where('company_id', $payment->company_id)
            ->where('type', 'INCOME')
            ->where(function ($q) use ($payment) {
                $q->where('reference', 'payment:' . $payment->id);
                if ($payment->appointment_id) {
                    $q->orWhere(function ($q2) use ($payment) {
                        $q2->where('appointment_id', $payment->appointment_id)
                            ->where('amount', $payment->amount)
                            ->whereDate('movement_date', optional($payment->paid_at)->toDateString() ?? now()->toDateString());
                    });
                }
            })
            ->first();

        if ($existing) {
            $payment->update([
                'metadata' => array_merge($meta, ['cash_movement_id' => $existing->id]),
                'cash_session_id' => $payment->cash_session_id ?: $existing->cash_session_id,
            ]);

            return $existing;
        }

        $force = (bool) ($options['require_open_session'] ?? false);
        $session = null;

        if ($payment->cash_session_id) {
            $session = CashSession::where('id', $payment->cash_session_id)
                ->where('status', 'OPEN')
                ->first();
        }

        if (! $session) {
            $session = CashSession::query()
                ->where('company_id', $payment->company_id)
                ->where('status', 'OPEN')
                ->when($payment->branch_id, fn ($q) => $q->where('branch_id', $payment->branch_id))
                ->orderByDesc('id')
                ->first();
        }

        if (! $session) {
            if ($force) {
                throw new \InvalidArgumentException('No hay caja abierta para registrar el ingreso');
            }
            Log::info('Payment completed sin caja abierta; sin CashMovement', [
                'payment_id' => $payment->id,
                'gateway' => $payment->gateway,
            ]);

            return null;
        }

        $methodLabel = match ($payment->method) {
            'cash' => 'Efectivo',
            'card' => 'Tarjeta',
            'yape' => 'Yape',
            'plin' => 'Plin',
            'transfer' => 'Transferencia',
            default => 'Tarjeta',
        };

        $gw = $payment->gateway && $payment->gateway !== 'manual'
            ? strtoupper(str_replace('_', ' ', $payment->gateway))
            : null;

        $movement = CashMovement::create([
            'company_id' => $payment->company_id,
            'branch_id' => $payment->branch_id ?? $session->branch_id,
            'vehicle_id' => $payment->appointment?->vehicle_id,
            'appointment_id' => $payment->appointment_id,
            'user_id' => $payment->user_id,
            'cash_session_id' => $session->id,
            'type' => 'INCOME',
            'amount' => (float) $payment->amount,
            'description' => trim(
                'Pago #' . $payment->id
                . ($payment->appointment_id ? ' cita #' . $payment->appointment_id : '')
                . ($gw ? " ({$gw})" : '')
            ),
            'payment_method' => $methodLabel,
            'reference' => 'payment:' . $payment->id,
            'movement_date' => $payment->paid_at ?? now(),
            'metadata' => [
                'payment_id' => $payment->id,
                'gateway' => $payment->gateway,
                'source' => 'payment_ledger_sync',
            ],
        ]);

        $payment->update([
            'cash_session_id' => $session->id,
            'metadata' => array_merge($meta, [
                'cash_movement_id' => $movement->id,
                'synced_to_cash_at' => now()->toIso8601String(),
            ]),
        ]);

        return $movement;
    }
}
