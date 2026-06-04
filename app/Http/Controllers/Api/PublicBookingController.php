<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\CompanyConfiguration;
use App\Models\Pet;
use App\Models\Service;
use App\Models\Vehicle;
use App\Services\VehicleCoverageService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PublicBookingController extends Controller
{
    private function publicCompanyId(): int
    {
        return (int) config('smartpet.public_company_id', 1);
    }

    public function config(): JsonResponse
    {
        $companyId = $this->publicCompanyId();

        $workingHours = null;
        $config = CompanyConfiguration::where('company_id', $companyId)
            ->where('config_type', 'document_settings')
            ->first();

        if ($config && isset($config->config_data['working_hours'])) {
            $workingHours = $config->config_data['working_hours'];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'company_id' => $companyId,
                'working_hours' => $workingHours,
            ],
        ]);
    }

    public function services(): JsonResponse
    {
        $companyId = $this->publicCompanyId();

        $dbServices = Service::query()
            ->where('company_id', $companyId)
            ->where('active', true)
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        if ($dbServices->isNotEmpty()) {
            $items = $dbServices->map(function (Service $service) {
                $price = 0;
                $duration = 60;
                if (is_array($service->pricing) && !empty($service->pricing)) {
                    $first = $service->pricing[0] ?? $service->pricing;
                    $price = (float) ($first['price'] ?? $first['amount'] ?? 0);
                    $duration = (int) ($first['duration'] ?? 60);
                }

                $category = $service->category ?? 'MovilVet';
                $serviceCategory = str_contains(strtolower($category), 'pelu') ? 'Peluquería' : 'MovilVet';

                return [
                    'id' => (string) $service->id,
                    'code' => $service->code ?? ('svc-' . $service->id),
                    'name' => $service->name,
                    'description' => $service->description,
                    'category' => $category,
                    'service_category' => $serviceCategory,
                    'price' => $price,
                    'duration' => $duration,
                ];
            })->values();

            return response()->json(['success' => true, 'data' => $items]);
        }

        return response()->json([
            'success' => true,
            'data' => $this->defaultServiceCatalog(),
        ]);
    }

    public function availability(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'date' => 'required|date|after_or_equal:today',
            'district' => 'nullable|string|max:100',
            'duration' => 'nullable|integer|min:15|max:240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        $companyId = $this->publicCompanyId();
        $date = Carbon::parse($request->input('date'));
        $duration = (int) $request->input('duration', 60);
        $district = $request->input('district');

        $slots = $this->buildTimeSlots($companyId, $date, $duration);
        $coverageNote = null;

        if ($district) {
            /** @var VehicleCoverageService $coverageService */
            $coverageService = app(VehicleCoverageService::class);
            $availableVehicles = $coverageService->getAvailableVehicles(
                $companyId,
                $district,
                $date,
                '09:00'
            );

            if ($availableVehicles->isEmpty()) {
                $coverageNote = 'No hay vehículos con cobertura registrada para ese distrito en la fecha seleccionada. Puedes reservar y el equipo confirmará disponibilidad.';
            } else {
                $slots = array_map(function (array $slot) use ($coverageService, $companyId, $date, $district, $availableVehicles) {
                    if (!$slot['available']) {
                        return $slot;
                    }
                    $covers = $availableVehicles->contains(function (Vehicle $vehicle) use ($coverageService, $date, $slot, $district) {
                        $result = $coverageService->vehicleCoversAppointment(
                            $vehicle,
                            new Client(['distrito' => $district]),
                            $date,
                            $slot['time'],
                            $district
                        );
                        return $result['covers'];
                    });
                    if (!$covers) {
                        $slot['available'] = false;
                        $slot['reason'] = 'sin_cobertura';
                    }
                    return $slot;
                }, $slots);
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'date' => $date->toDateString(),
                'slots' => $slots,
                'coverage_note' => $coverageNote,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'client.tipo_documento' => 'required|string|in:1,4,6,7,0',
            'client.numero_documento' => 'required|string|max:20',
            'client.razon_social' => 'required|string|max:255',
            'client.telefono' => 'required|string|max:20',
            'client.email' => 'nullable|email|max:255',
            'client.direccion' => 'required|string|max:500',
            'client.distrito' => 'required|string|max:100',
            'client.provincia' => 'nullable|string|max:100',
            'client.departamento' => 'nullable|string|max:100',
            'pet.name' => 'required|string|max:255',
            'pet.species' => 'required|string|in:Perro,Gato,Otro',
            'pet.breed' => 'nullable|string|max:255',
            'pet.age' => 'nullable|integer|min:0|max:30',
            'pet.weight' => 'nullable|numeric|min:0|max:200',
            'appointment.service_type' => 'required|string|max:100',
            'appointment.service_name' => 'required|string|max:255',
            'appointment.service_category' => 'required|string|in:MovilVet,Peluquería',
            'appointment.date' => 'required|date|after_or_equal:today',
            'appointment.time' => 'required|date_format:H:i',
            'appointment.duration' => 'nullable|integer|min:15|max:480',
            'appointment.price' => 'required|numeric|min:0',
            'appointment.payment_method' => 'nullable|string|in:Efectivo,Tarjeta,Yape,Plin,Transferencia',
            'appointment.notes' => 'nullable|string|max:2000',
            'appointment.service_id' => 'nullable|integer|exists:services,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        $payload = $validator->validated();
        $companyId = $this->publicCompanyId();
        $clientInput = $payload['client'];
        $petInput = $payload['pet'];
        $aptInput = $payload['appointment'];

        try {
            DB::beginTransaction();

            $client = Client::query()
                ->where('company_id', $companyId)
                ->where('tipo_documento', $clientInput['tipo_documento'])
                ->where('numero_documento', $clientInput['numero_documento'])
                ->first();

            if (!$client) {
                $client = Client::create([
                    'company_id' => $companyId,
                    'client_type' => 'persona',
                    'tipo_documento' => $clientInput['tipo_documento'],
                    'numero_documento' => $clientInput['numero_documento'],
                    'razon_social' => $clientInput['razon_social'],
                    'telefono' => $clientInput['telefono'],
                    'email' => $clientInput['email'] ?? null,
                    'direccion' => $clientInput['direccion'],
                    'distrito' => $clientInput['distrito'],
                    'provincia' => $clientInput['provincia'] ?? 'Lima',
                    'departamento' => $clientInput['departamento'] ?? 'Lima',
                    'activo' => true,
                    'fecha_registro' => now()->toDateString(),
                ]);
            } else {
                $client->update([
                    'razon_social' => $clientInput['razon_social'],
                    'telefono' => $clientInput['telefono'],
                    'email' => $clientInput['email'] ?? $client->email,
                    'direccion' => $clientInput['direccion'],
                    'distrito' => $clientInput['distrito'],
                    'provincia' => $clientInput['provincia'] ?? $client->provincia,
                    'departamento' => $clientInput['departamento'] ?? $client->departamento,
                ]);
            }

            $pet = Pet::create([
                'client_id' => $client->id,
                'company_id' => $companyId,
                'name' => $petInput['name'],
                'species' => $petInput['species'],
                'breed' => $petInput['breed'] ?? null,
                'age' => $petInput['age'] ?? null,
                'weight' => $petInput['weight'] ?? null,
                'fecha_registro' => now()->toDateString(),
            ]);

            $date = Carbon::parse($aptInput['date']);
            $vehicleId = $this->resolveVehicleId($companyId, $client, $date, $aptInput['time'], $clientInput['distrito']);

            $price = (float) $aptInput['price'];
            $appointment = Appointment::create([
                'tracking_code' => $this->generateTrackingCode(),
                'client_id' => $client->id,
                'pet_id' => $pet->id,
                'company_id' => $companyId,
                'vehicle_id' => $vehicleId,
                'service_id' => $aptInput['service_id'] ?? null,
                'service_type' => $aptInput['service_type'],
                'service_name' => $aptInput['service_name'],
                'service_category' => $aptInput['service_category'],
                'date' => $aptInput['date'],
                'time' => $aptInput['time'],
                'duration' => $aptInput['duration'] ?? 60,
                'address' => $clientInput['direccion'],
                'district' => $clientInput['distrito'],
                'province' => $clientInput['provincia'] ?? 'Lima',
                'department' => $clientInput['departamento'] ?? 'Lima',
                'status' => 'Pendiente',
                'price' => $price,
                'discount' => 0,
                'total' => $price,
                'payment_status' => 'Pendiente',
                'payment_method' => $aptInput['payment_method'] ?? null,
                'notes' => $this->buildPublicBookingNotes($aptInput['notes'] ?? null),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Reserva registrada correctamente',
                'data' => [
                    'tracking_code' => $appointment->tracking_code,
                    'appointment_id' => $appointment->id,
                    'status' => $appointment->status,
                    'date' => $appointment->date?->format('Y-m-d'),
                    'time' => substr((string) $appointment->time, 0, 5),
                    'vehicle_assigned' => $vehicleId !== null,
                ],
            ], 201);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error en reserva pública', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo registrar la reserva. Intenta nuevamente.',
            ], 500);
        }
    }

    public function track(string $code): JsonResponse
    {
        $normalized = strtoupper(trim($code));

        $appointment = Appointment::with(['client', 'pet', 'vehicle'])
            ->where('tracking_code', $normalized)
            ->first();

        if (!$appointment) {
            return response()->json([
                'success' => false,
                'message' => 'No encontramos una reserva con ese código.',
            ], 404);
        }

        $vehicle = $appointment->vehicle;
        $driverName = $vehicle?->driver_name ?? null;
        $vehicleLabel = $vehicle
            ? trim(($vehicle->marca ?? '') . ' ' . ($vehicle->modelo ?? $vehicle->name ?? '') . ($vehicle->placa ? ' • ' . $vehicle->placa : ''))
            : null;

        return response()->json([
            'success' => true,
            'data' => [
                'code' => $appointment->tracking_code,
                'status' => $this->mapStatusForTracking($appointment->status),
                'status_label' => $appointment->status,
                'service' => [
                    'name' => $appointment->service_name,
                    'price' => (float) $appointment->total,
                ],
                'pet' => [
                    'name' => $appointment->pet?->name,
                    'breed' => $appointment->pet?->breed,
                    'species' => $appointment->pet?->species,
                ],
                'schedule' => [
                    'date' => $appointment->date?->format('Y-m-d'),
                    'time' => substr((string) $appointment->time, 0, 5),
                    'address' => $appointment->address,
                    'district' => $appointment->district,
                ],
                'driver' => $driverName ? [
                    'name' => $driverName,
                    'vehicle' => $vehicleLabel,
                    'phone' => null,
                ] : null,
            ],
        ]);
    }

    private function generateTrackingCode(): string
    {
        do {
            $code = 'SPT-' . strtoupper(Str::random(6));
        } while (Appointment::where('tracking_code', $code)->exists());

        return $code;
    }

    private function resolveVehicleId(
        int $companyId,
        Client $client,
        Carbon $date,
        string $time,
        string $district
    ): ?int {
        /** @var VehicleCoverageService $coverageService */
        $coverageService = app(VehicleCoverageService::class);
        $vehicles = $coverageService->getAvailableVehicles($companyId, $district, $date, $time);

        $vehicle = $vehicles->first();
        return $vehicle?->id;
    }

    private function buildPublicBookingNotes(?string $notes): string
    {
        $prefix = '[Portal público]';
        $body = trim((string) $notes);

        return $body !== '' ? "{$prefix} {$body}" : $prefix;
    }

    private function mapStatusForTracking(?string $status): string
    {
        return match ($status) {
            'Confirmada' => 'preparing',
            'En Proceso' => 'on-the-way',
            'Completada' => 'completed',
            'Cancelada' => 'cancelled',
            default => 'confirmed',
        };
    }

    /**
     * @return list<array{time: string, available: bool, reason?: string}>
     */
    private function buildTimeSlots(int $companyId, Carbon $date, int $duration): array
    {
        $dayOfWeek = strtolower($date->format('l'));
        $start = '08:00';
        $end = '18:00';
        $isOpen = true;

        $config = CompanyConfiguration::where('company_id', $companyId)
            ->where('config_type', 'document_settings')
            ->first();

        if ($config && isset($config->config_data['working_hours'][$dayOfWeek])) {
            $hours = $config->config_data['working_hours'][$dayOfWeek];
            $isOpen = (bool) ($hours['open'] ?? true);
            $start = $hours['start'] ?? $start;
            $end = $hours['end'] ?? $end;
        }

        if (!$isOpen) {
            return [];
        }

        $slotStarts = [];
        $cursor = Carbon::createFromFormat('H:i', substr($start, 0, 5));
        $endTime = Carbon::createFromFormat('H:i', substr($end, 0, 5));

        while ($cursor->lte($endTime)) {
            $slotStarts[] = $cursor->format('H:i');
            $cursor->addMinutes($duration);
        }

        $busy = Appointment::query()
            ->where('company_id', $companyId)
            ->whereDate('date', $date->toDateString())
            ->whereNotIn('status', ['Cancelada'])
            ->get(['time', 'duration']);

        return array_map(function (string $time) use ($busy, $duration) {
            $available = !$this->slotOverlapsBusy($time, $duration, $busy);
            return [
                'time' => $time,
                'available' => $available,
                ...(!$available ? ['reason' => 'ocupado'] : []),
            ];
        }, $slotStarts);
    }

    private function slotOverlapsBusy(string $slotTime, int $duration, $busyAppointments): bool
    {
        $slotStart = Carbon::createFromFormat('H:i', substr($slotTime, 0, 5));
        $slotEnd = $slotStart->copy()->addMinutes($duration);

        foreach ($busyAppointments as $apt) {
            $aptStart = Carbon::createFromFormat('H:i', substr((string) $apt->time, 0, 5));
            $aptEnd = $aptStart->copy()->addMinutes((int) ($apt->duration ?? 60));

            if ($slotStart->lt($aptEnd) && $aptStart->lt($slotEnd)) {
                return true;
            }
        }

        return false;
    }

    private function defaultServiceCatalog(): array
    {
        return [
            ['id' => 'grooming-basic', 'code' => 'grooming-basic', 'name' => 'Baño Básico', 'description' => 'Baño, secado y corte de uñas', 'category' => 'Grooming Móvil', 'service_category' => 'Peluquería', 'price' => 50, 'duration' => 60],
            ['id' => 'grooming-complete', 'code' => 'grooming-complete', 'name' => 'Grooming Completo', 'description' => 'Baño, corte, secado, limpieza dental', 'category' => 'Grooming Móvil', 'service_category' => 'Peluquería', 'price' => 90, 'duration' => 90],
            ['id' => 'vet-consultation', 'code' => 'vet-consultation', 'name' => 'Consulta Veterinaria', 'description' => 'Revisión general y diagnóstico', 'category' => 'Veterinaria Móvil', 'service_category' => 'MovilVet', 'price' => 80, 'duration' => 45],
            ['id' => 'vet-vaccination', 'code' => 'vet-vaccination', 'name' => 'Vacunación', 'description' => 'Vacunas preventivas', 'category' => 'Veterinaria Móvil', 'service_category' => 'MovilVet', 'price' => 60, 'duration' => 30],
            ['id' => 'vet-deworming', 'code' => 'vet-deworming', 'name' => 'Desparasitación', 'description' => 'Tratamiento antiparasitario', 'category' => 'Veterinaria Móvil', 'service_category' => 'MovilVet', 'price' => 40, 'duration' => 30],
        ];
    }
}
