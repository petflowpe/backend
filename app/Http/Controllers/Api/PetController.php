<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\RespondsWithPagination;
use App\Models\Pet;
use App\Models\PetPhoto;
use App\Models\Client;
use App\Models\MedicalRecord;
use App\Models\VaccineRecord;
use App\Models\Appointment;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\View;
use Dompdf\Dompdf;
use Dompdf\Options;
use Exception;

class PetController extends Controller
{
    use RespondsWithPagination;
    /**
     * Listar mascotas
     */
    public function index(Request $request): JsonResponse
    {
        try {
            if ($auth = $this->denyWithoutPermission($request, 'pets.view')) {
                return $auth;
            }
            $query = $this->buildPetsQuery($request);
            $perPage = (int) $request->input('per_page', 15);
            $perPage = max(1, min($perPage, 5000));
            $pets = $query->paginate($perPage);

            return $this->paginatedResponse($pets);

        } catch (Exception $e) {
            Log::error("Error al listar mascotas", ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener mascotas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Construye la query de mascotas con filtros y orden (usado por index y exportPdf).
     */
    protected function buildPetsQuery(Request $request)
    {
        $query = Pet::with([
            'client:id,razon_social,numero_documento',
            'owners:id,razon_social,numero_documento',
            'company:id,razon_social',
            'photos'
        ]);

        if ($request->has('client_id')) {
            $clientId = (int) $request->client_id;
            $query->where(function ($q) use ($clientId) {
                $q->where('client_id', $clientId)
                    ->orWhereHas('owners', fn ($ownersQuery) => $ownersQuery->where('clients.id', $clientId));
            });
        }
        if ($request->has('company_id')) {
            $query->where('company_id', $request->company_id);
        }
        if ($request->boolean('only_active', false)) {
            $query->where('fallecido', false);
        }
        if ($request->boolean('fallecido', false)) {
            $query->where('fallecido', true);
        }
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('breed', 'like', "%{$search}%")
                  ->orWhere('microchip', 'like', "%{$search}%")
                  ->orWhere('identification_number', 'like', "%{$search}%");
            });
        }
        if ($request->filled('species')) {
            $query->where('species', $request->species);
        }
        if ($request->filled('breed')) {
            $query->where('breed', $request->breed);
        }
        if ($request->filled('gender')) {
            $query->where('gender', $request->gender);
        }
        if ($request->boolean('birthday_soon', false)) {
            $query->whereNotNull('birth_date');
            $query->whereRaw("DATEDIFF(
                IF(STR_TO_DATE(CONCAT(YEAR(CURDATE()), '-', LPAD(MONTH(birth_date), 2, '0'), '-', LPAD(DAY(birth_date), 2, '0')), '%Y-%m-%d') >= CURDATE(),
                    STR_TO_DATE(CONCAT(YEAR(CURDATE()), '-', LPAD(MONTH(birth_date), 2, '0'), '-', LPAD(DAY(birth_date), 2, '0')), '%Y-%m-%d'),
                    STR_TO_DATE(CONCAT(YEAR(CURDATE())+1, '-', LPAD(MONTH(birth_date), 2, '0'), '-', LPAD(DAY(birth_date), 2, '0')), '%Y-%m-%d')),
                CURDATE()) BETWEEN 0 AND 30");
        }
        $sortBy = $request->get('sort_by', 'name');
        $sortOrder = strtolower($request->get('sort_order', 'asc')) === 'desc' ? 'desc' : 'asc';
        $allowedSort = ['name', 'age', 'birth_date', 'species', 'created_at'];
        if (in_array($sortBy, $allowedSort)) {
            $query->orderBy($sortBy === 'birth_date' ? 'birth_date' : $sortBy, $sortOrder);
        } else {
            $query->orderBy('name', 'asc');
        }
        return $query;
    }

    /**
     * Exportar listado de mascotas en PDF (respeta los mismos filtros que index).
     */
    public function exportPdf(Request $request)
    {
        try {
            if ($auth = $this->denyWithoutPermission($request, 'pets.view')) {
                return $auth;
            }
            $query = $this->buildPetsQuery($request);
            $pets = $query->limit(2000)->get();
            $html = View::make('pdf.pets-export', ['pets' => $pets])->render();
            $options = new Options();
            $options->set('defaultFont', 'DejaVu Sans');
            $options->set('isHtml5ParserEnabled', true);
            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'landscape');
            $dompdf->render();
            $filename = 'Mascotas_' . now()->format('Y-m-d_His') . '.pdf';
            return response()->streamDownload(
                fn () => print($dompdf->output()),
                $filename,
                ['Content-Type' => 'application/pdf']
            );
        } catch (Exception $e) {
            Log::error("Error al exportar PDF mascotas", ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error al generar PDF: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear nueva mascota
     */
    public function store(Request $request): JsonResponse
    {
        try {
            if ($auth = $this->denyWithoutPermission($request, 'pets.create')) {
                return $auth;
            }
            $validator = Validator::make($request->all(), [
                'client_id' => 'required|integer|exists:clients,id',
                'company_id' => 'nullable|integer|exists:companies,id',
                'name' => 'required|string|max:255',
                'last_name' => 'nullable|string|max:120',
                'species' => 'required|string|max:255',
                'breed' => 'nullable|string|max:255',
                'age' => 'nullable|integer|min:0|max:30',
                'weight' => 'nullable|numeric|min:0|max:200',
                'size' => 'nullable|string|max:30',
                'gender' => 'nullable|string|in:Macho,Hembra',
                'color' => 'nullable|string|max:100',
                'microchip' => 'nullable|string|max:50',
                'identification_type' => 'nullable|string|in:Microchip,Placa,Pasaporte,Otro',
                'identification_number' => 'nullable|string|max:100|required_with:identification_type',
                'temperament' => 'nullable|string|max:255',
                'behavior' => 'nullable|array',
                'allergies' => 'nullable|array',
                'medications' => 'nullable|array',
                'photo' => 'nullable|string|max:500',
                'fallecido' => 'boolean',
                'sterilized' => 'boolean',
                'sterilization_date' => 'nullable|date',
                'birth_date' => 'nullable|date',
                'last_vaccination_date' => 'nullable|date',
                'next_vaccination_date' => 'nullable|date',
                'last_deworming_date' => 'nullable|date',
                'next_deworming_date' => 'nullable|date',
                'insurance_company' => 'nullable|string|max:255',
                'insurance_policy_number' => 'nullable|string|max:100',
                'emergency_contact_name' => 'nullable|string|max:255',
                'emergency_contact_phone' => 'nullable|string|max:20',
                'fecha_registro' => 'nullable|date',
                'fecha_ultima_visita' => 'nullable|date',
                'notes' => 'nullable|string',
                'owner_ids' => 'nullable|array',
                'owner_ids.*' => 'integer|exists:clients,id',
                'photos' => 'nullable|array',
                'photos.*' => 'image|max:2048',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Errores de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $validator->validated();
            if (($data['identification_type'] ?? null) === 'Microchip' && !empty($data['identification_number']) && empty($data['microchip'])) {
                $data['microchip'] = $data['identification_number'];
            }
            if ($duplicate = $this->detectDuplicatePet($data, null)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Posible mascota duplicada detectada',
                    'duplicate' => $duplicate,
                ], 409);
            }
            $ownerIds = $this->normalizeOwnerIds($data['owner_ids'] ?? null, (int) $data['client_id']);
            unset($data['photos']);
            unset($data['owner_ids']);
            $pet = Pet::create($data);
            $this->syncPetOwners($pet, $ownerIds);

            $this->storePetPhotos($pet, $request);

            Log::info("Mascota creada exitosamente", [
                'pet_id' => $pet->id,
                'client_id' => $pet->client_id,
                'name' => $pet->name
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Mascota creada exitosamente',
                'data' => $pet->load(['client:id,razon_social', 'owners:id,razon_social,numero_documento', 'photos'])
            ], 201);

        } catch (Exception $e) {
            Log::error("Error al crear mascota", [
                'request_data' => $request->all(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al crear mascota: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mostrar mascota
     */
    public function show($id): JsonResponse
    {
        try {
            if ($auth = $this->denyWithoutPermission(request(), 'pets.view')) {
                return $auth;
            }
            $pet = Pet::with(['client', 'owners', 'photos', 'appointments', 'medicalRecords', 'vaccineRecords'])
                     ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $pet
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Mascota no encontrada'
            ], 404);
        }
    }

    /**
     * Actualizar mascota
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            if ($auth = $this->denyWithoutPermission($request, 'pets.update')) {
                return $auth;
            }
            $pet = Pet::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'client_id' => 'sometimes|integer|exists:clients,id',
                'name' => 'sometimes|string|max:255',
                'last_name' => 'nullable|string|max:120',
                'species' => 'sometimes|string|max:255',
                'breed' => 'nullable|string|max:255',
                'age' => 'nullable|integer|min:0|max:30',
                'weight' => 'nullable|numeric|min:0|max:200',
                'size' => 'nullable|string|max:30',
                'gender' => 'nullable|string|in:Macho,Hembra',
                'color' => 'nullable|string|max:100',
                'microchip' => 'nullable|string|max:50',
                'identification_type' => 'nullable|string|in:Microchip,Placa,Pasaporte,Otro',
                'identification_number' => 'nullable|string|max:100|required_with:identification_type',
                'temperament' => 'nullable|string|max:255',
                'behavior' => 'nullable|array',
                'allergies' => 'nullable|array',
                'medications' => 'nullable|array',
                'photo' => 'nullable|string|max:500',
                'fallecido' => 'boolean',
                'sterilized' => 'boolean',
                'sterilization_date' => 'nullable|date',
                'birth_date' => 'nullable|date',
                'last_vaccination_date' => 'nullable|date',
                'next_vaccination_date' => 'nullable|date',
                'last_deworming_date' => 'nullable|date',
                'next_deworming_date' => 'nullable|date',
                'insurance_company' => 'nullable|string|max:255',
                'insurance_policy_number' => 'nullable|string|max:100',
                'emergency_contact_name' => 'nullable|string|max:255',
                'emergency_contact_phone' => 'nullable|string|max:20',
                'fecha_registro' => 'nullable|date',
                'fecha_ultima_visita' => 'nullable|date',
                'notes' => 'nullable|string',
                'owner_ids' => 'nullable|array',
                'owner_ids.*' => 'integer|exists:clients,id',
                'default_photo_id' => 'nullable|integer|exists:pet_photos,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Errores de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            $validated = $validator->validated();
            if (($validated['identification_type'] ?? null) === 'Microchip' && !empty($validated['identification_number']) && empty($validated['microchip'])) {
                $validated['microchip'] = $validated['identification_number'];
            }
            if ($duplicate = $this->detectDuplicatePet(array_merge($pet->toArray(), $validated), (int) $pet->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Posible mascota duplicada detectada',
                    'duplicate' => $duplicate,
                ], 409);
            }
            $ownerIdsInput = $validated['owner_ids'] ?? null;
            unset($validated['owner_ids']);
            $pet->update($validated);
            $primaryClientId = array_key_exists('client_id', $validated)
                ? (int) $validated['client_id']
                : (int) $pet->client_id;
            if ($ownerIdsInput !== null || array_key_exists('client_id', $validated)) {
                $this->syncPetOwners($pet, $this->normalizeOwnerIds($ownerIdsInput, $primaryClientId));
            }

            if (array_key_exists('default_photo_id', $validated) && $validated['default_photo_id']) {
                $this->reorderPetPhotos($pet, (int) $validated['default_photo_id'], null, []);
            }

            return response()->json([
                'success' => true,
                'message' => 'Mascota actualizada exitosamente',
                'data' => $pet->load(['client', 'owners', 'photos'])
            ]);

        } catch (Exception $e) {
            Log::error("Error al actualizar mascota", [
                'pet_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar mascota: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar mascota
     */
    public function destroy($id): JsonResponse
    {
        try {
            if ($auth = $this->denyWithoutPermission(request(), 'pets.delete')) {
                return $auth;
            }
            $pet = Pet::findOrFail($id);
            $pet->delete();

            return response()->json([
                'success' => true,
                'message' => 'Mascota eliminada exitosamente'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar mascota'
            ], 500);
        }
    }

    /**
     * Subir fotos de una mascota (máx. 5)
     */
    public function storePhotos(Request $request, $id): JsonResponse
    {
        try {
            if ($auth = $this->denyWithoutPermission($request, 'pets.update')) {
                return $auth;
            }
            $pet = Pet::findOrFail($id);

            $removePhotoIds = $request->input('remove_photo_ids', []);
            $removePhotoIds = is_array($removePhotoIds)
                ? array_values(array_filter(array_map('intval', $removePhotoIds), fn ($v) => $v > 0))
                : [];

            $files = $request->file('photos') ?? $request->file('photos[]');
            $hasFiles = $files && (!is_array($files) || count($files) > 0);
            if (!$hasFiles && empty($removePhotoIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Debe enviar fotos o indicar fotos para eliminar',
                ], 422);
            }

            if (!empty($removePhotoIds)) {
                $photosToDelete = $pet->photos()->whereIn('id', $removePhotoIds)->get();
                foreach ($photosToDelete as $photo) {
                    if (!empty($photo->path) && Storage::disk('public')->exists($photo->path)) {
                        Storage::disk('public')->delete($photo->path);
                    }
                    $photo->delete();
                }
            }

            $files = $hasFiles ? (is_array($files) ? $files : [$files]) : [];
            foreach ($files as $f) {
                if ($f && !$f->isValid()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Uno o más archivos no son válidos (use imagen JPG, PNG, WebP o GIF, máx. 2MB)',
                    ], 422);
                }
            }

            $existingCount = $pet->photos()->count();
            $remaining = Pet::MAX_PHOTOS - $existingCount;
            if ($remaining <= 0 && count($files) > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'La mascota ya tiene el mÃ¡ximo de fotos permitidas',
                ], 422);
            }

            $defaultPhotoId = $request->input('default_photo_id');
            $defaultNewIndex = $request->input('default_new_index');

            $createdPhotos = $this->storePetPhotos($pet, $request, $existingCount, max(0, $remaining));
            $this->reorderPetPhotos(
                $pet,
                $defaultPhotoId ? (int) $defaultPhotoId : null,
                $defaultNewIndex !== null ? (int) $defaultNewIndex : null,
                $createdPhotos
            );

            return response()->json([
                'success' => true,
                'message' => 'Fotos actualizadas',
                'data' => $pet->load('photos')
            ]);
        } catch (Exception $e) {
            Log::error("Error al subir fotos de mascota", ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error al subir fotos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener mascotas por cliente
     */
    public function getByClient($clientId): JsonResponse
    {
        try {
            if ($auth = $this->denyWithoutPermission(request(), 'pets.view')) {
                return $auth;
            }
            $pets = Pet::where('client_id', $clientId)
                      ->orWhereHas('owners', fn ($query) => $query->where('clients.id', $clientId))
                      ->where('fallecido', false)
                      ->with(['vaccineRecords', 'owners'])
                      ->get();

            return response()->json([
                'success' => true,
                'data' => $pets
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener mascotas'
            ], 500);
        }
    }

    /**
     * Guardar fotos de la mascota (máx. 5, al menos se permiten 3)
     */
    public function timeline(Request $request, $id): JsonResponse
    {
        try {
            if ($auth = $this->denyWithoutPermission($request, 'pets.view')) {
                return $auth;
            }
            $pet = Pet::with(['client:id,razon_social', 'owners:id,razon_social'])->findOrFail($id);
            $medical = MedicalRecord::where('pet_id', $pet->id)->orderByDesc('date')->get()->map(fn ($m) => [
                'id' => $m->id,
                'type' => 'medical_record',
                'event_type' => $m->type,
                'title' => $m->title ?: $m->type,
                'description' => $m->description,
                'attachments' => $m->attachments ?? [],
                'occurred_at' => optional($m->date)->toDateString(),
            ]);
            $vaccines = VaccineRecord::where('pet_id', $pet->id)->orderByDesc('date')->get()->map(fn ($v) => [
                'id' => $v->id,
                'type' => 'vaccine',
                'event_type' => $v->vaccine_name ?? 'Vacunación',
                'title' => $v->vaccine_name ?? 'Vacuna',
                'description' => $v->notes ?? null,
                'attachments' => [],
                'occurred_at' => optional($v->date)->toDateString(),
                'next_due_date' => optional($v->next_due_date)->toDateString(),
            ]);
            $appointments = Appointment::where('pet_id', $pet->id)
                ->orderByDesc('date')
                ->orderByDesc('time')
                ->get()
                ->map(function ($a) {
                    $occurredAt = null;
                    if (!empty($a->date)) {
                        $occurredAt = Carbon::parse($a->date)->toDateString();
                    }

                    return [
                        'id' => $a->id,
                        'type' => 'appointment',
                        'event_type' => $a->status ?? 'Cita',
                        'title' => 'Cita',
                        'description' => $a->notes ?? null,
                        'attachments' => [],
                        'occurred_at' => $occurredAt,
                        'status' => $a->status,
                    ];
                });
            $timeline = $medical->concat($vaccines)->concat($appointments)->sortByDesc('occurred_at')->values();
            return response()->json([
                'success' => true,
                'data' => [
                    'pet' => $pet,
                    'timeline' => $timeline,
                    'summary' => [
                        'medical_records' => $medical->count(),
                        'vaccines' => $vaccines->count(),
                        'appointments' => $appointments->count(),
                        'total_events' => $timeline->count(),
                    ],
                ],
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al obtener timeline de mascota: ' . $e->getMessage()], 500);
        }
    }

    public function auditHistory(Request $request, $id): JsonResponse
    {
        try {
            if ($auth = $this->denyWithoutPermission($request, 'pets.view')) {
                return $auth;
            }
            $perPage = max(1, min((int) $request->input('per_page', 25), 100));
            $logs = AuditLog::with('user:id,name,email')
                ->where(function ($q) use ($id) { $q->where('model_type', Pet::class)->where('model_id', $id); })
                ->orWhere(function ($q) use ($id) { $q->where('model_type', 'Pet')->where('model_id', $id); })
                ->orderByDesc('created_at')
                ->paginate($perPage);
            return response()->json([
                'success' => true,
                'data' => $logs->items(),
                'meta' => [
                    'total' => $logs->total(),
                    'per_page' => $logs->perPage(),
                    'current_page' => $logs->currentPage(),
                    'last_page' => $logs->lastPage(),
                ],
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al obtener auditoría de mascota: ' . $e->getMessage()], 500);
        }
    }

    public function reminders(Request $request): JsonResponse
    {
        try {
            if ($auth = $this->denyWithoutPermission($request, 'pets.view')) {
                return $auth;
            }
            $days = max(1, min((int) $request->input('days', 30), 120));
            $today = Carbon::today();
            $to = $today->copy()->addDays($days);
            $pets = Pet::with(['client:id,razon_social', 'owners:id,razon_social'])->where('fallecido', false)->get();
            $items = [];
            foreach ($pets as $pet) {
                $ownerName = $pet->client->razon_social ?? ($pet->owners[0]->razon_social ?? '');
                $push = function (string $kind, ?string $date) use (&$items, $pet, $today, $to, $ownerName) {
                    if (!$date) return;
                    $d = Carbon::parse($date)->startOfDay();
                    if ($kind === 'birthday') {
                        $d = Carbon::create($today->year, (int) $d->month, (int) $d->day)->startOfDay();
                        if ($d->lt($today)) {
                            $d = $d->addYear();
                        }
                    }
                    if ($d->lt($today) || $d->gt($to)) return;
                    $items[] = [
                        'type' => $kind,
                        'pet_id' => $pet->id,
                        'pet_name' => trim(($pet->name ?? '') . ' ' . ($pet->last_name ?? '')),
                        'owner_name' => $ownerName,
                        'due_date' => $d->toDateString(),
                        'days_until' => $today->diffInDays($d, false),
                        'severity' => $today->eq($d) ? 'today' : ($today->diffInDays($d, false) <= 7 ? 'soon' : 'upcoming'),
                    ];
                };
                $push('vaccination', $pet->next_vaccination_date);
                $push('deworming', $pet->next_deworming_date);
                $push('birthday', $pet->birth_date);
            }
            usort($items, fn ($a, $b) => strcmp($a['due_date'], $b['due_date']));
            return response()->json([
                'success' => true,
                'data' => $items,
                'summary' => [
                    'total' => count($items),
                    'today' => count(array_filter($items, fn ($i) => $i['severity'] === 'today')),
                    'soon' => count(array_filter($items, fn ($i) => $i['severity'] === 'soon')),
                ],
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al obtener recordatorios de mascotas: ' . $e->getMessage()], 500);
        }
    }

    public function duplicates(Request $request): JsonResponse
    {
        try {
            if ($auth = $this->denyWithoutPermission($request, 'pets.view')) {
                return $auth;
            }
            $duplicateOwners = Client::selectRaw('LOWER(TRIM(razon_social)) as key_name, COUNT(*) as total')
                ->groupBy('key_name')->having('total', '>', 1)->get()
                ->map(fn ($row) => ['name' => $row->key_name, 'total' => (int) $row->total])->values();
            $duplicatePets = Pet::selectRaw('client_id, LOWER(TRIM(name)) as key_name, LOWER(TRIM(COALESCE(last_name, ""))) as key_last, species, COUNT(*) as total')
                ->groupBy('client_id', 'key_name', 'key_last', 'species')->having('total', '>', 1)->get()
                ->map(fn ($row) => [
                    'client_id' => (int) $row->client_id,
                    'name' => trim($row->key_name . ' ' . $row->key_last),
                    'species' => $row->species,
                    'total' => (int) $row->total,
                ])->values();
            $duplicateSpecies = DB::table('pet_configurations')
                ->selectRaw('LOWER(TRIM(name)) as key_name, COUNT(*) as total')
                ->where('type', 'species')
                ->groupBy('key_name')
                ->having('total', '>', 1)
                ->get();
            $duplicateBreeds = DB::table('pet_configurations')
                ->selectRaw('type, LOWER(TRIM(name)) as key_name, COUNT(*) as total')
                ->where(function ($q) {
                    $q->whereIn('type', ['dog_breed', 'cat_breed'])->orWhere('type', 'like', 'breed_%');
                })
                ->groupBy('type', 'key_name')
                ->having('total', '>', 1)
                ->get();
            return response()->json([
                'success' => true,
                'data' => [
                    'owners' => $duplicateOwners,
                    'pets' => $duplicatePets,
                    'species' => $duplicateSpecies,
                    'breeds' => $duplicateBreeds,
                ],
                'summary' => [
                    'owners' => $duplicateOwners->count(),
                    'pets' => $duplicatePets->count(),
                    'species' => count($duplicateSpecies),
                    'breeds' => count($duplicateBreeds),
                ],
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al generar reporte de duplicados: ' . $e->getMessage()], 500);
        }
    }

    private function storePetPhotos(Pet $pet, Request $request, int $startOrder = 0, ?int $limit = null): array
    {
        $files = $request->file('photos') ?? $request->file('photos[]');
        if (!$files) {
            return [];
        }

        if (!is_array($files)) {
            $files = [$files];
        }

        $limit = $limit ?? Pet::MAX_PHOTOS;
        $files = array_slice($files, 0, $limit);
        $disk = 'public';
        $basePath = 'pet_photos/' . $pet->id;
        $created = [];

        foreach ($files as $index => $file) {
            $path = $file->store($basePath, $disk);
            $created[] = PetPhoto::create([
                'pet_id' => $pet->id,
                'path' => $path,
                'sort_order' => $startOrder + $index,
            ]);
        }
        return $created;
    }

    private function reorderPetPhotos(Pet $pet, ?int $defaultPhotoId, ?int $defaultNewIndex, array $createdPhotos = []): void
    {
        $photos = $pet->photos()->orderBy('sort_order')->get();
        if ($photos->isEmpty()) {
            return;
        }

        $defaultPhoto = null;
        if ($defaultNewIndex !== null && isset($createdPhotos[$defaultNewIndex])) {
            $defaultPhoto = $createdPhotos[$defaultNewIndex];
        }
        if (!$defaultPhoto && $defaultPhotoId) {
            $defaultPhoto = $photos->firstWhere('id', $defaultPhotoId);
        }
        if (!$defaultPhoto) {
            return;
        }

        $ordered = $photos->filter(fn ($photo) => $photo->id !== $defaultPhoto->id)->values();
        $ordered->prepend($defaultPhoto);
        foreach ($ordered as $i => $photo) {
            if ($photo->sort_order !== $i) {
                $photo->sort_order = $i;
                $photo->save();
            }
        }
    }

    private function denyWithoutPermission(Request $request, string $permission): ?JsonResponse
    {
        $user = $request->user();
        if (!$user || !method_exists($user, 'hasPermission')) {
            return null;
        }
        if (empty($user->role_id)) {
            return null;
        }
        $allowed = $user->hasRole('super_admin')
            || $user->hasPermission($permission)
            || $user->hasPermission('pets.manage')
            || $user->hasPermission('system.manage')
            || $user->hasPermission('companies.manage');
        if ($allowed) {
            return null;
        }
        return response()->json([
            'success' => false,
            'message' => 'No autorizado para esta acción',
        ], 403);
    }

    private function detectDuplicatePet(array $data, ?int $ignorePetId = null): ?array
    {
        $clientId = (int) ($data['client_id'] ?? 0);
        $species = trim((string) ($data['species'] ?? ''));
        $name = mb_strtolower(trim((string) ($data['name'] ?? '')));
        $lastName = mb_strtolower(trim((string) ($data['last_name'] ?? '')));
        $birthDate = $data['birth_date'] ?? null;
        $microchip = trim((string) ($data['microchip'] ?? ''));
        $identification = trim((string) ($data['identification_number'] ?? ''));

        if ($microchip !== '') {
            $chipQuery = Pet::query()->where('microchip', $microchip);
            if ($ignorePetId) {
                $chipQuery->where('id', '!=', $ignorePetId);
            }
            $chip = $chipQuery->first();
            if ($chip) {
                return ['reason' => 'microchip', 'pet_id' => $chip->id];
            }
        }

        if ($identification !== '') {
            $idQuery = Pet::query()->where('identification_number', $identification);
            if ($ignorePetId) {
                $idQuery->where('id', '!=', $ignorePetId);
            }
            $byId = $idQuery->first();
            if ($byId) {
                return ['reason' => 'identification_number', 'pet_id' => $byId->id];
            }
        }

        if ($clientId > 0 && $species !== '' && $name !== '') {
            $nameQuery = Pet::query()
                ->where('client_id', $clientId)
                ->whereRaw('LOWER(TRIM(name)) = ?', [$name])
                ->whereRaw('LOWER(TRIM(COALESCE(last_name, ""))) = ?', [$lastName])
                ->where('species', $species);
            if ($birthDate) {
                $nameQuery->whereDate('birth_date', $birthDate);
            }
            if ($ignorePetId) {
                $nameQuery->where('id', '!=', $ignorePetId);
            }
            $sameName = $nameQuery->first();
            if ($sameName) {
                return ['reason' => 'name_species_owner', 'pet_id' => $sameName->id];
            }
        }

        return null;
    }

    private function normalizeOwnerIds(?array $ownerIds, int $clientId): array
    {
        $ids = array_filter(array_map('intval', $ownerIds ?? []), fn ($id) => $id > 0);
        if (!in_array($clientId, $ids, true)) {
            $ids[] = $clientId;
        }
        return array_values(array_unique($ids));
    }

    private function syncPetOwners(Pet $pet, array $ownerIds): void
    {
        if (empty($ownerIds)) {
            return;
        }
        $pet->owners()->sync($ownerIds);
    }
}
