<?php

namespace App\Observers;

use App\Models\Appointment;
use App\Models\AppointmentItem;
use App\Services\AppointmentStockService;
use Illuminate\Support\Facades\Log;

/**
 * Mantiene las reservas de stock de una cita al crearla, cambiar sus ítems, cancelarla o facturarla.
 * Un fallo de reserva nunca debe impedir guardar la cita.
 */
class AppointmentStockObserver
{
    private const WATCHED = ['status', 'service_id', 'boleta_id', 'invoice_id', 'company_id'];

    public function saved(Appointment|AppointmentItem $model): void
    {
        if ($model instanceof Appointment) {
            if (! $model->wasRecentlyCreated && ! $model->wasChanged(self::WATCHED)) {
                return;
            }
            $this->sync($model);

            return;
        }

        $this->sync($model->appointment);
    }

    public function deleted(Appointment|AppointmentItem $model): void
    {
        if ($model instanceof Appointment) {
            $this->release($model);

            return;
        }

        $this->sync($model->appointment);
    }

    private function sync(?Appointment $appointment): void
    {
        if (! $appointment) {
            return;
        }

        try {
            $appointment->unsetRelation('items');
            app(AppointmentStockService::class)->syncReservation($appointment);
        } catch (\Throwable $e) {
            Log::warning('No se pudo actualizar la reserva de stock de la cita', [
                'appointment_id' => $appointment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function release(Appointment $appointment): void
    {
        try {
            app(\App\Services\StockReservationService::class)
                ->release(AppointmentStockService::RESERVATION_SOURCE, $appointment->id);
        } catch (\Throwable $e) {
            Log::warning('No se pudo liberar la reserva de stock de la cita', [
                'appointment_id' => $appointment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
