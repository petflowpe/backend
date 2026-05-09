<?php

namespace App\Http\Controllers\Api\V2;

use App\Helpers\ScopeHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\V2\ClientResource;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Pet;
use App\Models\Zone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Exception;

class ClientController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Client::class);

        try {
            $query = Client::query()
                ->withCount('pets');

            $companyId = ScopeHelper::companyId($request);
            if ($companyId !== null) {
                $query->where('company_id', $companyId);
            }

            if ($request->filled('status')) {
                $status = mb_strtolower((string) $request->input('status'));
                if (in_array($status, ['activo', 'inactivo'], true)) {
                    $query->where('activo', $status === 'activo');
                }
            }

            if ($request->filled('zoneId')) {
                $query->where('zone_id', (int) $request->input('zoneId'));
            }

            if ($request->filled('search')) {
                $search = trim((string) $request->input('search'));
                $query->where(function ($q) use ($search) {
                    $q->where('razon_social', 'like', "%{$search}%")
                        ->orWhere('numero_documento', 'like', "%{$search}%")
                        ->orWhere('nombre_comercial', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('telefono', 'like', "%{$search}%")
                        ->orWhereHas('pets', function ($petsQ) use ($search) {
                            $petsQ->where('name', 'like', "%{$search}%");
                        });
                });
            }

            $orderBy = (string) $request->input('orderBy', 'fullName');
            $orderDir = strtolower((string) $request->input('orderDir', 'asc')) === 'desc' ? 'desc' : 'asc';
            $orderMap = [
                'fullName' => 'razon_social',
                'documentNumber' => 'numero_documento',
                'createdAt' => 'created_at',
                'updatedAt' => 'updated_at',
            ];
            $query->orderBy($orderMap[$orderBy] ?? 'razon_social', $orderDir);

            $perPage = (int) $request->input('perPage', 15);
            $perPage = max(1, min($perPage, 100));
            $paginator = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                // Evitar doble wrapper "data.data" de Resources dentro de response()->json()
                'data' => $paginator->getCollection()
                    ->map(fn ($client) => (new ClientResource($client))->resolve())
                    ->values()
                    ->all(),
                'meta' => [
                    'total' => $paginator->total(),
                    'per_page' => $paginator->perPage(),
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                ],
            ]);
        } catch (Exception $e) {
            Log::error('v2 clients index error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error al listar clientes',
            ], 500);
        }
    }

    public function show(Request $request, Client $client): JsonResponse
    {
        $this->authorize('view', $client);

        try {
            $companyId = ScopeHelper::companyId($request);
            if ($companyId !== null && (int) $client->company_id !== (int) $companyId && !$request->user()?->hasRole('super_admin')) {
                return response()->json(['success' => false, 'message' => 'No autorizado'], 403);
            }

            $client->load([
                'pets' => function ($q) {
                    $q->withMax('appointments', 'date');
                },
            ]);

            $lastInvoices = Invoice::query()
                ->where('client_id', $client->id)
                ->orderBy('fecha_emision', 'desc')
                ->limit(5)
                ->get();

            $nextAppointment = Appointment::query()
                ->where('client_id', $client->id)
                ->whereIn('status', ['Pendiente', 'Confirmada', 'En Proceso'])
                ->where(function ($q) {
                    $q->whereDate('date', '>', now()->toDateString())
                        ->orWhere(function ($inner) {
                            $inner->whereDate('date', now()->toDateString())
                                ->where('time', '>=', now()->format('H:i:s'));
                        });
                })
                ->orderBy('date', 'asc')
                ->orderBy('time', 'asc')
                ->first();

            $client->setRelation('lastInvoices', $lastInvoices);
            $client->setRelation('nextAppointment', $nextAppointment);

            return response()->json([
                'success' => true,
                'data' => new ClientResource($client),
            ]);
        } catch (Exception $e) {
            Log::error('v2 clients show error', ['error' => $e->getMessage(), 'client_id' => $client->id]);
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener cliente',
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Client::class);

        $validator = Validator::make($request->all(), [
            'fullName' => ['required', 'string', 'max:255'],
            'commercialName' => ['nullable', 'string', 'max:255'],
            'documentType' => ['required', 'string', Rule::in(['DNI', 'RUC', 'CE', 'Pasaporte'])],
            'documentNumber' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'phone2' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
            'ubigeo' => ['nullable', 'string', 'size:6'],
            'zoneId' => ['nullable', 'integer', 'exists:zones,id'],
            'clientType' => ['nullable', 'string', Rule::in(['Regular', 'VIP', 'Moroso', 'Problemático', 'Empleado'])],
            'status' => ['nullable', 'string', Rule::in(['Activo', 'Inactivo'])],
            'notes' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $data = $validator->validated();

            $companyId = ScopeHelper::companyId($request);
            if ($request->user()?->hasRole('super_admin') && $request->filled('companyId')) {
                $companyId = (int) $request->input('companyId');
            }

            DB::beginTransaction();

            $client = Client::create([
                'company_id' => $companyId,
                'zone_id' => $this->resolveZoneId($companyId, $data['district'] ?? null, $data['zoneId'] ?? null),
                'client_type' => $data['clientType'] ?? 'Regular',
                'tipo_documento' => $this->mapDocumentTypeToCode($data['documentType']),
                'numero_documento' => $data['documentNumber'],
                'razon_social' => $data['fullName'],
                'nombre_comercial' => $data['commercialName'] ?? null,
                'email' => $data['email'] ?? null,
                'telefono' => $data['phone'] ?? null,
                'telefono2' => $data['phone2'] ?? null,
                'direccion' => $data['address'] ?? null,
                'distrito' => $data['district'] ?? null,
                'provincia' => $data['province'] ?? null,
                'departamento' => $data['department'] ?? null,
                'ubigeo' => $data['ubigeo'] ?? null,
                'notas' => $data['notes'] ?? null,
                'activo' => ($data['status'] ?? 'Activo') === 'Activo',
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => new ClientResource($client->load('pets')),
                'message' => 'Cliente creado exitosamente',
            ], 201);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('v2 clients store error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error al crear cliente',
            ], 500);
        }
    }

    public function update(Request $request, Client $client): JsonResponse
    {
        $this->authorize('update', $client);

        $validator = Validator::make($request->all(), [
            'fullName' => ['sometimes', 'required', 'string', 'max:255'],
            'commercialName' => ['nullable', 'string', 'max:255'],
            'documentType' => ['sometimes', 'required', 'string', Rule::in(['DNI', 'RUC', 'CE', 'Pasaporte'])],
            'documentNumber' => ['sometimes', 'required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'phone2' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
            'ubigeo' => ['nullable', 'string', 'size:6'],
            'zoneId' => ['nullable', 'integer', 'exists:zones,id'],
            'clientType' => ['nullable', 'string', Rule::in(['Regular', 'VIP', 'Moroso', 'Problemático', 'Empleado'])],
            'status' => ['nullable', 'string', Rule::in(['Activo', 'Inactivo'])],
            'notes' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $data = $validator->validated();
            $companyId = ScopeHelper::companyId($request);

            $update = [];
            if (array_key_exists('fullName', $data)) {
                $update['razon_social'] = $data['fullName'];
            }
            if (array_key_exists('commercialName', $data)) {
                $update['nombre_comercial'] = $data['commercialName'];
            }
            if (array_key_exists('documentType', $data)) {
                $update['tipo_documento'] = $this->mapDocumentTypeToCode($data['documentType']);
            }
            if (array_key_exists('documentNumber', $data)) {
                $update['numero_documento'] = $data['documentNumber'];
            }
            if (array_key_exists('email', $data)) {
                $update['email'] = $data['email'];
            }
            if (array_key_exists('phone', $data)) {
                $update['telefono'] = $data['phone'];
            }
            if (array_key_exists('phone2', $data)) {
                $update['telefono2'] = $data['phone2'];
            }
            if (array_key_exists('address', $data)) {
                $update['direccion'] = $data['address'];
            }
            if (array_key_exists('district', $data)) {
                $update['distrito'] = $data['district'];
            }
            if (array_key_exists('province', $data)) {
                $update['provincia'] = $data['province'];
            }
            if (array_key_exists('department', $data)) {
                $update['departamento'] = $data['department'];
            }
            if (array_key_exists('ubigeo', $data)) {
                $update['ubigeo'] = $data['ubigeo'];
            }
            if (array_key_exists('notes', $data)) {
                $update['notas'] = $data['notes'];
            }
            if (array_key_exists('clientType', $data)) {
                $update['client_type'] = $data['clientType'];
            }
            if (array_key_exists('status', $data)) {
                $update['activo'] = $data['status'] === 'Activo';
            }

            if (array_key_exists('zoneId', $data) || array_key_exists('district', $data)) {
                $district = array_key_exists('district', $data) ? $data['district'] : $client->distrito;
                $zoneId = array_key_exists('zoneId', $data) ? $data['zoneId'] : null;
                $update['zone_id'] = $this->resolveZoneId($companyId, $district, $zoneId);
            }

            $client->update($update);

            return response()->json([
                'success' => true,
                'data' => new ClientResource($client->fresh()->load('pets')),
                'message' => 'Cliente actualizado exitosamente',
            ]);
        } catch (Exception $e) {
            Log::error('v2 clients update error', ['error' => $e->getMessage(), 'client_id' => $client->id]);
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar cliente',
            ], 500);
        }
    }

    public function destroy(Client $client): JsonResponse
    {
        $this->authorize('delete', $client);

        try {
            $client->update(['activo' => false]);
            return response()->json([
                'success' => true,
                'message' => 'Cliente desactivado exitosamente',
            ]);
        } catch (Exception $e) {
            Log::error('v2 clients destroy error', ['error' => $e->getMessage(), 'client_id' => $client->id]);
            return response()->json([
                'success' => false,
                'message' => 'Error al desactivar cliente',
            ], 500);
        }
    }

    public function addPet(Request $request, Client $client): JsonResponse
    {
        $this->authorize('update', $client);

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'species' => ['required', 'string', Rule::in(['Perro', 'Gato', 'Exótico'])],
            'breed' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', 'string', Rule::in(['Macho', 'Hembra'])],
            'birthDate' => ['nullable', 'date'],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:200'],
            'medicalNotes' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $data = $validator->validated();

            $pet = Pet::create([
                'client_id' => $client->id,
                'company_id' => $client->company_id,
                'name' => $data['name'],
                'species' => $data['species'] === 'Exótico' ? 'Otro' : $data['species'],
                'breed' => $data['breed'] ?? null,
                'gender' => $data['gender'] ?? null,
                'birth_date' => $data['birthDate'] ?? null,
                'weight' => $data['weight'] ?? null,
                'notes' => $data['medicalNotes'] ?? null,
                'fallecido' => false,
            ]);

            return response()->json([
                'success' => true,
                'data' => (new ClientResource($client->fresh()->load('pets'))),
                'message' => 'Mascota agregada exitosamente',
            ], 201);
        } catch (Exception $e) {
            Log::error('v2 clients addPet error', ['error' => $e->getMessage(), 'client_id' => $client->id]);
            return response()->json([
                'success' => false,
                'message' => 'Error al agregar mascota',
            ], 500);
        }
    }

    private function mapDocumentTypeToCode(string $documentType): string
    {
        return match ($documentType) {
            'DNI' => '1',
            'CE' => '4',
            'RUC' => '6',
            'Pasaporte' => '7',
            default => '0',
        };
    }

    private function resolveZoneId(?int $companyId, ?string $district, ?int $explicitZoneId): ?int
    {
        if ($explicitZoneId) {
            if ($companyId) {
                $ok = Zone::where('id', $explicitZoneId)->where('company_id', $companyId)->exists();
                return $ok ? $explicitZoneId : null;
            }
            return $explicitZoneId;
        }

        if (!$companyId || !$district) {
            return null;
        }

        $zone = Zone::query()
            ->where('company_id', $companyId)
            ->where('active', true)
            ->whereJsonContains('districts', $district)
            ->first();

        return $zone?->id;
    }
}

