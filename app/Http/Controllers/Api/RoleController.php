<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    /**
     * Listar roles disponibles.
     * Query: include_inactive=1, include_system_only=1, search=
     */
    public function index(Request $request): JsonResponse
    {
        $query = Role::query()->withCount('users');

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

        $roles = $query->orderBy('name')->get();

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
        $role = Role::withCount('users')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $this->roleToArray($role),
        ]);
    }

    /**
     * Crear rol personalizado
     */
    public function store(Request $request): JsonResponse
    {
        if ($resp = $this->authorizeManage($request)) {
            return $resp;
        }

        $request->validate([
            'display_name' => 'required|string|max:120',
            'name' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9_-]+$/', Rule::unique('roles', 'name')],
            'description' => 'nullable|string|max:2000',
            'active' => 'nullable|boolean',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|max:120',
        ]);

        try {
            DB::beginTransaction();

            $baseName = $request->filled('name')
                ? $request->string('name')->toString()
                : Str::slug($request->string('display_name')->toString(), '_');

            if ($baseName === '') {
                $baseName = 'rol_' . Str::lower(Str::random(8));
            }

            $name = $this->uniqueRoleName($baseName);

            $role = Role::create([
                'name' => $name,
                'display_name' => $request->string('display_name')->toString(),
                'description' => $request->input('description'),
                'is_system' => false,
                'active' => $request->boolean('active', true),
                'permissions' => [],
            ]);

            $this->applyPermissionsToRole($role, $request->input('permissions', []));

            DB::commit();

            $role->refresh()->loadCount('users');

            return response()->json([
                'success' => true,
                'message' => 'Rol creado correctamente',
                'data' => $this->roleToArray($role),
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error al crear rol', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error al crear rol',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Actualizar rol
     */
    public function update(Request $request, int $id): JsonResponse
    {
        if ($resp = $this->authorizeManage($request)) {
            return $resp;
        }

        $role = Role::findOrFail($id);

        if ($role->is_system && ! $request->user()->hasRole('super_admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Solo el super administrador puede modificar roles del sistema.',
            ], 403);
        }

        $request->validate([
            'display_name' => 'sometimes|string|max:120',
            'description' => 'nullable|string|max:2000',
            'active' => 'sometimes|boolean',
            'permissions' => 'sometimes|array',
            'permissions.*' => 'string|max:120',
            'name' => ['sometimes', 'nullable', 'string', 'max:80', 'regex:/^[a-z0-9_-]+$/', Rule::unique('roles', 'name')->ignore($role->id)],
        ]);

        if ($role->isCriticalSystemRole() && $request->has('active') && ! $request->boolean('active')) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede desactivar un rol crítico del sistema.',
            ], 422);
        }

        try {
            DB::beginTransaction();

            if ($request->filled('display_name')) {
                $role->display_name = $request->string('display_name')->toString();
            }
            if ($request->has('description')) {
                $role->description = $request->input('description');
            }
            if ($request->has('active') && ! $role->isCriticalSystemRole()) {
                $role->active = $request->boolean('active');
            }
            if ($request->filled('name') && ! $role->is_system) {
                $role->name = $request->string('name')->toString();
            }

            $role->save();

            if ($request->has('permissions')) {
                $this->applyPermissionsToRole($role, $request->input('permissions', []));
            }

            DB::commit();

            $role->refresh()->loadCount('users');

            return response()->json([
                'success' => true,
                'message' => 'Rol actualizado correctamente',
                'data' => $this->roleToArray($role),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error al actualizar rol', ['id' => $id, 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar rol',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Alternar activo/inactivo (roles no críticos)
     */
    public function toggle(Request $request, int $id): JsonResponse
    {
        if ($resp = $this->authorizeManage($request)) {
            return $resp;
        }

        $role = Role::withCount('users')->findOrFail($id);

        if ($role->is_system && ! $request->user()->hasRole('super_admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Solo el super administrador puede activar o desactivar roles del sistema.',
            ], 403);
        }

        if ($role->isCriticalSystemRole()) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede desactivar un rol crítico del sistema.',
            ], 422);
        }

        $role->active = ! $role->active;
        $role->save();

        return response()->json([
            'success' => true,
            'message' => $role->active ? 'Rol activado' : 'Rol desactivado',
            'data' => $this->roleToArray($role->fresh()->loadCount('users')),
        ]);
    }

    /**
     * Eliminar rol (solo personalizados y sin usuarios)
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        if ($resp = $this->authorizeManage($request)) {
            return $resp;
        }

        $role = Role::withCount('users')->findOrFail($id);

        if (($role->is_system || $role->isCriticalSystemRole()) && ! $request->user()->hasRole('super_admin')) {
            return response()->json([
                'success' => false,
                'message' => 'No tiene permiso para eliminar este rol.',
            ], 403);
        }

        if ($role->is_system || $role->isCriticalSystemRole()) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar un rol del sistema.',
            ], 422);
        }

        if ($role->users_count > 0) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar el rol: hay usuarios asignados.',
            ], 422);
        }

        try {
            $role->permissions()->detach();
            $role->delete();

            return response()->json([
                'success' => true,
                'message' => 'Rol eliminado correctamente',
            ]);
        } catch (\Throwable $e) {
            Log::error('Error al eliminar rol', ['id' => $id, 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar rol',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    private function authorizeManage(Request $request): ?JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'No autenticado'], 401);
        }
        if ($user->hasRole('super_admin')
            || $user->hasRole('company_admin')
            || $user->hasPermission('users.manage')) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'No tiene permiso para gestionar roles',
        ], 403);
    }

    private function roleToArray(Role $role): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'display_name' => $role->display_name,
            'description' => $role->description,
            'is_system' => (bool) $role->is_system,
            'active' => (bool) $role->active,
            'protected' => $role->isCriticalSystemRole(),
            'permissions' => $role->getAllPermissions(),
            'users_count' => $role->users_count ?? $role->users()->count(),
        ];
    }

    /**
     * Sincroniza permisos por pivote y copia nombres válidos en JSON del rol.
     *
     * @param  array<int, string>  $names
     */
    private function applyPermissionsToRole(Role $role, array $names): void
    {
        $validNames = Permission::query()
            ->where('active', true)
            ->whereIn('name', $names)
            ->orderBy('name')
            ->pluck('name')
            ->values()
            ->all();

        $ids = Permission::query()
            ->whereIn('name', $validNames)
            ->pluck('id')
            ->all();

        $role->permissions()->sync($ids);

        $role->forceFill(['permissions' => $validNames])->save();
    }

    private function uniqueRoleName(string $base): string
    {
        $name = $base;
        $i = 1;
        while (Role::where('name', $name)->exists()) {
            $name = $base . '_' . $i;
            $i++;
        }

        return $name;
    }
}
