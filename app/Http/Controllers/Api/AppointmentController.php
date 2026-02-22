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
use App\Models\StockMovement;
use App\Models\Vehicle;
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
            $query = Appointment::with(['client', 'pet', 'vehicle', 'user', 'items.product']);

            // Filtros
            if ($request->has('client_id')) {
                $query->where('client_id', $request->client_id);
            }

            if ($request->has('company_id')) {
                $query->where('company_id', $request->company_id);
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
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Errores de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $validator->validated();
            $data['company_id'] = $data['company_id'] ?? 1;
            $data['address'] = $data['address'] ?? '';

            // 1. Validar Horario Laboral
            $date = Carbon::parse($data['date']);
            $dayOfWeek = strtolower($date->format('l'));
            $dayNamesEs = [
                'monday' => 'lunes', 'tuesday' => 'martes', 'wednesday' => 'miércoles',
                'thursday' => 'jueves', 'friday' => 'viernes', 'saturday' => 'sábado', 'sunday' => 'domingo',
            ];
            $dayLabel = $dayNamesEs[$dayOfWeek] ?? $dayOfWeek;
            $config = CompanyConfiguration::where('company_id', $data['company_id'])
                ->where('config_type', 'document_settings') // As defined in seeder
                ->first();

            if ($config && isset($config->config_data['working_hours'])) {
                $hours = $config->config_data['working_hours'][$dayOfWeek] ?? null;
                if ($hours) {
                    if (!$hours['open']) {
                        return response()->json(['success' => false, 'message' => "La empresa no trabaja los $dayLabel."], 422);
                    }
                    $appointmentTime = Carbon::createFromFormat('H:i', $data['time']);
                    $startTime = Carbon::createFromFormat('H:i', $hours['start']);
                    $endTime = Carbon::createFromFormat('H:i', $hours['end']);

                    if ($appointmentTime->lt($startTime) || $appointmentTime->gt($endTime)) {
                        return response()->json([
                            'success' => false,
                            'message' => "Horario fuera de jornada laboral ($hours[start] - $hours[end])."
                        ], 422);
                    }
                }
            }

            // 2. Validar disponibilidad del vehículo (si se asigna vehículo)
            if (!empty($data['vehicle_id'])) {
                $vehicle = Vehicle::find($data['vehicle_id']);
                if ($vehicle && !empty($vehicle->horario_disponibilidad) && is_array($vehicle->horario_disponibilidad)) {
                    $dayOfWeek = strtolower($date->format('l'));
                    $dayNamesEs = [
                        'monday' => 'lunes', 'tuesday' => 'martes', 'wednesday' => 'miércoles',
                        'thursday' => 'jueves', 'friday' => 'viernes', 'saturday' => 'sábado', 'sunday' => 'domingo',
                    ];
                    $dayLabel = $dayNamesEs[$dayOfWeek] ?? $dayOfWeek;
                    $vehicleHours = $vehicle->horario_disponibilidad[$dayOfWeek] ?? null;
                    if ($vehicleHours) {
                        if (empty($vehicleHours['open'])) {
                            return response()->json([
                                'success' => false,
                                'message' => "El vehículo \"{$vehicle->name}\" no está disponible los {$dayLabel}.",
                            ], 422);
                        }
                        $appointmentTime = Carbon::createFromFormat('H:i', $data['time']);
                        $startTime = Carbon::createFromFormat('H:i', $vehicleHours['start'] ?? '00:00');
                        $endTime = Carbon::createFromFormat('H:i', $vehicleHours['end'] ?? '23:59');
                        if ($appointmentTime->lt($startTime) || $appointmentTime->gt($endTime)) {
                            return response()->json([
                                'success' => false,
                                'message' => "El vehículo \"{$vehicle->name}\" no está disponible a esa hora (disponible {$vehicleHours['start']} - {$vehicleHours['end']}).",
                            ], 422);
                        }
                    }
                }
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

            $data['status'] = 'Pendiente';
            $data['payment_status'] = 'Pendiente';

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

                return response()->json([
                    'success' => true,
                    'message' => $is_recurring ? 'Serie de citas creada exitosamente' : 'Cita creada exitosamente',
                    'data' => $is_recurring ? $appointments[0]->load(['client', 'pet', 'vehicle', 'user']) : $appointment->load(['client', 'pet', 'vehicle', 'user']),
                    'series_count' => count($appointments)
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
                'payment_status' => 'nullable|string|in:Pendiente,Pagado,Reembolsado',
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

                // Procesar deducción de stock si hay un servicio asociado
                if ($appointment->service_id) {
                    $service = Service::find($appointment->service_id);
                    if ($service && !empty($service->required_products)) {
                        foreach ($service->required_products as $req) {
                            $product = Product::find($req['product_id']);
                            if ($product) {
                                // 1. Deduct stock
                                $product->decrement('stock', $req['quantity']);

                                // 2. Record Stock Movement (Kardex)
                                StockMovement::create([
                                    'company_id' => $appointment->company_id,
                                    'branch_id' => $appointment->branch_id,
                                    'product_id' => $product->id,
                                    'movement_date' => now(),
                                    'type' => 'Salida',
                                    'quantity' => $req['quantity'],
                                    'unit_cost' => $product->unit_price ?? 0,
                                    'total_cost' => ($product->unit_price ?? 0) * $req['quantity'],
                                    'source_type' => 'App\Models\Appointment',
                                    'source_id' => $appointment->id,
                                    'notes' => 'Salida automática por cita completada: ' . $appointment->id,
                                    'created_by' => auth()->id() ?? 1,
                                ]);

                                Log::info("Stock deducido por cita completada", [
                                    'appointment_id' => $appointment->id,
                                    'product_id' => $product->id,
                                    'quantity' => $req['quantity']
                                ]);
                            }
                        }
                    }
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
}