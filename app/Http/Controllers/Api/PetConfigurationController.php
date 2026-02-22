<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PetConfiguration;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Exception;

class PetConfigurationController extends Controller
{
    /**
     * Obtener configuraciones por tipo
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $type = $request->query('type'); // dog_breed, cat_breed, temperament, behavior, species
            $companyId = $request->query('company_id');

            $query = PetConfiguration::query();

            if ($type) {
                $query->where('type', $type);
            }

            if ($companyId) {
                $query->where('company_id', $companyId);
            } else {
                // Si no hay company_id, obtener las globales (company_id = null)
                $query->whereNull('company_id');
            }

            // Por defecto solo activas
            if (!$request->boolean('include_inactive', false)) {
                $query->active();
            }

            $configurations = $query->ordered()->get();

            return response()->json([
                'success' => true,
                'data' => $configurations->pluck('name')->toArray()
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener configuraciones: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener todas las configuraciones agrupadas por tipo
     */
    public function getAll(Request $request): JsonResponse
    {
        try {
            $companyId = $request->query('company_id');

            $query = PetConfiguration::query();

            if ($companyId) {
                $query->where('company_id', $companyId);
            } else {
                $query->whereNull('company_id');
            }

            if (!$request->boolean('include_inactive', false)) {
                $query->active();
            }

            $configurations = $query->ordered()->get();

            $dogBreeds = $configurations->where('type', 'dog_breed')->pluck('name')->toArray();
            $catBreeds = $configurations->where('type', 'cat_breed')->pluck('name')->toArray();

            $breedsBySpecies = [
                'Perro' => $dogBreeds,
                'Gato' => $catBreeds,
            ];
            foreach ($configurations->groupBy('type') as $type => $items) {
                if (str_starts_with($type, 'breed_')) {
                    $speciesName = substr($type, 6);
                    $breedsBySpecies[$speciesName] = $items->pluck('name')->toArray();
                }
            }

            $grouped = [
                'species' => $configurations->where('type', 'species')->pluck('name')->toArray(),
                'dog_breeds' => $dogBreeds,
                'cat_breeds' => $catBreeds,
                'breeds_by_species' => $breedsBySpecies,
                'temperaments' => $configurations->where('type', 'temperament')->pluck('name')->toArray(),
                'behaviors' => $configurations->where('type', 'behavior')->pluck('name')->toArray(),
            ];

            return response()->json([
                'success' => true,
                'data' => $grouped
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener configuraciones: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear o actualizar configuraciones (batch)
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'type' => [
                    'required',
                    'string',
                    function ($attribute, $value, $fail) {
                        $allowed = ['species', 'dog_breed', 'cat_breed', 'temperament', 'behavior'];
                        if (! in_array($value, $allowed) && ! preg_match('/^breed_.+/', $value)) {
                            $fail('El tipo debe ser species, dog_breed, cat_breed, temperament, behavior o breed_<especie>.');
                        }
                    },
                ],
                'items' => 'required|array',
                'items.*' => 'required|string|max:255',
                'company_id' => ['nullable', Rule::when($request->filled('company_id'), ['integer', Rule::exists('companies', 'id')])],
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Errores de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            $type = $request->type;
            $companyId = $request->company_id;
            $itemsRaw = is_array($request->items) ? $request->items : [];
            $seen = [];
            $duplicates = [];
            $items = [];
            foreach ($itemsRaw as $item) {
                $name = trim((string) $item);
                if ($name === '') {
                    continue;
                }
                $key = mb_strtolower($name);
                if (isset($seen[$key])) {
                    $duplicates[] = $name;
                    continue;
                }
                $seen[$key] = true;
                $items[] = $name;
            }
            if (!empty($duplicates)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Se detectaron elementos duplicados en la lista',
                    'duplicates' => array_values(array_unique($duplicates)),
                ], 422);
            }

            // Eliminar configuraciones existentes del mismo tipo y company (null en SQL requiere whereNull)
            $deleteQuery = PetConfiguration::where('type', $type);
            if ($companyId === null) {
                $deleteQuery->whereNull('company_id');
            } else {
                $deleteQuery->where('company_id', $companyId);
            }
            $deleteQuery->delete();

            // Crear nuevas configuraciones
            $configurations = [];
            foreach ($items as $index => $name) {
                $configurations[] = PetConfiguration::create([
                    'company_id' => $companyId,
                    'type' => $type,
                    'name' => $name,
                    'sort_order' => $index,
                    'active' => true,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Configuraciones guardadas exitosamente',
                'data' => $configurations
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar configuraciones: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Agregar una configuración individual
     */
    public function addItem(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'type' => [
                    'required',
                    'string',
                    function ($attribute, $value, $fail) {
                        $allowed = ['species', 'dog_breed', 'cat_breed', 'temperament', 'behavior'];
                        if (! in_array($value, $allowed) && ! preg_match('/^breed_.+/', $value)) {
                            $fail('El tipo no es válido.');
                        }
                    },
                ],
                'name' => 'required|string|max:255',
                'company_id' => ['nullable', Rule::when($request->filled('company_id'), ['integer', Rule::exists('companies', 'id')])],
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Errores de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Verificar si ya existe (company_id null requiere whereNull en SQL)
            $normalizedName = trim((string) $request->name);
            $existingQuery = PetConfiguration::where('type', $request->type)
                ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($normalizedName)]);
            if ($request->company_id === null) {
                $existingQuery->whereNull('company_id');
            } else {
                $existingQuery->where('company_id', $request->company_id);
            }
            $existing = $existingQuery->first();

            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta configuración ya existe'
                ], 400);
            }

            // Obtener el siguiente sort_order
            $orderQuery = PetConfiguration::where('type', $request->type);
            if ($request->company_id === null) {
                $orderQuery->whereNull('company_id');
            } else {
                $orderQuery->where('company_id', $request->company_id);
            }
            $maxOrder = $orderQuery->max('sort_order') ?? -1;

            $configuration = PetConfiguration::create([
                'company_id' => $request->company_id,
                'type' => $request->type,
                'name' => $normalizedName,
                'sort_order' => $maxOrder + 1,
                'active' => true,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Configuración agregada exitosamente',
                'data' => $configuration
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al agregar configuración: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar una configuración
     */
    public function destroy($id): JsonResponse
    {
        try {
            $configuration = PetConfiguration::findOrFail($id);
            $configuration->delete();

            return response()->json([
                'success' => true,
                'message' => 'Configuración eliminada exitosamente'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar configuración: ' . $e->getMessage()
            ], 500);
        }
    }
}
