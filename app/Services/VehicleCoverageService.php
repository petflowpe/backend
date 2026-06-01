<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Vehicle;
use App\Models\VehicleCoverageRule;
use App\Models\Zone;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class VehicleCoverageService
{
    public const VALID_DAYS = [
        'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday',
    ];

    public function normalizeDistrict(?string $district): string
    {
        return Str::lower(Str::ascii(trim((string) $district)));
    }

    public function normalizeTime(string $time): string
    {
        return substr($time, 0, 5);
    }

    public function hasActiveRules(Vehicle $vehicle): bool
    {
        return VehicleCoverageRule::query()
            ->where('vehicle_id', $vehicle->id)
            ->where('active', true)
            ->exists();
    }

    public function timesOverlap(string $startA, string $endA, string $startB, string $endB): bool
    {
        $startA = $this->normalizeTime($startA);
        $endA = $this->normalizeTime($endA);
        $startB = $this->normalizeTime($startB);
        $endB = $this->normalizeTime($endB);

        return $startA < $endB && $startB < $endA;
    }

    public function assertNoOverlap(
        Vehicle $vehicle,
        array $districts,
        array $days,
        string $startTime,
        string $endTime,
        ?int $excludeRuleId = null
    ): void {
        $normalizedDistricts = array_values(array_unique(array_map(
            fn ($d) => $this->normalizeDistrict((string) $d),
            $districts
        )));

        $existing = VehicleCoverageRule::query()
            ->where('vehicle_id', $vehicle->id)
            ->where('active', true)
            ->when($excludeRuleId, fn ($q) => $q->where('id', '!=', $excludeRuleId))
            ->get();

        foreach ($existing as $rule) {
            $ruleDistricts = array_map(
                fn ($d) => $this->normalizeDistrict((string) $d),
                $rule->districts ?? []
            );
            $sharedDistricts = array_intersect($normalizedDistricts, $ruleDistricts);
            if ($sharedDistricts === []) {
                continue;
            }

            $sharedDays = array_values(array_intersect($days, $rule->days ?? []));
            if ($sharedDays === []) {
                continue;
            }

            if ($this->timesOverlap($startTime, $endTime, (string) $rule->start_time, (string) $rule->end_time)) {
                $districtLabel = implode(', ', array_slice($sharedDistricts, 0, 2));
                $dayLabel = implode(', ', array_slice($sharedDays, 0, 2));
                throw ValidationException::withMessages([
                    'start_time' => [
                        "La regla se solapa con otra existente (distritos: {$districtLabel}; días: {$dayLabel}).",
                    ],
                ]);
            }
        }
    }

    public function validateDistrictsBelongToZone(Zone $zone, array $districts): void
    {
        $zoneDistricts = array_map(
            fn ($d) => $this->normalizeDistrict((string) $d),
            $zone->districts ?? []
        );
        $invalid = [];

        foreach ($districts as $district) {
            $normalized = $this->normalizeDistrict((string) $district);
            if (!in_array($normalized, $zoneDistricts, true)) {
                $invalid[] = $district;
            }
        }

        if ($invalid !== []) {
            throw ValidationException::withMessages([
                'districts' => [
                    'Distritos no válidos para la zona seleccionada: ' . implode(', ', $invalid),
                ],
            ]);
        }
    }

    public function matchesRule(VehicleCoverageRule $rule, string $district, Carbon $datetime): bool
    {
        if (!$rule->active) {
            return false;
        }

        $day = strtolower($datetime->format('l'));
        if (!in_array($day, $rule->days ?? [], true)) {
            return false;
        }

        $normalizedDistrict = $this->normalizeDistrict($district);
        $ruleDistricts = array_map(
            fn ($d) => $this->normalizeDistrict((string) $d),
            $rule->districts ?? []
        );
        if (!in_array($normalizedDistrict, $ruleDistricts, true)) {
            return false;
        }

        $time = $datetime->format('H:i');
        $start = $this->normalizeTime((string) $rule->start_time);
        $end = $this->normalizeTime((string) $rule->end_time);

        if ($time < $start || $time > $end) {
            return false;
        }

        if ($rule->max_daily_appointments !== null) {
            $count = Appointment::query()
                ->where('vehicle_id', $rule->vehicle_id)
                ->whereDate('date', $datetime->toDateString())
                ->whereNotIn('status', ['Cancelada'])
                ->count();
            if ($count >= $rule->max_daily_appointments) {
                return false;
            }
        }

        return true;
    }

    public function resolveDistrict(?Client $client, ?string $districtOverride = null): ?string
    {
        if ($districtOverride !== null && trim($districtOverride) !== '') {
            return trim($districtOverride);
        }

        return $client?->distrito ? trim((string) $client->distrito) : null;
    }

    public function validateVehicleSchedule(Vehicle $vehicle, Carbon $date, string $time): ?string
    {
        $dayOfWeek = strtolower($date->format('l'));
        $dayNamesEs = [
            'monday' => 'lunes', 'tuesday' => 'martes', 'wednesday' => 'miércoles',
            'thursday' => 'jueves', 'friday' => 'viernes', 'saturday' => 'sábado', 'sunday' => 'domingo',
        ];
        $dayLabel = $dayNamesEs[$dayOfWeek] ?? $dayOfWeek;

        if (empty($vehicle->horario_disponibilidad) || !is_array($vehicle->horario_disponibilidad)) {
            return null;
        }

        $vehicleHours = $vehicle->horario_disponibilidad[$dayOfWeek] ?? null;
        if (!$vehicleHours) {
            return null;
        }

        if (empty($vehicleHours['open'])) {
            return "El vehículo \"{$vehicle->name}\" no está disponible los {$dayLabel}.";
        }

        $appointmentTime = Carbon::createFromFormat('H:i', $this->normalizeTime($time));
        $startTime = Carbon::createFromFormat('H:i', $this->normalizeTime($vehicleHours['start'] ?? '00:00'));
        $endTime = Carbon::createFromFormat('H:i', $this->normalizeTime($vehicleHours['end'] ?? '23:59'));

        if ($appointmentTime->lt($startTime) || $appointmentTime->gt($endTime)) {
            return "El vehículo \"{$vehicle->name}\" no está disponible a esa hora (disponible {$vehicleHours['start']} - {$vehicleHours['end']}).";
        }

        return null;
    }

    /**
     * @return array{covers: bool, message: ?string, rule: ?VehicleCoverageRule}
     */
    public function vehicleCoversAppointment(
        Vehicle $vehicle,
        ?Client $client,
        Carbon $date,
        string $time,
        ?string $districtOverride = null
    ): array {
        $district = $this->resolveDistrict($client, $districtOverride);
        $datetime = Carbon::parse($date->format('Y-m-d') . ' ' . $this->normalizeTime($time));

        if ($this->hasActiveRules($vehicle)) {
            if (!$district) {
                return [
                    'covers' => false,
                    'message' => 'Se requiere el distrito del cliente para validar la cobertura del vehículo.',
                    'rule' => null,
                ];
            }

            $rules = VehicleCoverageRule::query()
                ->where('vehicle_id', $vehicle->id)
                ->where('active', true)
                ->orderByDesc('priority')
                ->orderBy('id')
                ->get();

            foreach ($rules as $rule) {
                if ($this->matchesRule($rule, $district, $datetime)) {
                    return ['covers' => true, 'message' => null, 'rule' => $rule];
                }
            }

            return [
                'covers' => false,
                'message' => "El vehículo \"{$vehicle->name}\" no cubre {$district} en ese día u horario.",
                'rule' => null,
            ];
        }

        if (!empty($vehicle->zonas_asignadas) && is_array($vehicle->zonas_asignadas) && $client?->zone_id) {
            $zoneIds = array_map('intval', $vehicle->zonas_asignadas);
            if (!in_array((int) $client->zone_id, $zoneIds, true)) {
                return [
                    'covers' => false,
                    'message' => "El vehículo \"{$vehicle->name}\" no está asignado a la zona del cliente.",
                    'rule' => null,
                ];
            }
        }

        $scheduleError = $this->validateVehicleSchedule($vehicle, $date, $time);
        if ($scheduleError) {
            return ['covers' => false, 'message' => $scheduleError, 'rule' => null];
        }

        return ['covers' => true, 'message' => null, 'rule' => null];
    }

    public function getAvailableVehicles(
        int $companyId,
        string $district,
        Carbon $date,
        string $time
    ): Collection {
        $clientStub = new Client(['distrito' => $district]);

        return Vehicle::query()
            ->where('company_id', $companyId)
            ->where('activo', true)
            ->orderBy('name')
            ->get()
            ->filter(function (Vehicle $vehicle) use ($clientStub, $date, $time, $district) {
                $result = $this->vehicleCoversAppointment($vehicle, $clientStub, $date, $time, $district);
                return $result['covers'];
            })
            ->values();
    }

    public function migrateVehicleFromLegacy(Vehicle $vehicle): int
    {
        if ($this->hasActiveRules($vehicle)) {
            return 0;
        }

        $zoneIds = array_filter(array_map('intval', $vehicle->zonas_asignadas ?? []));
        if ($zoneIds === []) {
            return 0;
        }

        $created = 0;
        $horario = is_array($vehicle->horario_disponibilidad) ? $vehicle->horario_disponibilidad : [];

        foreach ($zoneIds as $zoneId) {
            $zone = Zone::find($zoneId);
            if (!$zone || empty($zone->districts)) {
                continue;
            }

            $days = [];
            $startTime = '08:00';
            $endTime = '18:00';

            foreach (self::VALID_DAYS as $day) {
                $dayHours = $horario[$day] ?? null;
                if (!$dayHours) {
                    continue;
                }
                $isOpen = array_key_exists('open', $dayHours) ? (bool) $dayHours['open'] : true;
                if (!$isOpen) {
                    continue;
                }
                $days[] = $day;
                $startTime = $this->normalizeTime((string) ($dayHours['start'] ?? $startTime));
                $endTime = $this->normalizeTime((string) ($dayHours['end'] ?? $endTime));
            }

            if ($days === []) {
                $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
            }

            VehicleCoverageRule::create([
                'company_id' => $vehicle->company_id,
                'vehicle_id' => $vehicle->id,
                'zone_id' => $zone->id,
                'districts' => $zone->districts,
                'days' => $days,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'priority' => 0,
                'active' => true,
                'notes' => 'Migrado desde zonas_asignadas y horario_disponibilidad',
            ]);
            $created++;
        }

        return $created;
    }
}
