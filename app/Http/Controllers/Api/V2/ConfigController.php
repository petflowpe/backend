<?php

namespace App\Http\Controllers\Api\V2;

use App\Helpers\ScopeHelper;
use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\Module;
use App\Models\PetConfiguration;
use App\Models\UbiDistrito;
use App\Models\UbiProvincia;
use App\Models\UbiRegion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ConfigController extends Controller
{
    /**
     * Catálogos/valores maestros para el frontend (React).
     *
     * Permite ir removiendo hardcode del cliente (distritos, species, document types, etc.).
     */
    public function masters(Request $request): JsonResponse
    {
        try {
            $companyId = ScopeHelper::companyId($request);

            // Ubigeo (si está cargado): por defecto Lima (Perú) pero admite filtro por region/provincia.
            $regionId = $request->query('regionId');
            $provinciaId = $request->query('provinciaId');
            $search = $request->query('search');

            $regions = UbiRegion::orderBy('nombre')->get(['id', 'nombre']);
            $provinciasQuery = UbiProvincia::query()->orderBy('nombre');
            if ($regionId) {
                $provinciasQuery->where('region_id', $regionId);
            }
            $provincias = $provinciasQuery->get(['id', 'nombre', 'region_id']);

            $distritosQuery = UbiDistrito::query()->orderBy('nombre');
            if ($provinciaId) {
                $distritosQuery->where('provincia_id', $provinciaId);
            }
            if ($regionId) {
                $distritosQuery->where('region_id', $regionId);
            }
            if ($search && is_string($search) && mb_strlen($search) >= 2) {
                $distritosQuery->where(function ($q) use ($search) {
                    $q->where('nombre', 'like', "%{$search}%")
                        ->orWhere('info_busqueda', 'like', "%{$search}%");
                });
            }
            $distritos = $distritosQuery->limit(500)->get(['id', 'nombre', 'provincia_id', 'region_id']);

            // Pet configurations: por empresa (si existe) o global.
            $petConfigsQuery = PetConfiguration::query();
            if ($companyId) {
                $petConfigsQuery->where('company_id', $companyId)->orWhereNull('company_id');
            } else {
                $petConfigsQuery->whereNull('company_id');
            }
            $petConfigs = $petConfigsQuery->active()->ordered()->get(['type', 'name']);

            $dogBreeds = $petConfigs->where('type', 'dog_breed')->pluck('name')->values()->all();
            $catBreeds = $petConfigs->where('type', 'cat_breed')->pluck('name')->values()->all();
            $species = $petConfigs->where('type', 'species')->pluck('name')->values()->all();
            if (empty($species)) {
                $species = ['Perro', 'Gato', 'Exótico'];
            }

            $breedsBySpecies = [
                'Perro' => $dogBreeds,
                'Gato' => $catBreeds,
            ];
            foreach ($petConfigs->groupBy('type') as $type => $items) {
                if (str_starts_with($type, 'breed_')) {
                    $speciesName = substr($type, 6);
                    $breedsBySpecies[$speciesName] = $items->pluck('name')->values()->all();
                }
            }

            // Core: monedas y módulos (activos).
            $currencies = Currency::active()
                ->orderBy('is_default', 'desc')
                ->orderBy('code')
                ->get(['code', 'name', 'symbol', 'decimal_places', 'is_default']);

            $modules = Module::active()
                ->orderBy('order')
                ->get(['id', 'name', 'slug', 'description', 'order']);

            return response()->json([
                'success' => true,
                'data' => [
                    'documentTypes' => [
                        ['value' => 'DNI', 'label' => 'DNI'],
                        ['value' => 'RUC', 'label' => 'RUC'],
                        ['value' => 'CE', 'label' => 'CE'],
                        ['value' => 'Pasaporte', 'label' => 'Pasaporte'],
                    ],
                    'clientTypes' => ['Regular', 'VIP', 'Moroso', 'Problemático', 'Empleado'],
                    'clientStatuses' => ['Activo', 'Inactivo'],

                    'petSpecies' => $species,
                    'petGenders' => ['Macho', 'Hembra'],
                    'breedsBySpecies' => $breedsBySpecies,
                    'temperaments' => $petConfigs->where('type', 'temperament')->pluck('name')->values()->all(),
                    'behaviors' => $petConfigs->where('type', 'behavior')->pluck('name')->values()->all(),

                    'appointmentStatuses' => ['Pendiente', 'Confirmada', 'En Proceso', 'Completada', 'Cancelada'],
                    'paymentMethods' => ['Efectivo', 'Tarjeta', 'Yape', 'Plin', 'Transferencia'],
                    'paymentStatuses' => ['Pendiente', 'Pagado', 'Reembolsado'],

                    'geo' => [
                        'regions' => $regions->map(fn ($r) => ['id' => $r->id, 'name' => $r->nombre])->values(),
                        'provinces' => $provincias->map(fn ($p) => [
                            'id' => $p->id,
                            'name' => $p->nombre,
                            'regionId' => $p->region_id,
                        ])->values(),
                        'districts' => $distritos->map(fn ($d) => [
                            'id' => $d->id,
                            'name' => $d->nombre,
                            'provinceId' => $d->provincia_id,
                            'regionId' => $d->region_id,
                        ])->values(),
                    ],

                    'currencies' => $currencies->map(fn ($c) => [
                        'code' => $c->code,
                        'name' => $c->name,
                        'symbol' => $c->symbol,
                        'decimalPlaces' => (int) $c->decimal_places,
                        'isDefault' => (bool) $c->is_default,
                    ])->values(),

                    'modules' => $modules,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('v2 config masters error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener catálogos',
            ], 500);
        }
    }
}

