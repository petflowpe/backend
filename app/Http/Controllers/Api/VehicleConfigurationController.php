<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VehicleConfiguration;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class VehicleConfigurationController extends Controller
{
    public function getAll(Request $request): JsonResponse
    {
        try {
            // Scope efectivo: preferir el scope del request (auth+middleware). Si no hay, permitir querystring.
            $companyId = $request->attributes->get('scope_company_id');
            if ($companyId === null) {
                $companyId = $request->query('company_id');
            }

            // Cargar global (company_id NULL) y, si aplica, también por empresa.
            // Esto evita el síntoma "guarda pero al refrescar desaparece" cuando alguna operación
            // terminó grabando en NULL vs company_id, o cuando el cliente no envía company_id explícito.
            $includeInactive = $request->boolean('include_inactive', false);

            $globalQuery = VehicleConfiguration::query()->whereNull('company_id');
            if (! $includeInactive) {
                $globalQuery->active();
            }
            $globalRows = $globalQuery->ordered()->get();

            $companyRows = collect();
            if ($companyId) {
                $companyQuery = VehicleConfiguration::query()->where('company_id', $companyId);
                if (! $includeInactive) {
                    $companyQuery->active();
                }
                $companyRows = $companyQuery->ordered()->get();
            }

            // Preferir data por empresa si existe; si no, usar global.
            $rowsForBrands = $companyRows->where('type', 'vehicle_brand')->count() ? $companyRows : $globalRows;
            $rowsForMaintenanceTypes = $companyRows->where('type', 'vehicle_maintenance_type')->count() ? $companyRows : $globalRows;
            $rowsForModels = $companyRows->where('type', 'vehicle_model')->count() ? $companyRows : $globalRows;
            $rowsForWorkshops = $companyRows->where('type', 'vehicle_workshop')->count() ? $companyRows : $globalRows;

            $brands = $rowsForBrands->where('type', 'vehicle_brand')->pluck('name')->values()->toArray();
            $maintenanceTypes = $rowsForMaintenanceTypes->where('type', 'vehicle_maintenance_type')->pluck('name')->values()->toArray();

            $modelsByBrand = [];
            foreach ($rowsForModels->where('type', 'vehicle_model') as $row) {
                $brand = is_array($row->meta) ? ($row->meta['brand'] ?? null) : null;
                if (! is_string($brand) || trim($brand) === '') {
                    continue;
                }
                $brand = trim($brand);
                if (! isset($modelsByBrand[$brand])) {
                    $modelsByBrand[$brand] = [];
                }
                $modelsByBrand[$brand][] = $row->name;
            }
            foreach ($modelsByBrand as $brand => $list) {
                $unique = [];
                $seen = [];
                foreach ($list as $m) {
                    $key = mb_strtolower(trim((string) $m));
                    if ($key === '' || isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = true;
                    $unique[] = trim((string) $m);
                }
                $modelsByBrand[$brand] = array_values($unique);
            }

            $workshops = $rowsForWorkshops->where('type', 'vehicle_workshop')->map(function (VehicleConfiguration $row) {
                $meta = is_array($row->meta) ? $row->meta : [];
                return [
                    'id' => $row->id,
                    'name' => $row->name,
                    'ruc' => $meta['ruc'] ?? null,
                    'address' => $meta['address'] ?? null,
                    'phone' => $meta['phone'] ?? null,
                ];
            })->values()->toArray();

            return response()->json([
                'success' => true,
                'data' => [
                    'brands' => $brands,
                    'models_by_brand' => $modelsByBrand,
                    'maintenance_types' => $maintenanceTypes,
                    'workshops' => $workshops,
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener configuraciones de vehículos: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'type' => ['required', 'string', Rule::in(['brands', 'models_by_brand', 'maintenance_types', 'workshops'])],
                'company_id' => ['nullable', Rule::when($request->filled('company_id'), ['integer', Rule::exists('companies', 'id')])],
                'items' => 'nullable|array',
                'items.*' => 'nullable',
                'models_by_brand' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Errores de validación',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Usar el scope efectivo como fuente de verdad; fallback a input (super_admin con selección explícita).
            $companyId = $request->attributes->get('scope_company_id');
            if ($companyId === null) {
                $companyId = $request->input('company_id');
            }
            $type = $request->input('type');

            if ($type === 'brands') {
                return $this->storeSimpleList($companyId, 'vehicle_brand', $request->input('items', []));
            }
            if ($type === 'maintenance_types') {
                return $this->storeSimpleList($companyId, 'vehicle_maintenance_type', $request->input('items', []));
            }
            if ($type === 'models_by_brand') {
                $modelsByBrand = $request->input('models_by_brand', []);
                if (! is_array($modelsByBrand)) {
                    return response()->json(['success' => false, 'message' => 'models_by_brand debe ser un objeto'], 422);
                }
                return $this->storeModelsByBrand($companyId, $modelsByBrand);
            }
            if ($type === 'workshops') {
                $items = $request->input('items', []);
                if (! is_array($items)) {
                    return response()->json(['success' => false, 'message' => 'items debe ser un array'], 422);
                }
                return $this->storeWorkshops($companyId, $items);
            }

            return response()->json(['success' => false, 'message' => 'Tipo no soportado'], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar configuraciones de vehículos: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function storeSimpleList($companyId, string $dbType, $itemsRaw): JsonResponse
    {
        $itemsRaw = is_array($itemsRaw) ? $itemsRaw : [];
        $items = [];
        $seen = [];
        foreach ($itemsRaw as $item) {
            $name = trim((string) $item);
            if ($name === '') {
                continue;
            }
            $key = mb_strtolower($name);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $items[] = $name;
        }

        $deleteQuery = VehicleConfiguration::where('type', $dbType);
        if ($companyId === null) {
            $deleteQuery->whereNull('company_id');
        } else {
            $deleteQuery->where('company_id', $companyId);
        }
        $deleteQuery->delete();

        foreach ($items as $index => $name) {
            VehicleConfiguration::create([
                'company_id' => $companyId,
                'type' => $dbType,
                'name' => $name,
                'meta' => null,
                'sort_order' => $index,
                'active' => true,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Configuraciones guardadas exitosamente',
        ], 201);
    }

    private function storeModelsByBrand($companyId, array $modelsByBrand): JsonResponse
    {
        $deleteQuery = VehicleConfiguration::where('type', 'vehicle_model');
        if ($companyId === null) {
            $deleteQuery->whereNull('company_id');
        } else {
            $deleteQuery->where('company_id', $companyId);
        }
        $deleteQuery->delete();

        foreach ($modelsByBrand as $brand => $models) {
            $brandName = trim((string) $brand);
            if ($brandName === '' || ! is_array($models)) {
                continue;
            }
            $seen = [];
            $clean = [];
            foreach ($models as $m) {
                $name = trim((string) $m);
                if ($name === '') continue;
                $key = mb_strtolower($name);
                if (isset($seen[$key])) continue;
                $seen[$key] = true;
                $clean[] = $name;
            }

            foreach ($clean as $index => $name) {
                VehicleConfiguration::create([
                    'company_id' => $companyId,
                    'type' => 'vehicle_model',
                    'name' => $name,
                    'meta' => ['brand' => $brandName],
                    'sort_order' => $index,
                    'active' => true,
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Modelos guardados exitosamente',
        ], 201);
    }

    private function storeWorkshops($companyId, array $itemsRaw): JsonResponse
    {
        $items = [];
        $seenRuc = [];
        foreach ($itemsRaw as $row) {
            if (! is_array($row)) continue;
            $ruc = preg_replace('/\D/', '', (string) ($row['ruc'] ?? ''));
            $name = trim((string) ($row['name'] ?? ''));
            $address = trim((string) ($row['address'] ?? ''));
            $phone = trim((string) ($row['phone'] ?? ''));

            if ($name === '' || $ruc === '' || ! preg_match('/^\d{11}$/', $ruc)) {
                continue;
            }
            if (isset($seenRuc[$ruc])) {
                continue;
            }
            $seenRuc[$ruc] = true;
            $items[] = ['ruc' => $ruc, 'name' => $name, 'address' => $address, 'phone' => $phone];
        }

        $deleteQuery = VehicleConfiguration::where('type', 'vehicle_workshop');
        if ($companyId === null) {
            $deleteQuery->whereNull('company_id');
        } else {
            $deleteQuery->where('company_id', $companyId);
        }
        $deleteQuery->delete();

        foreach ($items as $index => $w) {
            VehicleConfiguration::create([
                'company_id' => $companyId,
                'type' => 'vehicle_workshop',
                'name' => $w['name'],
                'meta' => [
                    'ruc' => $w['ruc'],
                    'address' => $w['address'] !== '' ? $w['address'] : null,
                    'phone' => $w['phone'] !== '' ? $w['phone'] : null,
                ],
                'sort_order' => $index,
                'active' => true,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Talleres guardados exitosamente',
        ], 201);
    }
}

