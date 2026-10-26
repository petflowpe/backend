<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Company;
use App\Models\CompanyConfiguration;
use App\Models\Pet;
use App\Models\Service;
use App\Models\Vehicle;
use App\Services\AvailabilityService;
use App\Services\PortalBookingService;
use App\Services\VehicleCoverageService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PublicBookingController extends Controller
{
    /**
     * Resuelve la empresa del portal: ruta {companySlug}, header, query o fallback .env.
     */
    private function resolveCompany(Request $request, ?string $companySlug = null): Company
    {
        $raw = $companySlug
            ?? $request->header('X-Booking-Slug')
            ?? $request->query('company')
            ?? $request->query('slug')
            ?? config('smartpet.public_booking_slug');

        $slug = is_string($raw) ? Str::slug(trim($raw)) : '';

        if ($slug !== '') {
            $company = Company::query()
                ->where('activo', true)
                ->where('booking_slug', $slug)
                ->first();

            if (! $company) {
                throw new NotFoundHttpException('Empresa de reservas no encontrada.');
            }

            return $company;
        }

        $fallbackId = (int) config('smartpet.public_company_id', 1);
        $company = Company::query()
            ->where('activo', true)
            ->whereKey($fallbackId)
            ->first();

        if (! $company) {
            throw new NotFoundHttpException('Empresa de reservas no configurada.');
        }

        return $company;
    }

    public function config(Request $request, ?string $companySlug = null): JsonResponse
    {
        $company = $this->resolveCompany($request, $companySlug);
        $companyId = (int) $company->id;

        $workingHours = null;
        $config = CompanyConfiguration::where('company_id', $companyId)
            ->where('config_type', 'document_settings')
            ->first();

        if ($config && isset($config->config_data['working_hours'])) {
            $workingHours = $config->config_data['working_hours'];
        }

        /** @var PortalBookingService $portalService */
        $portalService = app(PortalBookingService::class);
        $portalSettings = $portalService->getSettings($companyId);

        return response()->json([
            'success' => true,
            'data' => [
                'company_id' => $companyId,
                'booking_slug' => $company->booking_slug,
                'company_name' => $company->nombre_comercial ?: $company->razon_social,
                'working_hours' => $workingHours,
                'portal_settings' => $portalSettings,
            ],
        ]);
    }

    public function services(Request $request, ?string $companySlug = null): JsonResponse
    {
        $companyId = (int) $this->resolveCompany($request, $companySlug)->id;

        $dbServices = Service::query()
            ->where('company_id', $companyId)
            ->where('active', true)
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        if ($dbServices->isNotEmpty()) {
            $items = $dbServices->map(function (Service $service) {
                $rep = \App\Services\ServiceCatalogService::representativePricing(
                    is_array($service->pricing) ? $service->pricing : null
                );
                $price = $rep['price'];
                $duration = $rep['duration'];

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
                    'pricing_by_size' => (bool) $service->pricing_by_size,
                    'pricing' => $service->pricing,
                ];
            })->values();

            return response()->json(['success' => true, 'data' => $items]);
        }

        return response()->json([
            'success' => true,
            'data' => $this->defaultServiceCatalog(),
        ]);
    }

    public function availability(Request $request, ?string $companySlug = null): JsonResponse
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

        $companyId = (int) $this->resolveCompany($request, $companySlug)->id;
        $date = Carbon::parse($request->input('date'));
        $duration = (int) $request->input('duration', 60);
        $district = $request->input('district');

        /** @var AvailabilityService $availabilityService */
        $availabilityService = app(AvailabilityService::class);
        $result = $availabilityService->getSlots(
            $companyId,
            $date,
            $duration,
            $district ? (string) $district : null
        );

        return response()->json([
            'success' => true,
            'data' => [
                'date' => $result['date'],
                'slots' => $result['slots'],
                'coverage_note' => $result['coverage_note'],
                'day_open' => $result['day_open'],
                'working_window' => $result['working_window'],
                'closed_reason' => $result['closed_reason'] ?? null,
            ],
        ]);
    }

    public function store(Request $request, ?string $companySlug = null): JsonResponse
    {
        // Honeypot anti-bot: campo oculto que los humanos dejan vacío.
        if (filled($request->input('website')) || filled($request->input('company_url'))) {
            return response()->json([
                'success' => true,
                'message' => 'Reserva registrada correctamente',
                'data' => [
                    'tracking_code' => 'SPT-OK',
                    'appointment_id' => 0,
                ],
            ], 201);
        }

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
            // El precio del cliente se ignora: siempre se calcula en el servidor.
            'appointment.price' => 'nullable|numeric|min:0',
            'appointment.payment_method' => 'nullable|string|in:Efectivo,Tarjeta,Yape,Plin,Transferencia',
            'appointment.notes' => 'nullable|string|max:2000',
            'appointment.service_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        $payload = $validator->validated();
        $companyId = (int) $this->resolveCompany($request, $companySlug)->id;

        /** @var PortalBookingService $portalService */
        $portalService = app(PortalBookingService::class);
        $portalSettings = $portalService->getSettings($companyId);
        if (empty($portalSettings['guest_booking_enabled'])) {
            return response()->json([
                'success' => false,
                'message' => 'Las reservas sin cuenta están deshabilitadas. Inicia sesión en el portal de clientes.',
            ], 403);
        }

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

            $petName = trim((string) $petInput['name']);
            $pet = Pet::query()
                ->where('client_id', $client->id)
                ->where('company_id', $companyId)
                ->where('species', $petInput['species'])
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($petName)])
                ->first();

            if ($pet) {
                $pet->update([
                    'breed' => $petInput['breed'] ?? $pet->breed,
                    'age' => $petInput['age'] ?? $pet->age,
                    'weight' => $petInput['weight'] ?? $pet->weight,
                ]);
            } else {
                $pet = Pet::create([
                    'client_id' => $client->id,
                    'company_id' => $companyId,
                    'name' => $petName,
                    'species' => $petInput['species'],
                    'breed' => $petInput['breed'] ?? null,
                    'age' => $petInput['age'] ?? null,
                    'weight' => $petInput['weight'] ?? null,
                    'fecha_registro' => now()->toDateString(),
                ]);
            }

            $catalog = $this->resolvePublicService($companyId, $aptInput);
            if (! $catalog) {
                throw new Exception('El servicio indicado no está disponible para reserva pública.');
            }

            $date = Carbon::parse($aptInput['date']);
            $vehicleId = $this->resolveVehicleId($companyId, $client, $date, $aptInput['time'], $clientInput['distrito']);

            /** @var AvailabilityService $availabilityService */
            $availabilityService = app(AvailabilityService::class);
            $slotCheck = $availabilityService->claimSlot(
                $companyId,
                $date,
                (string) $aptInput['time'],
                (int) $catalog['duration'],
                (string) $clientInput['distrito'],
                $vehicleId
            );

            if (! $slotCheck['valid']) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => $slotCheck['message'] ?? 'El horario ya no está disponible.',
                    'reason' => $slotCheck['reason'] ?? 'ocupado',
                ], 409);
            }

            $price = (float) $catalog['price'];
            $appointment = Appointment::create([
                'tracking_code' => $this->generateTrackingCode(),
                'booking_source' => PortalBookingService::SOURCE_PUBLIC_GUEST,
                'client_id' => $client->id,
                'pet_id' => $pet->id,
                'company_id' => $companyId,
                'vehicle_id' => $vehicleId,
                'service_id' => $catalog['service_id'],
                'service_type' => $catalog['service_type'],
                'service_name' => $catalog['service_name'],
                'service_category' => $catalog['service_category'],
                'date' => $aptInput['date'],
                'time' => $aptInput['time'],
                'duration' => $catalog['duration'],
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

            $appointment->load(['client', 'pet']);
            $portalService->notifyStaffPortalBooking($appointment, 'pending_approval');

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

    public function track(Request $request, string $code, ?string $companySlug = null): JsonResponse
    {
        $companySlug = is_string($request->route('companySlug'))
            ? (string) $request->route('companySlug')
            : $companySlug;

        $normalized = strtoupper(trim($code));

        $query = Appointment::with(['client', 'pet', 'vehicle'])
            ->where('tracking_code', $normalized);

        if ($companySlug || $request->query('company') || $request->header('X-Booking-Slug')) {
            try {
                $companyId = (int) $this->resolveCompany($request, $companySlug)->id;
                $query->where('company_id', $companyId);
            } catch (NotFoundHttpException $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Empresa de reservas no encontrada.',
                ], 404);
            }
        }

        $appointment = $query->first();

        if (! $appointment) {
            return response()->json([
                'success' => false,
                'message' => 'No encontramos una reserva con ese código.',
            ], 404);
        }

        $verified = $this->verifyTrackingPhone(
            (string) ($appointment->client?->telefono ?? ''),
            (string) ($request->query('phone') ?? $request->input('phone') ?? '')
        );

        $base = [
            'code' => $appointment->tracking_code,
            'status' => $this->mapStatusForTracking($appointment->status),
            'status_label' => $appointment->status,
            'verified' => $verified,
            'service' => [
                'name' => $appointment->service_name,
                'price' => $verified ? (float) $appointment->total : null,
            ],
            'pet' => [
                'name' => $verified ? $appointment->pet?->name : $this->maskName($appointment->pet?->name),
                'breed' => $verified ? $appointment->pet?->breed : null,
                'species' => $appointment->pet?->species,
            ],
            'schedule' => [
                'date' => $appointment->date?->format('Y-m-d'),
                'time' => substr((string) $appointment->time, 0, 5),
                'address' => $verified ? $appointment->address : null,
                'district' => $appointment->district,
            ],
            'driver' => null,
        ];

        if ($verified) {
            $vehicle = $appointment->vehicle;
            $driverName = $vehicle?->driver_name ?? null;
            $vehicleLabel = $vehicle
                ? trim(($vehicle->marca ?? '') . ' ' . ($vehicle->modelo ?? $vehicle->name ?? '') . ($vehicle->placa ? ' • ' . $vehicle->placa : ''))
                : null;
            $base['driver'] = $driverName ? [
                'name' => $driverName,
                'vehicle' => $vehicleLabel,
                'phone' => null,
            ] : null;
        }

        return response()->json([
            'success' => true,
            'data' => $base,
        ]);
    }

    private function verifyTrackingPhone(string $storedPhone, string $provided): bool
    {
        $digitsStored = preg_replace('/\D+/', '', $storedPhone) ?? '';
        $digitsProvided = preg_replace('/\D+/', '', $provided) ?? '';

        if ($digitsProvided === '' || $digitsStored === '') {
            return false;
        }

        if ($digitsProvided === $digitsStored) {
            return true;
        }

        // Últimos 4 dígitos bastan para desbloquear detalle.
        return strlen($digitsProvided) >= 4
            && str_ends_with($digitsStored, substr($digitsProvided, -4));
    }

    private function maskName(?string $name): ?string
    {
        if ($name === null || $name === '') {
            return null;
        }
        $name = trim($name);
        $first = mb_substr($name, 0, 1);

        return $first . str_repeat('*', max(2, mb_strlen($name) - 1));
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

    /**
     * Precio/nombre/duración desde el catálogo del servidor. Nunca se usa appointment.price del cliente.
     *
     * @return array{service_id: ?int, service_name: string, service_type: string, service_category: string, price: float, duration: int}|null
     */
    private function resolvePublicService(int $companyId, array $aptInput): ?array
    {
        $serviceId = isset($aptInput['service_id']) ? (int) $aptInput['service_id'] : 0;
        if ($serviceId > 0) {
            $service = Service::query()
                ->where('company_id', $companyId)
                ->where('active', true)
                ->whereKey($serviceId)
                ->first();
            if ($service) {
                return $this->mapDbServiceToBooking($service, $aptInput);
            }
        }

        $name = trim((string) ($aptInput['service_name'] ?? ''));
        if ($name !== '') {
            $service = Service::query()
                ->where('company_id', $companyId)
                ->where('active', true)
                ->where('name', $name)
                ->first();
            if ($service) {
                return $this->mapDbServiceToBooking($service, $aptInput);
            }
        }

        // Catálogo demo solo si la empresa aún no tiene servicios.
        if (Service::query()->where('company_id', $companyId)->where('active', true)->exists()) {
            return null;
        }

        $code = (string) ($aptInput['service_type'] ?? '');
        foreach ($this->defaultServiceCatalog() as $row) {
            if ($row['code'] === $code || $row['name'] === $name || (string) $row['id'] === (string) $serviceId) {
                return [
                    'service_id' => null,
                    'service_name' => $row['name'],
                    'service_type' => $row['code'],
                    'service_category' => $row['service_category'],
                    'price' => (float) $row['price'],
                    'duration' => (int) ($aptInput['duration'] ?? $row['duration']),
                ];
            }
        }

        return null;
    }

    private function mapDbServiceToBooking(Service $service, array $aptInput): array
    {
        $rep = \App\Services\ServiceCatalogService::representativePricing(
            is_array($service->pricing) ? $service->pricing : null
        );
        $category = $service->category ?? 'MovilVet';
        $serviceCategory = str_contains(strtolower($category), 'pelu') ? 'Peluquería' : 'MovilVet';

        return [
            'service_id' => (int) $service->id,
            'service_name' => $service->name,
            'service_type' => $service->code ?? ('svc-' . $service->id),
            'service_category' => $aptInput['service_category'] ?? $serviceCategory,
            'price' => (float) $rep['price'],
            'duration' => (int) ($aptInput['duration'] ?? $rep['duration']),
        ];
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
