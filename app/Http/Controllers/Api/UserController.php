<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;
use Exception;

class UserController extends Controller
{
    /**
     * Listar usuarios (staff/empleados)
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = User::with(['role:id,name,display_name'])
                ->select(['id', 'name', 'email', 'role_id', 'company_id', 'active', 'last_login_at', 'created_at']);

            if ($request->filled('company_id')) {
                $query->where('company_id', $request->company_id);
            }
            if ($request->boolean('only_active', false)) {
                $query->where('active', true);
            }
            if ($request->filled('search')) {
                $search = $request->get('search');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            }

            $perPage = $request->integer('per_page', 20);
            $users = $query->orderBy('name')->paginate($perPage);

            $data = $users->getCollection()->map(function (User $user) {
                return $this->userToArray($user);
            });

            return response()->json([
                'success' => true,
                'data' => $data,
                'meta' => [
                    'total' => $users->total(),
                    'per_page' => $users->perPage(),
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Error al listar usuarios', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener usuarios',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Ver un usuario por ID
     */
    public function show(int $id): JsonResponse
    {
        try {
            $user = User::with(['role', 'company'])->findOrFail($id);

            if (!$this->canAccessUser($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tiene permiso para ver este usuario',
                ], 403);
            }

            return response()->json([
                'success' => true,
                'data' => $this->userToArray($user, true),
            ]);
        } catch (Exception $e) {
            Log::error('Error al obtener usuario', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 404);
        }
    }

    /**
     * Crear usuario
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', Password::min(8)->letters()->mixedCase()->numbers()],
            'role_id' => 'required|exists:roles,id',
            'company_id' => 'nullable|exists:companies,id',
            'user_type' => 'nullable|in:user,api_client,system',
            'active' => 'nullable|boolean',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|max:100',
        ]);

        try {
            $authUser = $request->user();
            if (!$authUser->hasPermission('users.create') && !$authUser->hasRole('super_admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tiene permiso para crear usuarios',
                ], 403);
            }

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role_id' => $request->role_id,
                'company_id' => $request->company_id ?? $authUser->company_id,
                'user_type' => $request->user_type ?? 'user',
                'active' => $request->boolean('active', true),
                'permissions' => $request->permissions,
                'password_changed_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Usuario creado correctamente',
                'data' => $this->userToArray($user->load('role')),
            ], 201);
        } catch (Exception $e) {
            Log::error('Error al crear usuario', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error al crear usuario',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Actualizar usuario
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::with('role')->findOrFail($id);

        if (!$this->canAccessUser($user)) {
            return response()->json([
                'success' => false,
                'message' => 'No tiene permiso para editar este usuario',
            ], 403);
        }

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $id,
            'password' => ['sometimes', 'nullable', Password::min(8)->letters()->mixedCase()->numbers()],
            'role_id' => 'sometimes|exists:roles,id',
            'company_id' => 'nullable|exists:companies,id',
            'active' => 'sometimes|boolean',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|max:100',
        ]);

        try {
            $authUser = $request->user();
            if (!$authUser->hasPermission('users.update') && !$authUser->hasRole('super_admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tiene permiso para editar usuarios',
                ], 403);
            }

            if ($request->filled('name')) {
                $user->name = $request->name;
            }
            if ($request->filled('email')) {
                $user->email = $request->email;
            }
            if ($request->filled('password')) {
                $user->password = Hash::make($request->password);
                $user->password_changed_at = now();
            }
            if ($request->has('role_id')) {
                $user->role_id = $request->role_id;
            }
            if ($request->has('company_id')) {
                $user->company_id = $request->company_id;
            }
            if ($request->has('active')) {
                $user->active = $request->boolean('active');
            }
            if ($request->has('permissions')) {
                $user->permissions = $request->permissions;
            }
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Usuario actualizado correctamente',
                'data' => $this->userToArray($user->fresh('role')),
            ]);
        } catch (Exception $e) {
            Log::error('Error al actualizar usuario', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar usuario',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Eliminar / desactivar usuario
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        if ($user->id === $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'No puede eliminar su propia cuenta',
            ], 422);
        }

        if (!$this->canAccessUser($user)) {
            return response()->json([
                'success' => false,
                'message' => 'No tiene permiso para eliminar este usuario',
            ], 403);
        }

        try {
            $authUser = $request->user();
            if (!$authUser->hasPermission('users.delete') && !$authUser->hasRole('super_admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tiene permiso para eliminar usuarios',
                ], 403);
            }

            $soft = $request->boolean('soft', true);
            if ($soft) {
                $user->active = false;
                $user->save();
                return response()->json([
                    'success' => true,
                    'message' => 'Usuario desactivado correctamente',
                ]);
            }

            $user->delete();
            return response()->json([
                'success' => true,
                'message' => 'Usuario eliminado correctamente',
            ]);
        } catch (Exception $e) {
            Log::error('Error al eliminar usuario', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar usuario',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    private function canAccessUser(User $target): bool
    {
        $auth = request()->user();
        if ($auth->hasRole('super_admin')) {
            return true;
        }
        if ($auth->hasRole('company_admin') && $auth->company_id && $auth->company_id === $target->company_id) {
            return true;
        }
        return $auth->id === $target->id;
    }

    private function userToArray(User $user, bool $full = false): array
    {
        $meta = $user->metadata ?? [];
        $base = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role_id' => $user->role_id,
            'role' => $user->role?->name,
            'role_display' => $user->role?->display_name,
            'company_id' => $user->company_id,
            'active' => $user->active,
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'created_at' => $user->created_at?->toIso8601String(),
        ];
        if ($full) {
            $base['user_type'] = $user->user_type;
            $base['permissions'] = $user->permissions ?? [];
            $base['company'] = $user->company?->razon_social ?? null;
            $base['phone'] = $meta['phone'] ?? null;
            $base['updated_at'] = $user->updated_at?->toIso8601String();
        }
        return $base;
    }
}
