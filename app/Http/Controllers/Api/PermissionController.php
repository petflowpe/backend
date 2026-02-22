<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    /**
     * Listar permisos disponibles (por categoría)
     */
    public function index(Request $request): JsonResponse
    {
        $fromDb = $request->boolean('from_db', true);

        if ($fromDb && Permission::count() > 0) {
            $permissions = Permission::where('active', true)
                ->orderBy('category')
                ->orderBy('name')
                ->get();

            $byCategory = $permissions->groupBy('category')->map(function ($items) {
                return $items->map(function ($p) {
                    return [
                        'id' => $p->id,
                        'name' => $p->name,
                        'display_name' => $p->display_name,
                        'description' => $p->description,
                        'category' => $p->category,
                    ];
                })->values();
            });

            return response()->json([
                'success' => true,
                'data' => $byCategory,
                'flat' => $permissions->pluck('display_name', 'name'),
            ]);
        }

        // Fallback: permisos del sistema (estáticos)
        $system = Permission::getSystemPermissions();
        $flat = [];
        $byCategory = [];
        foreach ($system as $category => $items) {
            $byCategory[$category] = [];
            foreach ($items as $name => $info) {
                $byCategory[$category][] = [
                    'name' => $name,
                    'display_name' => $info['display_name'] ?? $name,
                    'description' => $info['description'] ?? null,
                    'category' => $category,
                ];
                $flat[$name] = $info['display_name'] ?? $name;
            }
        }

        return response()->json([
            'success' => true,
            'data' => $byCategory,
            'flat' => $flat,
        ]);
    }
}
