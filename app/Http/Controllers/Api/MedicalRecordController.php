<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MedicalRecord;
use App\Models\VaccineRecord;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Exception;

class MedicalRecordController extends Controller
{
    /**
     * Listar registros médicos
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = MedicalRecord::with(['pet', 'client', 'user', 'appointment']);

            if ($request->has('pet_id')) {
                $query->where('pet_id', $request->pet_id);
            }

            if ($request->has('client_id')) {
                $query->where('client_id', $request->client_id);
            }

            if ($request->has('type')) {
                $query->where('type', $request->type);
            }

            if ($request->has('date_from')) {
                $query->whereDate('date', '>=', $request->date_from);
            }

            if ($request->has('date_to')) {
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
                    'last_page' => $records->lastPage()
                ]
            ]);

        } catch (Exception $e) {
            Log::error("Error al listar registros médicos", ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener registros médicos: ' . $e->getMessage()
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
                    'errors' => $validator->errors()
                ], 422);
            }

            $record = MedicalRecord::create($validator->validated());

            return response()->json([
                'success' => true,
                'message' => 'Registro médico creado exitosamente',
                'data' => $record->load(['pet', 'client', 'user'])
            ], 201);

        } catch (Exception $e) {
            Log::error("Error al crear registro médico", ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error al crear registro médico: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mostrar registro médico
     */
    public function show($id): JsonResponse
    {
        try {
            $record = MedicalRecord::with(['pet', 'client', 'user', 'appointment', 'vaccineRecords'])
                                  ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $record
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Registro médico no encontrado'
            ], 404);
        }
    }

    /**
     * Actualizar registro médico
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $record = MedicalRecord::findOrFail($id);

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
                    'errors' => $validator->errors()
                ], 422);
            }

            $record->update($validator->validated());

            return response()->json([
                'success' => true,
                'message' => 'Registro médico actualizado exitosamente',
                'data' => $record->load(['pet', 'client', 'user'])
            ]);

        } catch (Exception $e) {
            Log::error("Error al actualizar registro médico", ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar registro médico: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar registro médico
     */
    public function destroy($id): JsonResponse
    {
        try {
            $record = MedicalRecord::findOrFail($id);
            $record->delete();

            return response()->json([
                'success' => true,
                'message' => 'Registro médico eliminado exitosamente'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar registro médico'
            ], 500);
        }
    }
}
