<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\RespondsWithPagination;
use App\Helpers\ScopeHelper;
use App\Models\Client;
use App\Models\Company;
use App\Models\Pet;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Exception;

class ClientController extends Controller
{
    use RespondsWithPagination;
    /**
     * Listar clientes
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Client::with(['company:id,ruc,razon_social']);

            $companyId = ScopeHelper::companyId($request) ?? $request->get('company_id');
            if ($companyId !== null) {
                $query->where('company_id', $companyId);
            }

            // Filtrar por tipo de documento
            if ($request->has('tipo_documento')) {
                $query->where('tipo_documento', $request->tipo_documento);
            }

            // Búsqueda por texto
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('numero_documento', 'like', "%{$search}%")
                      ->orWhere('razon_social', 'like', "%{$search}%")
                      ->orWhere('nombre_comercial', 'like', "%{$search}%");
                });
            }

            $perPage = $request->integer('per_page', 15);
            $clients = $query->paginate(min(max($perPage, 1), 100));

            return $this->paginatedResponse($clients);

        } catch (Exception $e) {
            Log::error("Error al listar clientes", [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener clientes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear nuevo cliente
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $rules = [
                'company_id' => 'nullable|integer|exists:companies,id',
                'tipo_documento' => 'required|string|in:1,4,6,7,0', // DNI, CE, RUC, PAS, SIN DOC
                'numero_documento' => 'required|string|max:20',
                'razon_social' => 'required|string|max:255',
                'nombre_comercial' => 'nullable|string|max:255',
                'direccion' => 'nullable|string|max:255',
                'ubigeo' => 'nullable|string|size:6',
                'distrito' => 'nullable|string|max:100',
                'provincia' => 'nullable|string|max:100',
                'departamento' => 'nullable|string|max:100',
                'zona_preferida' => 'nullable|string|max:100',
                'telefono' => 'nullable|string|max:20',
                'telefono2' => 'nullable|string|max:20',
                'email' => 'nullable|email|max:255',
                'fecha_nacimiento' => 'nullable|date',
                'genero' => 'nullable|string|in:Masculino,Femenino,Otro',
                'notas' => 'nullable|string',
                'preferencias_contacto' => 'nullable|array',
                'puntos_fidelizacion' => 'nullable|integer|min:0',
                'nivel_fidelizacion' => 'nullable|string|in:Bronce,Plata,Oro,VIP',
                'fecha_ultima_visita' => 'nullable|date',
                'fecha_registro' => 'nullable|date',
                'activo' => 'boolean',
                'pets' => 'nullable|array',
                'pets.*.name' => 'required|string|max:255',
                'pets.*.species' => 'required|string|in:Perro,Gato,Otro',
                'pets.*.breed' => 'nullable|string|max:255',
                'pets.*.age' => 'nullable|integer|min:0|max:30',
                'pets.*.weight' => 'nullable|numeric|min:0|max:200',
                'pets.*.size' => 'nullable|string|in:Pequeño,Mediano,Grande,Gigante',
                'pets.*.gender' => 'nullable|string|in:Macho,Hembra',
                'pets.*.color' => 'nullable|string|max:100',
                'pets.*.microchip' => 'nullable|string|max:50',
                'pets.*.temperament' => 'nullable|string|max:255',
                'pets.*.behavior' => 'nullable|array',
                'pets.*.allergies' => 'nullable|array',
                'pets.*.medications' => 'nullable|array',
                'pets.*.notes' => 'nullable|string',
                'pets.*.sterilized' => 'nullable|boolean',
                'pets.*.birth_date' => 'nullable|date',
                'pets.*.sterilization_date' => 'nullable|date',
                'pets.*.last_vaccination_date' => 'nullable|date',
                'pets.*.next_vaccination_date' => 'nullable|date',
                'pets.*.last_deworming_date' => 'nullable|date',
                'pets.*.next_deworming_date' => 'nullable|date',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Errores de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $validator->validated();
            $petsInput = $data['pets'] ?? null;
            unset($data['pets']);

            if (empty($data['company_id'])) {
                $data['company_id'] = 1;
            }
            $companyId = $data['company_id'];

            // Si se proporciona company_id, verificar que la empresa existe y está activa
            if ($companyId) {
                $company = Company::where('id', $companyId)
                                 ->where('activo', true)
                                 ->first();

                if (!$company) {
                    return response()->json([
                        'success' => false,
                        'message' => 'La empresa especificada no existe o está inactiva'
                    ], 404);
                }
            }

            // Verificar que no exista otro cliente con el mismo documento
            $existingClientQuery = Client::where('tipo_documento', $request->tipo_documento)
                                        ->where('numero_documento', $request->numero_documento);

            if ($companyId) {
                $existingClientQuery->where('company_id', $companyId);
            } else {
                $existingClientQuery->whereNull('company_id');
            }

            $existingClient = $existingClientQuery->first();

            if ($existingClient) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya existe un cliente con el mismo tipo y número de documento' . ($companyId ? ' en esta empresa' : '')
                ], 400);
            }

            // Detección preventiva de duplicados de amos por nombre + contacto
            $normalizedName = mb_strtolower(trim((string) ($data['razon_social'] ?? '')));
            $phone = trim((string) ($data['telefono'] ?? ''));
            $email = mb_strtolower(trim((string) ($data['email'] ?? '')));
            if ($normalizedName !== '') {
                $dupByIdentity = Client::query()
                    ->whereRaw('LOWER(TRIM(razon_social)) = ?', [$normalizedName])
                    ->when($companyId, fn ($q) => $q->where('company_id', $companyId), fn ($q) => $q->whereNull('company_id'))
                    ->when($phone !== '' || $email !== '', function ($q) use ($phone, $email) {
                        $q->where(function ($inner) use ($phone, $email) {
                            if ($phone !== '') {
                                $inner->orWhere('telefono', $phone)->orWhere('telefono2', $phone);
                            }
                            if ($email !== '') {
                                $inner->orWhereRaw('LOWER(TRIM(email)) = ?', [$email]);
                            }
                        });
                    })
                    ->first();
                if ($dupByIdentity) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Posible amo duplicado detectado (mismo nombre y datos de contacto)',
                        'duplicate_of' => $dupByIdentity->id,
                    ], 409);
                }
            }

            DB::beginTransaction();
            try {
                $client = Client::create($data);

                // Crear mascotas asociadas si se enviaron
                if (!empty($petsInput) && is_array($petsInput)) {
                    foreach ($petsInput as $petRow) {
                        $petData = [
                            'client_id' => $client->id,
                            'company_id' => $client->company_id,
                            'name' => $petRow['name'],
                            'species' => $petRow['species'],
                            'breed' => $petRow['breed'] ?? null,
                            'age' => $petRow['age'] ?? null,
                            'weight' => $petRow['weight'] ?? null,
                            'size' => $petRow['size'] ?? null,
                            'gender' => $petRow['gender'] ?? null,
                            'color' => $petRow['color'] ?? null,
                            'microchip' => $petRow['microchip'] ?? null,
                            'temperament' => $petRow['temperament'] ?? null,
                            'behavior' => $petRow['behavior'] ?? null,
                            'allergies' => $petRow['allergies'] ?? null,
                            'medications' => $petRow['medications'] ?? null,
                            'notes' => $petRow['notes'] ?? null,
                            'fallecido' => false,
                            'sterilized' => $petRow['sterilized'] ?? false,
                            'birth_date' => $petRow['birth_date'] ?? null,
                            'sterilization_date' => $petRow['sterilization_date'] ?? null,
                            'last_vaccination_date' => $petRow['last_vaccination_date'] ?? null,
                            'next_vaccination_date' => $petRow['next_vaccination_date'] ?? null,
                            'last_deworming_date' => $petRow['last_deworming_date'] ?? null,
                            'next_deworming_date' => $petRow['next_deworming_date'] ?? null,
                        ];
                        Pet::create($petData);
                    }
                }

                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }

            $client->recalculateLevel();

            Log::info("Cliente creado exitosamente", [
                'client_id' => $client->id,
                'company_id' => $client->company_id,
                'numero_documento' => $client->numero_documento,
                'razon_social' => $client->razon_social,
                'pets_count' => count($petsInput ?? []),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cliente creado exitosamente',
                'data' => $client->load(['company:id,ruc,razon_social', 'pets'])
            ], 201);

        } catch (Exception $e) {
            Log::error("Error al crear cliente", [
                'request_data' => $request->all(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al crear cliente: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener cliente específico
     */
    public function show(Client $client): JsonResponse
    {
        $this->authorize('view', $client);
        try {
            $client->load(['company:id,ruc,razon_social,nombre_comercial']);

            return response()->json([
                'success' => true,
                'data' => $client
            ]);

        } catch (Exception $e) {
            Log::error("Error al obtener cliente", [
                'client_id' => $client->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener cliente: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar cliente
     */
    public function update(Request $request, Client $client): JsonResponse
    {
        $this->authorize('update', $client);
        try {
            $validator = Validator::make($request->all(), [
                'company_id' => 'nullable|integer|exists:companies,id',
                'tipo_documento' => 'required|string|in:1,4,6,7,0',
                'numero_documento' => 'required|string|max:20',
                'razon_social' => 'required|string|max:255',
                'nombre_comercial' => 'nullable|string|max:255',
                'direccion' => 'nullable|string|max:255',
                'ubigeo' => 'nullable|string|size:6',
                'distrito' => 'nullable|string|max:100',
                'provincia' => 'nullable|string|max:100',
                'departamento' => 'nullable|string|max:100',
                'zona_preferida' => 'nullable|string|max:100',
                'telefono' => 'nullable|string|max:20',
                'telefono2' => 'nullable|string|max:20',
                'email' => 'nullable|email|max:255',
                'fecha_nacimiento' => 'nullable|date',
                'genero' => 'nullable|string|in:Masculino,Femenino,Otro',
                'notas' => 'nullable|string',
                'preferencias_contacto' => 'nullable|array',
                'puntos_fidelizacion' => 'nullable|integer|min:0',
                'nivel_fidelizacion' => 'nullable|string|in:Bronce,Plata,Oro,VIP',
                'fecha_ultima_visita' => 'nullable|date',
                'fecha_registro' => 'nullable|date',
                'activo' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Errores de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Verificar que no exista otro cliente con el mismo documento
            $companyId = $request->company_id;
            $existingClientQuery = Client::where('tipo_documento', $request->tipo_documento)
                                        ->where('numero_documento', $request->numero_documento)
                                        ->where('id', '!=', $client->id);
            
            if ($companyId) {
                $existingClientQuery->where('company_id', $companyId);
            } else {
                $existingClientQuery->whereNull('company_id');
            }
            
            $existingClient = $existingClientQuery->first();

            if ($existingClient) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya existe otro cliente con el mismo tipo y número de documento' . ($companyId ? ' en esta empresa' : '')
                ], 400);
            }

            $normalizedName = mb_strtolower(trim((string) $request->razon_social));
            $phone = trim((string) ($request->telefono ?? ''));
            $email = mb_strtolower(trim((string) ($request->email ?? '')));
            if ($normalizedName !== '') {
                $dupByIdentity = Client::query()
                    ->where('id', '!=', $client->id)
                    ->whereRaw('LOWER(TRIM(razon_social)) = ?', [$normalizedName])
                    ->when($companyId, fn ($q) => $q->where('company_id', $companyId), fn ($q) => $q->whereNull('company_id'))
                    ->when($phone !== '' || $email !== '', function ($q) use ($phone, $email) {
                        $q->where(function ($inner) use ($phone, $email) {
                            if ($phone !== '') {
                                $inner->orWhere('telefono', $phone)->orWhere('telefono2', $phone);
                            }
                            if ($email !== '') {
                                $inner->orWhereRaw('LOWER(TRIM(email)) = ?', [$email]);
                            }
                        });
                    })
                    ->first();
                if ($dupByIdentity) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Posible amo duplicado detectado (mismo nombre y datos de contacto)',
                        'duplicate_of' => $dupByIdentity->id,
                    ], 409);
                }
            }

            $client->update($validator->validated());

            Log::info("Cliente actualizado exitosamente", [
                'client_id' => $client->id,
                'company_id' => $client->company_id,
                'changes' => $client->getChanges()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cliente actualizado exitosamente',
                'data' => $client->fresh()->load('company:id,ruc,razon_social')
            ]);

        } catch (Exception $e) {
            Log::error("Error al actualizar cliente", [
                'client_id' => $client->id,
                'request_data' => $request->all(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar cliente: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar cliente (soft delete - marcar como inactivo)
     */
    public function destroy(Client $client): JsonResponse
    {
        $this->authorize('delete', $client);
        try {
            // Verificar si el cliente tiene documentos asociados
            $hasDocuments = false; // Podrías implementar estas verificaciones si es necesario
            // $hasDocuments = $client->invoices()->count() > 0 ||
            //                $client->boletas()->count() > 0 ||
            //                $client->dispatchGuides()->count() > 0;

            if ($hasDocuments) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar el cliente porque tiene documentos asociados. Considere desactivarlo en su lugar.'
                ], 400);
            }

            // Marcar como inactivo en lugar de eliminar
            $client->update(['activo' => false]);

            Log::warning("Cliente desactivado", [
                'client_id' => $client->id,
                'numero_documento' => $client->numero_documento,
                'razon_social' => $client->razon_social
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cliente desactivado exitosamente'
            ]);

        } catch (Exception $e) {
            Log::error("Error al desactivar cliente", [
                'client_id' => $client->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al desactivar cliente: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Activar cliente
     */
    public function activate(Client $client): JsonResponse
    {
        try {
            $client->update(['activo' => true]);

            Log::info("Cliente activado", [
                'client_id' => $client->id,
                'numero_documento' => $client->numero_documento,
                'razon_social' => $client->razon_social
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cliente activado exitosamente',
                'data' => $client->load('company:id,ruc,razon_social')
            ]);

        } catch (Exception $e) {
            Log::error("Error al activar cliente", [
                'client_id' => $client->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al activar cliente: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener clientes de una empresa específica
     */
    public function getByCompany(Company $company): JsonResponse
    {
        try {
            $clients = $company->clients()
                             ->select([
                                 'id', 'company_id', 'tipo_documento', 'numero_documento',
                                 'razon_social', 'nombre_comercial', 'direccion',
                                 'distrito', 'provincia', 'departamento',
                                 'telefono', 'email', 'activo',
                                 'created_at', 'updated_at'
                             ])
                             ->orderBy('razon_social')
                             ->paginate(50);

            return response()->json([
                'success' => true,
                'data' => $clients->items(),
                'meta' => [
                    'company_id' => $company->id,
                    'company_name' => $company->razon_social,
                    'total' => $clients->total(),
                    'per_page' => $clients->perPage(),
                    'current_page' => $clients->currentPage(),
                    'last_page' => $clients->lastPage()
                ]
            ]);

        } catch (Exception $e) {
            Log::error("Error al obtener clientes por empresa", [
                'company_id' => $company->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener clientes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Buscar cliente por número de documento
     */
    public function searchByDocument(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'company_id' => 'required|integer|exists:companies,id',
                'tipo_documento' => 'required|string|in:1,4,6,7,0',
                'numero_documento' => 'required|string|max:20'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Errores de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            $client = Client::where('company_id', $request->company_id)
                           ->where('tipo_documento', $request->tipo_documento)
                           ->where('numero_documento', $request->numero_documento)
                           ->where('activo', true)
                           ->with('company:id,ruc,razon_social')
                           ->first();

            if (!$client) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cliente no encontrado'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $client
            ]);

        } catch (Exception $e) {
            Log::error("Error al buscar cliente por documento", [
                'request_data' => $request->all(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al buscar cliente: ' . $e->getMessage()
            ], 500);
        }
    }
}
