<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    /**
     * Listar roles disponibles
     */
    public function index(Request $request): JsonResponse
    {
        $query = Role::query()->where('active', true);

        if ($request->boolean('include_system_only')) {
            $query->where('is_system', true);
        }
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('display_name', 'like', "%{$search}%");
            });
        }

        $roles = $query->orderBy('name')->get();

        $data = $roles->map(function (Role $role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'display_name' => $role->display_name,
                'description' => $role->description,
                'is_system' => $role->is_system,
                'permissions' => $role->getAllPermissions(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Detalle de un rol
     */
    public function show(int $id): JsonResponse
    {
        $role = Role::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $role->id,
                'name' => $role->name,
                'display_name' => $role->display_name,
                'description' => $role->description,
                'is_system' => $role->is_system,
                'active' => $role->active,
                'permissions' => $role->getAllPermissions(),
                'users_count' => $role->users()->count(),
            ],
        ]);
    }
}
