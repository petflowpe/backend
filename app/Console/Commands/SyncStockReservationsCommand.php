<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Services\AppointmentStockService;
use Illuminate\Console\Command;

class SyncStockReservationsCommand extends Command
{
    protected $signature = 'inventory:sync-reservations
                            {--company= : Solo una empresa}
                            {--days=30 : Solo citas desde hace N días (las antiguas sin facturar no reservan)}
                            {--dry-run : Solo contar las citas que se reservarían}';

    protected $description = 'Genera las reservas de stock de las citas abiertas (sin comprobante ni cancelar)';

    public function handle(AppointmentStockService $stockService): int
    {
        $query = Appointment::withoutGlobalScopes()
            ->where('status', '!=', 'Cancelada')
            ->whereNull('boleta_id')
            ->whereNull('invoice_id')
            ->whereDate('date', '>=', now()->subDays(max(0, (int) $this->option('days')))->toDateString())
            ->orderBy('id');
        if ($this->option('company')) {
            $query->where('company_id', (int) $this->option('company'));
        }

        if ($this->option('dry-run')) {
            $this->info($query->count() . ' cita(s) abiertas a reservar.');

            return self::SUCCESS;
        }

        $done = 0;
        $failed = 0;
        foreach ($query->cursor() as $appointment) {
            try {
                $stockService->syncReservation($appointment);
                $done++;
            } catch (\Throwable $e) {
                $failed++;
                $this->warn("Cita #{$appointment->id}: {$e->getMessage()}");
            }
        }

        $this->info("Reservas sincronizadas: {$done} cita(s). Errores: {$failed}.");

        return self::SUCCESS;
    }
}
