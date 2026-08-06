<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AppointmentItem;
use App\Models\Pet;
use App\Models\Service;
use App\Models\CompanyConfiguration;
use App\Models\Product;
use App\Models\Client;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\Payment;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Auth;
use App\Services\AppointmentBillingService;
use App\Services\AppointmentDocumentCorrectionService;
use App\Services\AppointmentPaymentStatusService;
use App\Services\AvailabilityService;
use App\Services\PortalBookingService;
use App\Services\VehicleCoverageService;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class AppointmentController extends Controller
{
    /**
     * Listar citas
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Appointment::with(['client', 'pet', 'vehicle', 'user', 'items.product', 'boleta', 'invoice']);

            $scopedCompanyId = \App\Helpers\ScopeHelper::companyId($request);
            if ($scopedCompanyId) {
                $query->where('company_id', $scopedCompanyId);
            } elseif ($request->user()?->hasRole('super_admin') && $request->filled('company_id')) {
                $query->where('company_id', (int) $request->company_id);
            }

            // Filtros
            if ($request->has('client_id')) {
                $query->where('client_id', $request->client_id);
            }

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('date')) {
                $query->whereDate('date', $request->date);
            }

            if ($request->has('date_from')) {
                $query->whereDate('date', '>=', $request->date_from);
            }

            if ($request->has('date_to')) {
                $query->whereDate('date', '<=', $request->date_to);
            }

            if ($request->has('vehicle_id')) {
                $query->where('vehicle_id', $request->vehicle_id);
            }

            if ($request->filled('booking_source')) {
                $query->where('booking_source', $request->booking_source);
            }

            $appointments = $query->orderBy('date', 'asc')
                ->orderBy('time', 'asc')
                ->paginate($request->integer('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => $appointments->items(),
                'meta' => [
                    'total' => $appointments->total(),
                    'per_page' => $appointments->perPage(),
                    'current_page' => $appointments->currentPage(),
                    'last_page' => $appointments->lastPage()
                ]
            ]);

        } catch (Exception $e) {
            Log::error("Error al listar citas", ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener citas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Disponibilidad unificada para staff (misma lógica que portal público).
     */
    public function availability(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'date' => 'required|date|after_or_equal:today',
            'district' => 'nullable|string|max:100',
            'duration' => 'nullable|integer|min:15|max:240',
            'vehicle_id' => 'nullable|integer|exists:vehicles,id',
            'exclude_appointment_id' => 'nullable|integer|exists:appointments,id',
            'company_id' => 'nullable|integer|exists:companies,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        $companyId = $request->input('company_id')
            ?? \App\Helpers\ScopeHelper::companyId($request)
            ?? $request->user()?->company_id;

        if (!$companyId) {
            return response()->json([
                'success' => false,
                'message' => 'company_id es requerido o el usuario debe tener empresa asignada.',
            ], 422);
        }

        $date = Carbon::parse($request->input('date'));
        $duration = (int) $request->input('duration', 60);

        /** @var AvailabilityService $availabilityService */
        $availabilityService = app(AvailabilityService::class);
        $result = $availabilityService->getSlots(
            (int) $companyId,
            $date,
            $duration,
            $request->input('district'),
            $request->input('vehicle_id') ? (int) $request->input('vehicle_id') : null,
            $request->input('exclude_appointment_id') ? (int) $request->input('exclude_appointment_id') : null
        );

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * Crear nueva cita
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'client_id' => 'required|integer|exists:clients,id',
                'pet_id' => 'required|integer|exists:pets,id',
                'company_id' => 'nullable|integer|exists:companies,id',
                'service_id' => 'nullable|integer|exists:services,id',
                'branch_id' => 'nullable|integer|exists:branches,id',
                'vehicle_id' => 'nullable|integer|exists:vehicles,id',
                'user_id' => 'nullable|integer|exists:users,id',
                'service_type' => 'required|string',
                'service_name' => 'required|string|max:255',
                'service_category' => 'required|string|in:MovilVet,Peluquería',
                'date' => 'required|date',
                'time' => 'required|date_format:H:i',
                'duration' => 'nullable|integer|min:15|max:480',
                'address' => 'nullable|string|max:500',
                'district' => 'nullable|string|max:100',
                'province' => 'nullable|string|max:100',
                'department' => 'nullable|string|max:100',
                'latitude' => 'nullable|numeric',
                'longitude' => 'nullable|numeric',
                'price' => 'required|numeric|min:0',
                'discount' => 'nullable|numeric|min:0',
                'payment_method' => 'nullable|string|in:Efectivo,Tarjeta,Yape,Plin,Transferencia',
                'notes' => 'nullable|string',
                'is_recurring' => 'nullable|boolean',
                'recurrence_series_id' => 'nullable|string',
                'recurrence_type' => 'nullable|string|in:daily,weekly,monthly',
                'recurrence_occurrences' => 'nullable|integer|min:1|max:52',
                'recurrence_days' => 'nullable|array',
                'recurrence_fixed_time' => 'nullable|boolean',
                'booking_source' => 'nullable|string|in:staff,portal_auth,public_guest',
                'advance_paid' => 'nullable|boolean',
                'advance_payment_method' => 'nullable|string|max:50',
                'advance_payment_reference' => 'nullable|string|max:100',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Errores de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $validator->validated();
            $data['company_id'] = $data['company_id'] ?? \App\Helpers\ScopeHelper::companyId($request) ?? $request->user()?->company_id;
            if (empty($data['company_id'])) {
                return response()->json(['success' => false, 'message' => 'company_id es requerido o el usuario debe tener empresa asignada.'], 422);
            }
            $data['address'] = $data['address'] ?? '';

            $date = Carbon::parse($data['date']);
            $duration = (int) ($data['duration'] ?? 60);
            $districtForAvailability = $data['district'] ?? null;

            // 1. Auto-asignar vehículo por cobertura si no se envió
            if (empty($data['vehicle_id'])) {
                $clientForCoverage = Client::find($data['client_id']);
                $districtForCoverage = $data['district'] ?? $clientForCoverage?->distrito;
                if ($districtForCoverage) {
                    /** @var VehicleCoverageService $coverageService */
                    $coverageService = app(VehicleCoverageService::class);
                    $available = $coverageService->getAvailableVehicles(
                        (int) $data['company_id'],
                        $districtForCoverage,
                        $date,
                        $data['time']
                    );
                    if ($available->isNotEmpty()) {
                        $data['vehicle_id'] = $available->first()->id;
                    }
                }
            }

            // 2. Validar disponibilidad unificada (horario + cobertura + ocupación)
            $clientForAvailability = Client::find($data['client_id']);
            if (!$districtForAvailability) {
                $districtForAvailability = $clientForAvailability?->distrito;
            }

            /** @var AvailabilityService $availabilityService */
            $availabilityService = app(AvailabilityService::class);
            $slotCheck = $availabilityService->validateSlot(
                (int) $data['company_id'],
                $date,
                $data['time'],
                $duration,
                $districtForAvailability,
                !empty($data['vehicle_id']) ? (int) $data['vehicle_id'] : null
            );

            if (!$slotCheck['valid']) {
                return response()->json([
                    'success' => false,
                    'message' => $slotCheck['message'] ?? 'Horario no disponible.',
                    'reason' => $slotCheck['reason'] ?? null,
                ], 422);
            }

            // 3. Validar Stock si hay service_id
            if (isset($data['service_id'])) {
                $service = Service::find($data['service_id']);
                if ($service && !empty($service->required_products)) {
                    foreach ($service->required_products as $req) {
                        $product = Product::find($req['product_id']);
                        if (!$product || $product->stock < $req['quantity']) {
                            return response()->json([
                                'success' => false,
                                'message' => "Stock insuficiente del producto: " . ($product ? $product->name : "ID " . $req['product_id'])
                            ], 422);
                        }
                    }
                }
            }

            // 4. Aplicar Descuento Automático según Nivel de Cliente
            $client = Client::find($data['client_id']);
            if ($client && $client->nivel_fidelizacion) {
                $discounts = [
                    'Oro' => 15,    // 15%
                    'Bronce' => 10, // 10%
                    'Plata' => 0    // 0%
                ];
                $discountPercent = $discounts[$client->nivel_fidelizacion] ?? 0;
                if ($discountPercent > 0) {
                    $autoDiscount = ($data['price'] * $discountPercent) / 100;
                    // Solo aplicar si el descuento manual es menor
                    if (!isset($data['discount']) || $data['discount'] < $autoDiscount) {
                        $data['discount'] = $autoDiscount;
                    }
                }
                $data['client_category'] = $client->nivel_fidelizacion;
            }

            // Calcular total desde items si existen, sino usar price del request
            $items = $request->input('items', []);
            if (!empty($items)) {
                $totalFromItems = 0;
                foreach ($items as $item) {
                    $itemPrice = $item['price'] ?? 0;
                    $itemQuantity = $item['quantity'] ?? 1;
                    $totalFromItems += $itemPrice * $itemQuantity;
                }
                $data['price'] = $totalFromItems;
            }

            $data['total'] = $data['price'] - ($data['discount'] ?? 0);

            /** @var PortalBookingService $portalService */
            $portalService = app(PortalBookingService::class);
            $bookingSource = $request->input('booking_source', PortalBookingService::SOURCE_STAFF);
            $data['booking_source'] = $bookingSource;

            if ($bookingSource === PortalBookingService::SOURCE_PORTAL_AUTH) {
                $settings = $portalService->getSettings((int) $data['company_id']);
                $clientForPortal = $client ?? Client::find($data['client_id']);
                $portalCheck = $portalService->canClientBook($clientForPortal, $settings);
                if (!$portalCheck['allowed']) {
                    return response()->json([
                        'success' => false,
                        'message' => $portalCheck['message'],
                    ], 403);
                }

                $advanceAmount = $portalService->calculateAdvance((float) $data['total'], $settings);
                $data['advance_amount'] = $advanceAmount;
                $advancePaid = $request->boolean('advance_paid', false);
                $resolved = $portalService->resolveStatusForPortalBooking(
                    $clientForPortal,
                    $settings,
                    $advancePaid,
                    $advanceAmount
                );
                $data['status'] = $resolved['status'];
                $data['payment_status'] = $resolved['payment_status'];
                if (!empty($resolved['confirmed_at'])) {
                    $data['confirmed_at'] = $resolved['confirmed_at'];
                }
                if ($advancePaid) {
                    $data['advance_paid_at'] = now();
                    $data['advance_payment_method'] = $request->input('advance_payment_method', 'Tarjeta');
                    $data['advance_payment_reference'] = $request->input('advance_payment_reference');
                }
                if (empty($data['tracking_code'])) {
                    $data['tracking_code'] = $portalService->generateTrackingCode();
                }
            } else {
                $data['status'] = 'Pendiente';
                $data['payment_status'] = 'Pendiente';
            }

            $appointments = [];
            $is_recurring = $request->input('is_recurring', false);
            $occurrences = $request->input('recurrence_occurrences', 1);
            $series_id = $is_recurring ? ($request->input('recurrence_series_id') ?? (string) Str::uuid()) : null;

            DB::beginTransaction();

            try {
                for ($i = 0; $i < ($is_recurring ? $occurrences : 1); $i++) {
                    $appointmentData = $data;

                    if ($is_recurring) {
                        $currentDate = Carbon::parse($data['date']);
                        if ($i > 0) {
                            switch ($data['recurrence_type']) {
                                case 'daily':
                                    $currentDate->addDays($i);
                                    break;
                                case 'weekly':
                                    $currentDate->addWeeks($i);
                                    break;
                                case 'monthly':
                                    $currentDate->addMonths($i);
                                    break;
                                default:
                                    $currentDate->addWeeks($i);
                            }
                            $appointmentData['date'] = $currentDate->toDateString();
                        }
                        $appointmentData['is_recurring'] = true;
                        $appointmentData['recurrence_series_id'] = $series_id;
                        $appointmentData['parent_appointment_id'] = ($i > 0) ? $appointments[0]->id : null;
                    }

                    $appointment = Appointment::create($appointmentData);
                    $appointments[] = $appointment;

                    // Crear items de la cita
                    if (!empty($items)) {
                        foreach ($items as $item) {
                            AppointmentItem::create([
                                'appointment_id' => $appointment->id,
                                'product_id' => $item['item_id'] ?? null,
                                'item_type' => $item['item_type'] ?? 'SERVICIO',
                                'name' => $item['name'] ?? '',
                                'quantity' => $item['quantity'] ?? 1,
                                'price' => $item['price'] ?? 0,
                                'duration' => $item['duration'] ?? null,
                                'subtotal' => ($item['price'] ?? 0) * ($item['quantity'] ?? 1),
                            ]);
                        }
                    }
                }

                DB::commit();

                $createdAppointment = $is_recurring ? $appointments[0] : $appointment;
                if ($bookingSource === PortalBookingService::SOURCE_PORTAL_AUTH && $createdAppointment) {
                    $createdAppointment->load(['client', 'pet']);
                    $event = $createdAppointment->status === 'Pendiente' ? 'pending_approval' : 'created';
                    $portalService->notifyStaffPortalBooking($createdAppointment, $event);
                }

                return response()->json([
                    'success' => true,
                    'message' => $is_recurring ? 'Serie de citas creada exitosamente' : 'Cita creada exitosamente',
                    'data' => $is_recurring ? $appointments[0]->load(['client', 'pet', 'vehicle', 'user']) : $appointment->load(['client', 'pet', 'vehicle', 'user']),
                    'series_count' => count($appointments),
                    'advance_amount' => $createdAppointment?->advance_amount,
                    'tracking_code' => $createdAppointment?->tracking_code,
                ], 201);

            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (Exception $e) {
            Log::error("Error al crear cita", [
                'request_data' => $request->all(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al crear cita: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mostrar cita
     */
    public function show($id): JsonResponse
    {
        try {
            $appointment = Appointment::with([
                'client',
                'pet',
                'vehicle',
                'user',
                'medicalRecord',
                'items.product',
                'parentAppointment',
                'childAppointments'
            ])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $appointment
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Cita no encontrada'
            ], 404);
        }
    }

    /**
     * Actualizar cita
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $appointment = Appointment::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'status' => 'sometimes|string|in:Pendiente,Confirmada,En Proceso,Completada,Cancelada',
                'vehicle_id' => 'nullable|integer|exists:vehicles,id',
                'user_id' => 'nullable|integer|exists:users,id',
                'date' => 'sometimes|date',
                'time' => 'sometimes|date_format:H:i',
                'duration' => 'nullable|integer|min:15|max:480',
                'price' => 'nullable|numeric|min:0',
                'discount' => 'nullable|numeric|min:0',
                'payment_status' => 'nullable|string|in:Pendiente,Parcial,Pagado,Reembolsado',
                'payment_method' => 'nullable|string|in:Efectivo,Tarjeta,Yape,Plin,Transferencia',
                'notes' => 'nullable|string',
                'cancellation_reason' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Errores de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $validator->validated();

            // Validar cobertura si cambia vehículo, fecha u hora
            $vehicleId = $data['vehicle_id'] ?? $appointment->vehicle_id;
            $appointmentDate = isset($data['date']) ? Carbon::parse($data['date']) : Carbon::parse($appointment->date);
            $appointmentTime = $data['time'] ?? $appointment->time;
            if ($vehicleId) {
                $vehicle = Vehicle::find($vehicleId);
                if ($vehicle) {
                    $client = $appointment->client ?? Client::find($appointment->client_id);
                    /** @var VehicleCoverageService $coverageService */
                    $coverageService = app(VehicleCoverageService::class);
                    $coverage = $coverageService->vehicleCoversAppointment(
                        $vehicle,
                        $client,
                        $appointmentDate,
                        is_string($appointmentTime) ? substr($appointmentTime, 0, 5) : (string) $appointmentTime,
                        $appointment->district ?? $client?->distrito
                    );
                    if (!$coverage['covers']) {
                        return response()->json([
                            'success' => false,
                            'message' => $coverage['message'] ?? 'El vehículo no está disponible para esta cita.',
                        ], 422);
                    }
                }
            }

            // Actualizar timestamps según el estado
            if (isset($data['status'])) {
                if ($data['status'] === 'Confirmada' && !$appointment->confirmed_at) {
                    $data['confirmed_at'] = now();
                }
                if ($data['status'] === 'Completada' && !$appointment->completed_at) {
                    $data['completed_at'] = now();
                }
                if ($data['status'] === 'Cancelada' && !$appointment->cancelled_at) {
                    $data['cancelled_at'] = now();
                }
            }

            // Recalcular total si cambia precio o descuento
            if (isset($data['price']) || isset($data['discount'])) {
                $price = $data['price'] ?? $appointment->price;
                $discount = $data['discount'] ?? $appointment->discount;
                $data['total'] = $price - $discount;
            }

            $appointment->update($data);

            // Actualizar items si se proporcionan
            if ($request->has('items')) {
                // Eliminar items existentes
                $appointment->items()->delete();

                // Crear nuevos items
                $items = $request->input('items', []);
                foreach ($items as $item) {
                    AppointmentItem::create([
                        'appointment_id' => $appointment->id,
                        'product_id' => $item['item_id'] ?? null,
                        'item_type' => $item['item_type'] ?? 'SERVICIO',
                        'name' => $item['name'] ?? '',
                        'quantity' => $item['quantity'] ?? 1,
                        'price' => $item['price'] ?? 0,
                        'duration' => $item['duration'] ?? null,
                        'subtotal' => ($item['price'] ?? 0) * ($item['quantity'] ?? 1),
                    ]);
                }

                // Recalcular total
                $totalFromItems = $appointment->items()->sum('subtotal');
                $appointment->update([
                    'price' => $totalFromItems,
                    'total' => $totalFromItems - ($appointment->discount ?? 0)
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Cita actualizada exitosamente',
                'data' => $appointment->load(['client', 'pet', 'vehicle', 'user', 'items.product'])
            ]);

        } catch (Exception $e) {
            Log::error("Error al actualizar cita", [
                'appointment_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar cita: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar cita
     */
    public function destroy($id): JsonResponse
    {
        try {
            $appointment = Appointment::findOrFail($id);
            $appointment->delete();

            return response()->json([
                'success' => true,
                'message' => 'Cita eliminada exitosamente'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar cita'
            ], 500);
        }
    }

    /**
     * Reprogramar cita
     */
    public function reschedule(Request $request, $id): JsonResponse
    {
        try {
            $appointment = Appointment::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'date' => 'required|date',
                'time' => 'required|date_format:H:i',
                'vehicle_id' => 'nullable|integer|exists:vehicles,id',
                'notes' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Errores de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $validator->validated();

            // Agregar nota de reprogramación
            if (isset($data['notes'])) {
                $data['notes'] = ($appointment->notes ? $appointment->notes . "\n" : '') .
                    "[Reprogramada] " . $data['notes'];
            }

            $appointment->update($data);

            Log::info("Cita reprogramada", [
                'appointment_id' => $appointment->id,
                'new_date' => $data['date'],
                'new_time' => $data['time']
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cita reprogramada exitosamente',
                'data' => $appointment->load(['client', 'pet', 'vehicle', 'user', 'items.product'])
            ]);

        } catch (Exception $e) {
            Log::error("Error al reprogramar cita", [
                'appointment_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al reprogramar cita: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cambiar estado de cita
     */
    public function changeStatus(Request $request, $id): JsonResponse
    {
        try {
            $appointment = Appointment::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'status' => 'required|string|in:Pendiente,Confirmada,En Proceso,Completada,Cancelada',
                'cancellation_reason' => 'nullable|string|required_if:status,Cancelada',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Errores de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            $status = $request->input('status');
            $data = ['status' => $status];

            // Actualizar timestamps según el estado
            if ($status === 'Confirmada' && !$appointment->confirmed_at) {
                $data['confirmed_at'] = now();
                $data['confirmation_sent'] = false; // Se enviará notificación
            }
            if ($status === 'Completada' && !$appointment->completed_at) {
                $data['completed_at'] = now();

                $productService = app(\App\Services\ProductService::class);

                // Deducción por insumos del servicio (modelo Services.required_products)
                if ($appointment->service_id) {
                    $service = Service::find($appointment->service_id);
                    if ($service && !empty($service->required_products)) {
                        foreach ($service->required_products as $req) {
                            $product = Product::find($req['product_id']);
                            if ($product) {
                                $qty = (float) ($req['quantity'] ?? 0);
                                if ($qty <= 0) {
                                    continue;
                                }
                                $productService->adjustStock(
                                    $product,
                                    null,
                                    $qty,
                                    'OUT',
                                    'Salida por cita completada #' . $appointment->id,
                                    [
                                        'wrap_transaction' => false,
                                        'source_type' => 'appointment',
                                        'source_id' => $appointment->id,
                                        'branch_id' => $appointment->branch_id,
                                        'unit_cost' => (float) ($product->cost_price ?? 0),
                                        'created_by' => auth()->id(),
                                    ]
                                );
                            }
                        }
                    }
                }

                // Deducción por ítems producto vendidos en la cita
                $appointment->loadMissing('items');
                foreach ($appointment->items as $item) {
                    $itemType = strtoupper((string) ($item->item_type ?? ''));
                    if (! in_array($itemType, ['PRODUCTO', 'PRODUCT'], true)) {
                        continue;
                    }
                    $productId = $item->product_id ?? $item->item_id ?? null;
                    if (! $productId) {
                        continue;
                    }
                    $product = Product::find($productId);
                    $qty = (float) ($item->quantity ?? 1);
                    if (! $product || $qty <= 0) {
                        continue;
                    }
                    $productService->adjustStock(
                        $product,
                        null,
                        $qty,
                        'OUT',
                        'Salida por producto en cita #' . $appointment->id,
                        [
                            'wrap_transaction' => false,
                            'source_type' => 'appointment_item',
                            'source_id' => $appointment->id,
                            'branch_id' => $appointment->branch_id,
                            'unit_cost' => (float) ($product->cost_price ?? 0),
                            'created_by' => auth()->id(),
                        ]
                    );
                }
            }
            if ($status === 'Cancelada' && !$appointment->cancelled_at) {
                $data['cancelled_at'] = now();
                if ($request->has('cancellation_reason')) {
                    $data['cancellation_reason'] = $request->input('cancellation_reason');
                }
            }

            $appointment->update($data);

            return response()->json([
                'success' => true,
                'message' => 'Estado de cita actualizado exitosamente',
                'data' => $appointment->load(['client', 'pet', 'vehicle', 'user', 'items.product'])
            ]);

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (Exception $e) {
            Log::error("Error al cambiar estado de cita", [
                'appointment_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al cambiar estado: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Enviar recordatorio
     */
    public function sendReminder($id): JsonResponse
    {
        try {
            $appointment = Appointment::findOrFail($id);

            if ($appointment->reminder_sent) {
                return response()->json([
                    'success' => false,
                    'message' => 'El recordatorio ya fue enviado'
                ], 400);
            }

            $appointment->update([
                'reminder_sent' => true,
                'reminder_sent_at' => now(),
            ]);

            // Aquí podrías integrar con servicios de notificación (email, SMS, etc.)
            Log::info("Recordatorio enviado", ['appointment_id' => $appointment->id]);

            return response()->json([
                'success' => true,
                'message' => 'Recordatorio enviado exitosamente',
                'data' => $appointment
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            throw $e;
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al enviar recordatorio: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Confirmar cita
     */
    public function confirm($id): JsonResponse
    {
        try {
            $appointment = Appointment::findOrFail($id);

            if ($appointment->status === 'Completada' || $appointment->status === 'Cancelada') {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede confirmar una cita completada o cancelada'
                ], 400);
            }

            $appointment->update([
                'status' => 'Confirmada',
                'confirmed_at' => now(),
                'confirmation_sent' => false, // Se enviará notificación
            ]);

            Log::info("Cita confirmada", ['appointment_id' => $appointment->id]);

            return response()->json([
                'success' => true,
                'message' => 'Cita confirmada exitosamente',
                'data' => $appointment->load(['client', 'pet', 'vehicle', 'user', 'items.product'])
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            throw $e;
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al confirmar cita: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener citas por cliente
     */
    public function getByClient($clientId): JsonResponse
    {
        try {
            $appointments = Appointment::with(['client', 'pet', 'vehicle', 'user', 'items.product'])
                ->where('client_id', $clientId)
                ->orderBy('date', 'desc')
                ->orderBy('time', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $appointments
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener citas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener serie de citas recurrentes
     */
    public function getRecurringSeries($seriesId): JsonResponse
    {
        try {
            $appointments = Appointment::with(['client', 'pet', 'vehicle', 'user', 'items.product'])
                ->where('recurrence_series_id', $seriesId)
                ->orderBy('date', 'asc')
                ->orderBy('time', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $appointments
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener serie: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Registrar cobro de cita (visita móvil) sin emitir comprobante SUNAT aún.
     */
    public function billingPreview(Appointment $appointment, AppointmentBillingService $billing): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $billing->preview($appointment->load(['client', 'pet', 'items', 'boleta', 'invoice'])),
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function issueDocument(Request $request, Appointment $appointment, AppointmentBillingService $billing): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tipo' => 'nullable|in:auto,01,03',
            'serie' => 'nullable|string|max:4',
            'send_to_sunat' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $result = $billing->issue($appointment, [
                'tipo' => $request->input('tipo', 'auto'),
                'serie' => $request->input('serie'),
                'send_to_sunat' => $request->boolean('send_to_sunat'),
            ]);

            return response()->json([
                'success' => true,
                'message' => ($result['tipo_documento'] === '01' ? 'Factura' : 'Boleta') . ' emitida: ' . $result['numero_completo'],
                'data' => $result,
            ], 201);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Opciones de anulación (≤7 días desde emisión) / nota de crédito.
     */
    public function documentCorrectionOptions(
        Appointment $appointment,
        AppointmentDocumentCorrectionService $correction
    ): JsonResponse {
        try {
            return response()->json([
                'success' => true,
                'data' => $correction->options($appointment),
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Anular CPE de la cita (ventana 7 días desde fecha_emision).
     * No modifica payment_status.
     */
    public function voidDocument(
        Request $request,
        Appointment $appointment,
        AppointmentDocumentCorrectionService $correction
    ): JsonResponse {
        $validator = Validator::make($request->all(), [
            'motivo' => 'nullable|string|max:500',
            'send_to_sunat' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $result = $correction->voidDocument($appointment, [
                'motivo' => $request->input('motivo'),
                'send_to_sunat' => $request->has('send_to_sunat')
                    ? $request->boolean('send_to_sunat')
                    : null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Comprobante anulado. El cobro de la cita no se modificó.',
                'data' => $result,
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Emite nota de crédito total o parcial sobre el CPE de la cita.
     * No modifica payment_status ni reabre cobro (regla 4A).
     */
    public function issueCreditNote(
        Request $request,
        Appointment $appointment,
        AppointmentDocumentCorrectionService $correction
    ): JsonResponse {
        $validator = Validator::make($request->all(), [
            'mode' => 'required|in:total,partial',
            'cod_motivo' => 'nullable|string|max:2',
            'des_motivo' => 'nullable|string|max:250',
            'serie' => 'nullable|string|max:4',
            'send_to_sunat' => 'nullable|boolean',
            'detalles' => 'nullable|array|min:1',
            'detalles.*.codigo' => 'nullable|string|max:30',
            'detalles.*.descripcion' => 'required_with:detalles|string|max:255',
            'detalles.*.cantidad' => 'required_with:detalles|numeric|min:0.01',
            'detalles.*.mto_valor_unitario' => 'required_with:detalles|numeric|min:0.01',
            'detalles.*.unidad' => 'nullable|string|max:10',
            'detalles.*.tip_afe_igv' => 'nullable|string|max:2',
            'detalles.*.porcentaje_igv' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $result = $correction->issueCreditNote($appointment, [
                'mode' => $request->input('mode'),
                'cod_motivo' => $request->input('cod_motivo'),
                'des_motivo' => $request->input('des_motivo'),
                'serie' => $request->input('serie'),
                'detalles' => $request->input('detalles'),
                'send_to_sunat' => $request->boolean('send_to_sunat'),
            ]);

            $cn = $result['credit_note'];
            $numero = $cn->numero_completo ?? ($cn->serie . '-' . $cn->correlativo);

            return response()->json([
                'success' => true,
                'message' => 'Nota de crédito emitida: ' . $numero . '. El cobro de la cita no se modificó.',
                'data' => $result,
            ], 201);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Registrar adelanto simulado de reserva portal (Fase 1).
     */
    public function payAdvance(Request $request, Appointment $appointment): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'payment_method' => 'required|string|in:Tarjeta,Yape,Plin,Transferencia,Efectivo',
                'reference' => 'nullable|string|max:100',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Errores de validación',
                    'errors' => $validator->errors(),
                ], 422);
            }

            if ($appointment->booking_source !== PortalBookingService::SOURCE_PORTAL_AUTH) {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo aplica a citas creadas desde el portal autenticado',
                ], 422);
            }

            if ($appointment->advance_paid_at) {
                return response()->json([
                    'success' => false,
                    'message' => 'El adelanto ya fue registrado',
                ], 409);
            }

            /** @var PortalBookingService $portalService */
            $portalService = app(PortalBookingService::class);
            $settings = $portalService->getSettings((int) $appointment->company_id);
            $advanceAmount = (float) ($appointment->advance_amount ?? 0);

            if ($advanceAmount <= 0) {
                $advanceAmount = $portalService->calculateAdvance((float) $appointment->total, $settings);
                $appointment->advance_amount = $advanceAmount;
                $appointment->save();
            }

            $updated = $portalService->applyAdvancePayment(
                $appointment,
                $request->input('payment_method'),
                $request->input('reference')
            );

            return response()->json([
                'success' => true,
                'message' => $updated->status === 'Confirmada'
                    ? 'Adelanto registrado. Tu cita fue confirmada.'
                    : 'Adelanto registrado. El equipo validará tu cita.',
                'data' => $updated->load(['client', 'pet', 'vehicle']),
            ]);
        } catch (Exception $e) {
            Log::error('Error al registrar adelanto portal', [
                'appointment_id' => $appointment->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al registrar adelanto: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function registerPayment(Request $request, Appointment $appointment): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'payment_method' => 'required|string|in:Efectivo,Tarjeta,Yape,Plin,Transferencia',
                'amount' => 'nullable|numeric|min:0.01',
                'cash_session_id' => 'nullable|integer|exists:cash_sessions,id',
                'reference' => 'nullable|string|max:100',
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
            }

            if (!in_array($appointment->status, ['Completada', 'En Proceso', 'Confirmada'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo se puede cobrar citas confirmadas, en proceso o completadas',
                ], 422);
            }

            /** @var AppointmentPaymentStatusService $paymentStatus */
            $paymentStatus = app(AppointmentPaymentStatusService::class);
            $remaining = $paymentStatus->remainingAmount($appointment);

            if ($remaining <= AppointmentPaymentStatusService::EPSILON) {
                return response()->json(['success' => false, 'message' => 'La cita ya está pagada'], 409);
            }

            $method = $request->input('payment_method');
            $cashSessionId = $request->input('cash_session_id');
            $requestedAmount = $request->filled('amount')
                ? (float) $request->input('amount')
                : $remaining;
            // No exigir más que el saldo; evita cobrar de más por defecto.
            $amount = round(min($requestedAmount, $remaining), 2);

            if ($amount <= AppointmentPaymentStatusService::EPSILON) {
                return response()->json(['success' => false, 'message' => 'Monto de cobro inválido'], 422);
            }

            DB::transaction(function () use ($appointment, $amount, $method, $cashSessionId, $request, $paymentStatus) {
                $session = $cashSessionId
                    ? CashSession::where('id', $cashSessionId)->where('status', 'OPEN')->first()
                    : null;

                Payment::create([
                    'company_id' => $appointment->company_id,
                    'branch_id' => $appointment->branch_id,
                    'invoice_id' => $appointment->invoice_id,
                    'appointment_id' => $appointment->id,
                    'user_id' => Auth::id(),
                    'cash_session_id' => $session?->id,
                    'amount' => $amount,
                    'fee' => 0,
                    'net_amount' => $amount,
                    'currency' => 'PEN',
                    'method' => $paymentStatus->mapCashMethodToPayment($method),
                    'gateway' => 'manual',
                    'status' => 'completed',
                    'reference' => $request->input('reference'),
                    'paid_at' => now(),
                    'notes' => 'Cobro cita #' . $appointment->id,
                    'metadata' => [
                        'source' => 'cash_register',
                        'appointment_id' => $appointment->id,
                        'tracking_code' => $appointment->tracking_code,
                        'payment_method_label' => $method,
                    ],
                ]);

                CashMovement::create([
                    'company_id' => $appointment->company_id,
                    'branch_id' => $appointment->branch_id,
                    'vehicle_id' => $appointment->vehicle_id,
                    'appointment_id' => $appointment->id,
                    'user_id' => Auth::id(),
                    'cash_session_id' => $session?->id,
                    'type' => 'INCOME',
                    'amount' => $amount,
                    'description' => 'Cobro cita #' . $appointment->id . ' — ' . ($appointment->service_name ?: 'Servicio'),
                    'payment_method' => $method,
                    'reference' => $request->input('reference'),
                    'movement_date' => now(),
                    'metadata' => [
                        'appointment_id' => $appointment->id,
                        'tracking_code' => $appointment->tracking_code,
                    ],
                ]);

                $fresh = $appointment->fresh();
                $status = $paymentStatus->resolveStatus($fresh);
                $fresh->update([
                    'payment_status' => $status,
                    'payment_method' => $method,
                    'status' => $fresh->status === 'Confirmada' ? 'Completada' : $fresh->status,
                ]);
            });

            $updated = $appointment->fresh()->load(['client', 'pet', 'vehicle']);

            return response()->json([
                'success' => true,
                'message' => 'Cobro registrado',
                'data' => $updated,
                'meta' => [
                    'paid_amount' => $paymentStatus->paidAmount($updated),
                    'remaining_amount' => $paymentStatus->remainingAmount($updated),
                    'payment_status' => $updated->payment_status,
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al registrar cobro: ' . $e->getMessage(),
            ], 500);
        }
    }
}