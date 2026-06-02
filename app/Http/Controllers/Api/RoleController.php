<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\HandlesStaffAuthorization;
use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    use HandlesStaffAuthorization;
    /**
     * Roles de sistema cuyo "name" NO se puede modificar, eliminar ni desactivar.
     * Los permisos sí pueden ajustarse (menos super_admin, que se fuerza a '*').
     */
    private const PROTECTED_ROLE_NAMES = [
        'super_admin',
        'company_admin',
        'company_user',
        'api_client',
        'read_only',
    ];

    /**
     * Listar roles disponibles
     */
    public function index(Request $request): JsonResponse
    {
        $authUser = $request->user();
        if (!$this->canViewRoles($authUser)) {
            return $this->denyStaff('No tiene permiso para consultar roles');
        }

        $query = Role::query()->with('permissions');

        // Por defecto, solo activos. Con include_inactive=1 se listan todos.
        if (!$request->boolean('include_inactive')) {
            $query->where('active', true);
        }

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

        $roles = $query->withCount('users')->orderBy('name')->get();

        $data = $roles->map(fn (Role $role) => $this->roleToArray($role));

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
        if (!$this->canViewRoles(request()->user())) {
            return $this->denyStaff('No tiene permiso para consultar roles');
        }

        $role = Role::with('permissions')->withCount('users')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $this->roleToArray($role, true),
        ]);
    }

    /**
     * Crear rol personalizado
     */
    public function store(Request $request): JsonResponse
    {
        if (!$this->canManageRoles($request->user())) {
            return $this->denyStaff('No tiene permiso para crear roles');
        }

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:64', 'regex:/^[a-z0-9_\-]+$/', 'unique:roles,name'],
            'display_name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'active' => ['nullable', 'boolean'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'max:100'],
        ]);

        $name = $data['name'] ?? Str::slug($data['display_name'], '_');
        // Evitar colisiones si el slug ya existe
        $baseName = $name;
        $i = 2;
        while (Role::where('name', $name)->exists()) {
            $name = $baseName . '_' . $i++;
        }

        $permissions = array_values(array_unique($data['permissions'] ?? []));

        $role = Role::create([
            'name' => $name,
            'display_name' => $data['display_name'],
            'description' => $data['description'] ?? null,
            'permissions' => $permissions,
            'is_system' => false,
            'active' => $data['active'] ?? true,
        ]);

        $this->syncPivotPermissions($role, $permissions);

        return response()->json([
            'success' => true,
            'message' => 'Rol creado correctamente',
            'data' => $this->roleToArray($role->fresh()->loadCount('users'), true),
        ], 201);
    }

    /**
     * Actualizar un rol
     */
    public function update(Request $request, int $id): JsonResponse
    {
        if (!$this->canManageRoles($request->user())) {
            return $this->denyStaff('No tiene permiso para editar roles');
        }

        $role = Role::findOrFail($id);

        if (!$this->canManageRoleRecord($request->user(), $role, 'update')) {
            return $this->denyStaff('No puede modificar este rol de sistema');
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:64', 'regex:/^[a-z0-9_\-]+$/', Rule::unique('roles', 'name')->ignore($role->id)],
            'display_name' => ['sometimes', 'string', 'max:120'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'active' => ['sometimes', 'boolean'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', 'max:100'],
        ]);

        $isProtected = $this->isProtectedRole($role);

        // Para roles protegidos / de sistema: solo se permiten display_name, description y permissions
        if ($isProtected) {
            if ($request->has('name') && $data['name'] !== $role->name) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede cambiar el nombre de un rol de sistema',
                ], 422);
            }
            if ($request->has('active') && (bool) $data['active'] !== (bool) $role->active) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede desactivar un rol de sistema',
                ], 422);
            }
        }

        if (!$isProtected && $request->has('name')) {
            $role->name = $data['name'];
        }

        if ($request->has('display_name')) {
            $role->display_name = $data['display_name'];
        }

        if ($request->has('description')) {
            $role->description = $data['description'];
        }

        if (!$isProtected && $request->has('active')) {
            $role->active = (bool) $data['active'];
        }

        if ($request->has('permissions')) {
            $permissions = array_values(array_unique($data['permissions']));

            // super_admin siempre tiene '*'
            if ($role->name === 'super_admin') {
                $permissions = ['*'];
            }

            $role->permissions = $permissions;
            $this->syncPivotPermissions($role, $permissions);
        }

        $role->save();

        return response()->json([
            'success' => true,
            'message' => 'Rol actualizado correctamente',
            'data' => $this->roleToArray($role->fresh()->loadCount('users'), true),
        ]);
    }

    /**
     * Activar/desactivar rol (solo roles no protegidos)
     */
    public function toggle(Request $request, int $id): JsonResponse
    {
        if (!$this->canManageRoles($request->user())) {
            return $this->denyStaff('No tiene permiso para modificar roles');
        }

        $role = Role::findOrFail($id);

        if (!$this->canManageRoleRecord($request->user(), $role, 'toggle')) {
            return $this->denyStaff('No puede modificar este rol de sistema');
        }

        if ($this->isProtectedRole($role)) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede desactivar un rol de sistema',
            ], 422);
        }

        $role->active = !$role->active;
        $role->save();

        return response()->json([
            'success' => true,
            'data' => $this->roleToArray($role->fresh()->loadCount('users'), true),
        ]);
    }

    /**
     * Eliminar un rol personalizado. No permite eliminar roles protegidos
     * ni roles con usuarios asignados.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        if (!$this->canManageRoles($request->user())) {
            return $this->denyStaff('No tiene permiso para eliminar roles');
        }

        $role = Role::withCount('users')->findOrFail($id);

        if (!$this->canManageRoleRecord($request->user(), $role, 'delete')) {
            return $this->denyStaff('No puede eliminar este rol de sistema');
        }

        if ($this->isProtectedRole($role) || $role->is_system) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar un rol de sistema',
            ], 422);
        }

        if ($role->users_count > 0) {
            return response()->json([
                'success' => false,
                'message' => "No se puede eliminar: hay {$role->users_count} usuario(s) asignado(s) a este rol",
            ], 422);
        }

        $role->permissions()->detach();
        $role->delete();

        return response()->json([
            'success' => true,
            'message' => 'Rol eliminado correctamente',
        ]);
    }

    // ===================
    // Helpers
    // ===================

    private function roleToArray(Role $role, bool $detail = false): array
    {
        $out = [
            'id' => $role->id,
            'name' => $role->name,
            'display_name' => $role->display_name,
            'description' => $role->description,
            'is_system' => (bool) $role->is_system,
            'active' => (bool) $role->active,
            'permissions' => $role->getAllPermissions(),
            'users_count' => $role->users_count ?? $role->users()->count(),
            'protected' => $this->isProtectedRole($role),
        ];

        if ($detail) {
            $out['created_at'] = $role->created_at;
            $out['updated_at'] = $role->updated_at;
        }

        return $out;
    }

    private function isProtectedRole(Role $role): bool
    {
        return in_array($role->name, self::PROTECTED_ROLE_NAMES, true) || (bool) $role->is_system;
    }

    /**
     * Sincroniza la tabla pivote role_permission según la lista de nombres.
     * Expande comodines (ej. invoices.*) y el wildcard global (*).
     */
    private function syncPivotPermissions(Role $role, array $permissionNames): void
    {
        $expanded = collect($permissionNames)
            ->flatMap(function ($p) {
                if ($p === '*') {
                    return Permission::query()->where('active', true)->pluck('name');
                }
                if (Str::contains($p, '*')) {
                    return collect(Permission::expandWildcardPermission($p));
                }
                return [$p];
            })
            ->filter()
            ->unique()
            ->values();

        $ids = Permission::whereIn('name', $expanded)->pluck('id')->all();
        $role->permissions()->sync($ids);
    }
}
