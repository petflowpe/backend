<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Helpers\ScopeHelper;
use App\Models\MedicalRecord;
use App\Models\Pet;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Exception;

class MedicalRecordController extends Controller
{
    /**
     * Listar registros médicos (siempre acotados al scope de empresa).
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = MedicalRecord::with(['pet', 'client', 'user', 'appointment']);
            $this->applyCompanyFilter($query, $request);

            $routePetId = $request->route('petId');
            $routeClientId = $request->route('clientId');

            if ($routePetId) {
                $query->where('pet_id', $routePetId);
            } elseif ($request->filled('pet_id')) {
                $query->where('pet_id', $request->pet_id);
            }

            if ($routeClientId) {
                $query->where('client_id', $routeClientId);
            } elseif ($request->filled('client_id')) {
                $query->where('client_id', $request->client_id);
            }

            if ($request->filled('type')) {
                $query->where('type', $request->type);
            }

            if ($request->filled('date_from')) {
                $query->whereDate('date', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $query->whereDate('date', '<=', $request->date_to);
            }

            $records = $query->orderBy('date', 'desc')
                ->paginate($request->integer('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => $records->items(),
                'meta' => [
                    'total' => $records->total(),
                    'per_page' => $records->perPage(),
                    'current_page' => $records->currentPage(),
                    'last_page' => $records->lastPage(),
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Error al listar registros médicos', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener registros médicos: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Crear registro médico
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'pet_id' => 'required|integer|exists:pets,id',
                'client_id' => 'required|integer|exists:clients,id',
                'company_id' => 'nullable|integer|exists:companies,id',
                'appointment_id' => 'nullable|integer|exists:appointments,id',
                'user_id' => 'nullable|integer|exists:users,id',
                'date' => 'required|date',
                'type' => 'required|string|in:Consulta,Vacunación,Cirugía,Emergencia,Chequeo,Laboratorio,Desparasitación,Tratamiento',
                'title' => 'nullable|string|max:255',
                'description' => 'required|string',
                'diagnosis' => 'nullable|string',
                'treatment' => 'nullable|string',
                'prescription' => 'nullable|array',
                'attachments' => 'nullable|array',
                'weight' => 'nullable|numeric|min:0|max:200',
                'temperature' => 'nullable|numeric|min:30|max:45',
                'vital_signs' => 'nullable|array',
                'notes' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Errores de validación',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $data = $validator->validated();
            $pet = Pet::with('client:id,company_id')->find($data['pet_id']);
            if (!$pet) {
                return response()->json([
                    'success' => false,
                    'message' => 'No autorizado: la mascota no pertenece a su empresa o no existe',
                ], 403);
            }

            $scopeCompanyId = ScopeHelper::companyId($request);
            $resolvedCompanyId = $scopeCompanyId
                ?? $pet->company_id
                ?? $pet->client?->company_id
                ?? ($data['company_id'] ?? null);

            if (!$resolvedCompanyId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo determinar la empresa del registro médico',
                ], 422);
            }

            // Defensa multiempresa: la mascota debe pertenecer al scope.
            $petCompanyId = (int) ($pet->company_id ?? $pet->client?->company_id ?? 0);
            if ($scopeCompanyId && $petCompanyId && $petCompanyId !== (int) $scopeCompanyId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No autorizado: la mascota no pertenece a su empresa',
                ], 403);
            }

            if ((int) ($data['client_id'] ?? 0) !== (int) $pet->client_id && $pet->client_id) {
                // Mantener consistencia pet→client si el cliente enviado no coincide.
                $data['client_id'] = $pet->client_id;
            }

            $data['company_id'] = (int) $resolvedCompanyId;
            $data['user_id'] = $data['user_id'] ?? $request->user()?->id;

            $record = MedicalRecord::create($data);

            return response()->json([
                'success' => true,
                'message' => 'Registro médico creado exitosamente',
                'data' => $record->load(['pet', 'client', 'user']),
            ], 201);
        } catch (Exception $e) {
            Log::error('Error al crear registro médico', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error al crear registro médico: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mostrar registro médico
     */
    public function show(Request $request, $id): JsonResponse
    {
        try {
            $query = MedicalRecord::with(['pet', 'client', 'user', 'appointment', 'vaccineRecords']);
            $this->applyCompanyFilter($query, $request);
            $record = $query->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $record,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Registro médico no encontrado',
            ], 404);
        }
    }

    /**
     * Actualizar registro médico
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $query = MedicalRecord::query();
            $this->applyCompanyFilter($query, $request);
            $record = $query->findOrFail($id);

            $validator = Validator::make($request->all(), [
                'type' => 'sometimes|string|in:Consulta,Vacunación,Cirugía,Emergencia,Chequeo,Laboratorio,Desparasitación,Tratamiento',
                'title' => 'nullable|string|max:255',
                'description' => 'sometimes|string',
                'diagnosis' => 'nullable|string',
                'treatment' => 'nullable|string',
                'prescription' => 'nullable|array',
                'attachments' => 'nullable|array',
                'notes' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Errores de validación',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $record->update($validator->validated());

            return response()->json([
                'success' => true,
                'message' => 'Registro médico actualizado exitosamente',
                'data' => $record->load(['pet', 'client', 'user']),
            ]);
        } catch (Exception $e) {
            Log::error('Error al actualizar registro médico', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar registro médico: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Eliminar registro médico
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        try {
            $query = MedicalRecord::query();
            $this->applyCompanyFilter($query, $request);
            $record = $query->findOrFail($id);
            $record->delete();

            return response()->json([
                'success' => true,
                'message' => 'Registro médico eliminado exitosamente',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Registro médico no encontrado',
            ], 404);
        }
    }

    /**
     * Defensa en profundidad: además del global scope, acota por ScopeHelper.
     */
    private function applyCompanyFilter($query, Request $request): void
    {
        $companyId = ScopeHelper::companyId($request);
        if ($companyId) {
            $query->where($query->getModel()->getTable() . '.company_id', $companyId);
        }
    }
}
