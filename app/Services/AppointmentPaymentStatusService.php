<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Payment;

class AppointmentPaymentStatusService
{
    public const EPSILON = 0.009;

    /**
     * Monto cobrado (payments completed + adelanto portal si no hay payments).
     */
    public function paidAmount(Appointment $appointment): float
    {
        $fromPayments = (float) Payment::query()
            ->where('appointment_id', $appointment->id)
            ->where('status', 'completed')
            ->sum('amount');

        if ($fromPayments > self::EPSILON) {
            return round($fromPayments, 2);
        }

        if ($appointment->advance_paid_at && (float) ($appointment->advance_amount ?? 0) > 0) {
            return round((float) $appointment->advance_amount, 2);
        }

        return 0.0;
    }

    public function remainingAmount(Appointment $appointment): float
    {
        $total = (float) ($appointment->total ?? 0);
        $remaining = $total - $this->paidAmount($appointment);

        return round(max(0, $remaining), 2);
    }

    /**
     * Pendiente (0) | Parcial (>0 y < total) | Pagado (>= total) | Reembolsado (se conserva).
     */
    public function resolveStatus(Appointment $appointment, ?float $paidOverride = null): string
    {
        if ($appointment->payment_status === 'Reembolsado') {
            return 'Reembolsado';
        }

        $paid = $paidOverride ?? $this->paidAmount($appointment);
        $total = (float) ($appointment->total ?? 0);

        if ($total <= self::EPSILON) {
            return $paid > self::EPSILON ? 'Pagado' : 'Pendiente';
        }

        if ($paid <= self::EPSILON) {
            return 'Pendiente';
        }

        if ($paid + self::EPSILON < $total) {
            return 'Parcial';
        }

        return 'Pagado';
    }

    public function sync(Appointment $appointment, ?string $paymentMethod = null): Appointment
    {
        $data = [
            'payment_status' => $this->resolveStatus($appointment),
        ];

        if ($paymentMethod !== null && $paymentMethod !== '') {
            $data['payment_method'] = $paymentMethod;
        }

        $appointment->update($data);

        return $appointment->fresh();
    }

    public function mapCashMethodToPayment(string $method): string
    {
        return match ($method) {
            'Efectivo' => 'cash',
            'Tarjeta' => 'card',
            'Yape' => 'yape',
            'Plin' => 'plin',
            'Transferencia' => 'transfer',
            default => 'other',
        };
    }
}
