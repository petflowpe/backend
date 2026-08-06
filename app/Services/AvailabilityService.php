<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\CompanyConfiguration;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AvailabilityService
{
    public const REASON_FUERA_HORARIO = 'fuera_horario';
    public const REASON_CERRADO = 'cerrado';
    public const REASON_OCUPADO = 'ocupado';
    public const REASON_SIN_COBERTURA = 'sin_cobertura';
    public const REASON_PASADO = 'pasado';

    public function __construct(
        private readonly VehicleCoverageService $coverageService
    ) {
    }

    /**
     * @return array{open: bool, start: string, end: string, day: string}
     */
    public function getCompanyDayWindow(int $companyId, Carbon $date): array
    {
        $dayOfWeek = strtolower($date->format('l'));
        $start = '08:00';
        $end = '18:00';
        $isOpen = true;

        $config = CompanyConfiguration::query()
            ->where('company_id', $companyId)
            ->where('config_type', 'document_settings')
            ->first();

        if ($config && isset($config->config_data['working_hours'][$dayOfWeek])) {
            $hours = $config->config_data['working_hours'][$dayOfWeek];
            $isOpen = (bool) ($hours['open'] ?? true);
            $start = substr((string) ($hours['start'] ?? $start), 0, 5);
            $end = substr((string) ($hours['end'] ?? $end), 0, 5);
        }

        return [
            'open' => $isOpen,
            'start' => $start,
            'end' => $end,
            'day' => $dayOfWeek,
        ];
    }

    /**
     * @return Collection<int, Appointment>
     */
    public function getBusyAppointments(
        int $companyId,
        Carbon $date,
        ?int $vehicleId = null,
        ?int $excludeAppointmentId = null
    ): Collection {
        $query = Appointment::query()
            ->where('company_id', $companyId)
            ->whereDate('date', $date->toDateString())
            ->whereNotIn('status', ['Cancelada']);

        if ($vehicleId) {
            $query->where('vehicle_id', $vehicleId);
        }

        if ($excludeAppointmentId) {
            $query->where('id', '!=', $excludeAppointmentId);
        }

        return $query->get(['id', 'time', 'duration', 'vehicle_id']);
    }

    public function slotOverlapsBusy(string $slotTime, int $duration, Collection $busyAppointments): bool
    {
        $slotStart = Carbon::createFromFormat('H:i', $this->normalizeTime($slotTime));
        $slotEnd = $slotStart->copy()->addMinutes($duration);

        foreach ($busyAppointments as $apt) {
            $aptStart = Carbon::createFromFormat('H:i', $this->normalizeTime((string) $apt->time));
            $aptEnd = $aptStart->copy()->addMinutes((int) ($apt->duration ?? 60));

            if ($slotStart->lt($aptEnd) && $aptStart->lt($slotEnd)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{available: bool, reason?: string, message?: string}
     */
    public function evaluateSlot(
        int $companyId,
        Carbon $date,
        string $time,
        int $duration = 60,
        ?string $district = null,
        ?int $vehicleId = null,
        ?int $excludeAppointmentId = null
    ): array {
        $time = $this->normalizeTime($time);
        $window = $this->getCompanyDayWindow($companyId, $date);

        if (!$window['open']) {
            return [
                'available' => false,
                'reason' => self::REASON_CERRADO,
                'message' => 'La empresa no atiende este día.',
            ];
        }

        $slotStart = Carbon::createFromFormat('H:i', $time);
        $slotEnd = $slotStart->copy()->addMinutes($duration);
        $dayStart = Carbon::createFromFormat('H:i', $window['start']);
        $dayEnd = Carbon::createFromFormat('H:i', $window['end']);

        if ($slotStart->lt($dayStart) || $slotStart->gt($dayEnd) || $slotEnd->gt($dayEnd)) {
            return [
                'available' => false,
                'reason' => self::REASON_FUERA_HORARIO,
                'message' => "Horario fuera de jornada laboral ({$window['start']} - {$window['end']}).",
            ];
        }

        if ($date->isToday()) {
            $now = Carbon::now();
            if ($slotStart->lte($now)) {
                return [
                    'available' => false,
                    'reason' => self::REASON_PASADO,
                    'message' => 'El horario ya pasó.',
                ];
            }
        }

        $district = $district !== null ? trim($district) : null;
        $hasDistrict = $district !== null && $district !== '';

        if ($vehicleId) {
            $vehicle = Vehicle::find($vehicleId);
            if ($vehicle && $hasDistrict) {
                $clientStub = new Client(['distrito' => $district]);
                $coverage = $this->coverageService->vehicleCoversAppointment(
                    $vehicle,
                    $clientStub,
                    $date,
                    $time,
                    $district
                );
                if (!$coverage['covers']) {
                    return [
                        'available' => false,
                        'reason' => self::REASON_SIN_COBERTURA,
                        'message' => $coverage['message'] ?? 'Sin cobertura para ese distrito u horario.',
                    ];
                }
            }

            $busy = $this->getBusyAppointments($companyId, $date, $vehicleId, $excludeAppointmentId);
            if ($this->slotOverlapsBusy($time, $duration, $busy)) {
                return [
                    'available' => false,
                    'reason' => self::REASON_OCUPADO,
                    'message' => 'El móvil ya tiene una cita en ese horario.',
                ];
            }
        } elseif ($hasDistrict) {
            $coveringVehicles = $this->coverageService->getAvailableVehicles(
                $companyId,
                $district,
                $date,
                $time
            );

            if ($coveringVehicles->isEmpty()) {
                return [
                    'available' => false,
                    'reason' => self::REASON_SIN_COBERTURA,
                    'message' => 'No hay móviles con cobertura para ese distrito en ese horario.',
                ];
            }

            $anyFreeVehicle = $coveringVehicles->contains(function (Vehicle $vehicle) use (
                $companyId,
                $date,
                $time,
                $duration,
                $excludeAppointmentId
            ) {
                $busy = $this->getBusyAppointments($companyId, $date, (int) $vehicle->id, $excludeAppointmentId);

                return !$this->slotOverlapsBusy($time, $duration, $busy);
            });

            if (!$anyFreeVehicle) {
                return [
                    'available' => false,
                    'reason' => self::REASON_OCUPADO,
                    'message' => 'Todos los móviles con cobertura están ocupados en ese horario.',
                ];
            }
        } else {
            $busy = $this->getBusyAppointments($companyId, $date, null, $excludeAppointmentId);
            if ($this->slotOverlapsBusy($time, $duration, $busy)) {
                return [
                    'available' => false,
                    'reason' => self::REASON_OCUPADO,
                    'message' => 'Ya existe una cita en ese horario.',
                ];
            }
        }

        return ['available' => true];
    }

    /**
     * @return array{
     *   date: string,
     *   slots: list<array{time: string, available: bool, reason?: string}>,
     *   coverage_note: ?string,
     *   day_open: bool,
     *   working_window: array{start: string, end: string}
     * }
     */
    public function getSlots(
        int $companyId,
        Carbon $date,
        int $duration = 60,
        ?string $district = null,
        ?int $vehicleId = null,
        ?int $excludeAppointmentId = null,
        ?int $stepMinutes = null
    ): array {
        $window = $this->getCompanyDayWindow($companyId, $date);
        $coverageNote = null;

        if (!$window['open']) {
            return [
                'date' => $date->toDateString(),
                'slots' => [],
                'coverage_note' => 'La empresa no atiende este día.',
                'day_open' => false,
                'working_window' => ['start' => $window['start'], 'end' => $window['end']],
                'closed_reason' => self::REASON_CERRADO,
            ];
        }

        $step = max(15, min(240, $stepMinutes ?? $duration));
        $slotStarts = [];
        $cursor = Carbon::createFromFormat('H:i', $window['start']);
        $endTime = Carbon::createFromFormat('H:i', $window['end']);

        while ($cursor->lte($endTime)) {
            $slotEnd = $cursor->copy()->addMinutes($duration);
            if ($slotEnd->lte($endTime) || $slotEnd->eq($endTime)) {
                $slotStarts[] = $cursor->format('H:i');
            }
            $cursor->addMinutes($step);
        }

        if ($district && trim($district) !== '' && !$vehicleId) {
            $probeVehicles = $this->coverageService->getAvailableVehicles(
                $companyId,
                trim($district),
                $date,
                $window['start']
            );
            if ($probeVehicles->isEmpty()) {
                $coverageNote = 'No hay vehículos con cobertura registrada para ese distrito en la fecha seleccionada. Puedes reservar y el equipo confirmará disponibilidad.';
            }
        }

        $slots = array_map(function (string $time) use (
            $companyId,
            $date,
            $duration,
            $district,
            $vehicleId,
            $excludeAppointmentId
        ) {
            $evaluation = $this->evaluateSlot(
                $companyId,
                $date,
                $time,
                $duration,
                $district,
                $vehicleId,
                $excludeAppointmentId
            );

            $slot = [
                'time' => $time,
                'available' => $evaluation['available'],
            ];

            if (!$evaluation['available'] && !empty($evaluation['reason'])) {
                $slot['reason'] = $evaluation['reason'];
            }

            return $slot;
        }, $slotStarts);

        return [
            'date' => $date->toDateString(),
            'slots' => $slots,
            'coverage_note' => $coverageNote,
            'day_open' => true,
            'working_window' => ['start' => $window['start'], 'end' => $window['end']],
        ];
    }

    /**
     * @return array{valid: bool, reason?: string, message?: string}
     */
    public function validateSlot(
        int $companyId,
        Carbon $date,
        string $time,
        int $duration = 60,
        ?string $district = null,
        ?int $vehicleId = null,
        ?int $excludeAppointmentId = null
    ): array {
        $evaluation = $this->evaluateSlot(
            $companyId,
            $date,
            $time,
            $duration,
            $district,
            $vehicleId,
            $excludeAppointmentId
        );

        return [
            'valid' => $evaluation['available'],
            'reason' => $evaluation['reason'] ?? null,
            'message' => $evaluation['message'] ?? null,
        ];
    }

    private function normalizeTime(string $time): string
    {
        return substr($time, 0, 5);
    }
}
