<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\HandlesStaffAuthorization;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    use HandlesStaffAuthorization;

    public function index(Request $request): JsonResponse
    {
        try {
            $authUser = $request->user();
            if (!$this->canListUsers($authUser)) {
                return $this->denyStaff('No tiene permiso para listar usuarios');
            }

            $query = User::with(['role:id,name,display_name'])
                ->select(['id', 'name', 'email', 'role_id', 'company_id', 'active', 'last_login_at', 'created_at', 'metadata']);

            $scopeCompanyId = $this->scopedCompanyId($request, $authUser);
            if ($scopeCompanyId) {
                $query->where('company_id', $scopeCompanyId);
            } elseif (!$authUser->hasRole('super_admin')) {
                return $this->denyStaff('No tiene empresa asignada para consultar usuarios');
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

            $data = $users->getCollection()->map(fn (User $user) => $this->userToArray($user));

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

    public function show(int $id): JsonResponse
    {
        try {
            $user = User::with(['role', 'company'])->findOrFail($id);

            if (!$this->canAccessUser($user)) {
                return $this->denyStaff('No tiene permiso para ver este usuario');
            }

            return response()->json([
                'success' => true,
                'data' => $this->userToArray($user, true),
            ]);
        } catch (ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado',
            ], 404);
        } catch (Exception $e) {
            Log::error('Error al obtener usuario', ['id' => $id, 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener usuario',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

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
            'phone' => 'nullable|string|max:40',
            'initials' => 'nullable|string|max:20',
            'all_branches_access' => 'nullable|boolean',
            'branch_ids' => 'nullable|array',
            'branch_ids.*' => 'integer|exists:branches,id',
        ]);

        try {
            $authUser = $request->user();
            if (!$this->canCreateUsers($authUser)) {
                return $this->denyStaff('No tiene permiso para crear usuarios');
            }

            if ($response = $this->assertAssignableRole($authUser, (int) $request->role_id)) {
                return $response;
            }

            $newCompanyId = (int) ($request->input('company_id') ?? $authUser->company_id);
            if (!$authUser->hasRole('super_admin') && $authUser->company_id && $newCompanyId !== (int) $authUser->company_id) {
                return $this->denyStaff('No puede crear usuarios en otra empresa');
            }

            $allowedBranchIds = $newCompanyId > 0
                ? Branch::where('company_id', $newCompanyId)->where('activo', true)->pluck('id')->all()
                : [];

            $meta = [];
            if ($request->filled('phone')) {
                $meta['phone'] = $request->string('phone')->toString();
            }
            if ($request->filled('initials')) {
                $meta['initials'] = trim($request->string('initials')->toString());
            }
            $allAccess = $request->boolean('all_branches_access', true);
            if ($request->has('all_branches_access')) {
                $meta['all_branches_access'] = $allAccess;
            }
            $pickedBranchIds = [];
            if (!$allAccess) {
                $pickedBranchIds = array_values(array_unique(array_intersect(
                    array_map('intval', $request->input('branch_ids', [])),
                    $allowedBranchIds
                )));
                $meta['branch_ids'] = $pickedBranchIds;
            } else {
                unset($meta['branch_ids']);
            }

            if ($response = $this->assertBranchSelection($newCompanyId, $allAccess, $pickedBranchIds)) {
                return $response;
            }

            $permissions = $this->normalizeUserPermissions($request->input('permissions'));

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => $request->password,
                'role_id' => $request->role_id,
                'company_id' => $newCompanyId ?: null,
                'user_type' => $request->user_type ?? 'user',
                'active' => $request->boolean('active', true),
                'permissions' => $permissions,
                'password_changed_at' => now(),
                'metadata' => $meta,
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

    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $user = User::with('role')->findOrFail($id);
        } catch (ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado',
            ], 404);
        }

        if (!$this->canAccessUser($user)) {
            return $this->denyStaff('No tiene permiso para editar este usuario');
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
            'phone' => 'nullable|string|max:40',
            'initials' => 'nullable|string|max:20',
            'all_branches_access' => 'nullable|boolean',
            'branch_ids' => 'nullable|array',
            'branch_ids.*' => 'integer|exists:branches,id',
        ]);

        try {
            $authUser = $request->user();
            if (!$this->canUpdateUsers($authUser)) {
                return $this->denyStaff('No tiene permiso para editar usuarios');
            }

            if ($request->has('role_id')) {
                if ($response = $this->assertAssignableRole($authUser, (int) $request->role_id)) {
                    return $response;
                }
            }

            if ($request->has('company_id') && !$authUser->hasRole('super_admin')) {
                if ((int) $request->company_id !== (int) $authUser->company_id) {
                    return $this->denyStaff('No puede mover usuarios a otra empresa');
                }
            }

            if ($request->filled('name')) {
                $user->name = $request->name;
            }
            if ($request->filled('email')) {
                $user->email = $request->email;
            }
            if ($request->filled('password')) {
                $user->password = $request->password;
                $user->password_changed_at = now();
            }
            if ($request->has('role_id')) {
                $user->role_id = $request->role_id;
            }
            if ($request->has('company_id') && $authUser->hasRole('super_admin')) {
                $user->company_id = $request->company_id;
            }
            if ($request->has('active')) {
                if ($user->id === $authUser->id && !$request->boolean('active')) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No puede desactivar su propia cuenta',
                    ], 422);
                }
                $user->active = $request->boolean('active');
            }
            if ($request->has('permissions')) {
                $user->permissions = $this->normalizeUserPermissions($request->input('permissions'));
            }

            if ($request->has('phone') || $request->has('initials') || $request->has('all_branches_access') || $request->has('branch_ids')) {
                $meta = $user->metadata ?? [];
                if ($request->has('phone')) {
                    $phone = $request->input('phone');
                    if ($phone === null || $phone === '') {
                        unset($meta['phone']);
                    } else {
                        $meta['phone'] = $phone;
                    }
                }
                if ($request->has('initials')) {
                    $initials = $request->input('initials');
                    if ($initials === null || trim((string) $initials) === '') {
                        unset($meta['initials']);
                    } else {
                        $meta['initials'] = trim((string) $initials);
                    }
                }
                if ($request->has('all_branches_access')) {
                    $meta['all_branches_access'] = $request->boolean('all_branches_access');
                }
                $companyIdForBranches = (int) ($user->company_id ?? 0);
                $allowedBranchIds = $companyIdForBranches > 0
                    ? Branch::where('company_id', $companyIdForBranches)->where('activo', true)->pluck('id')->all()
                    : [];
                $allAccess = (bool) ($meta['all_branches_access'] ?? ($user->metadata['all_branches_access'] ?? true));
                $pickedBranchIds = [];
                if ($allAccess) {
                    unset($meta['branch_ids']);
                } elseif ($request->has('branch_ids')) {
                    $pickedBranchIds = array_values(array_unique(array_intersect(
                        array_map('intval', $request->input('branch_ids', [])),
                        $allowedBranchIds
                    )));
                    $meta['branch_ids'] = $pickedBranchIds;
                } else {
                    $pickedBranchIds = array_map('intval', $meta['branch_ids'] ?? []);
                }

                if ($response = $this->assertBranchSelection($companyIdForBranches, $allAccess, $pickedBranchIds)) {
                    return $response;
                }

                $user->metadata = $meta;
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

    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $user = User::findOrFail($id);
        } catch (ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado',
            ], 404);
        }

        if ($user->id === $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'No puede eliminar su propia cuenta',
            ], 422);
        }

        if (!$this->canAccessUser($user)) {
            return $this->denyStaff('No tiene permiso para eliminar este usuario');
        }

        try {
            $authUser = $request->user();
            if (!$this->canDeleteUsers($authUser)) {
                return $this->denyStaff('No tiene permiso para eliminar usuarios');
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

            if (!$authUser->hasRole('super_admin')) {
                return $this->denyStaff('Solo super administrador puede eliminar usuarios permanentemente');
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
        if ($auth->hasPermission('users.manage') && $auth->company_id && $auth->company_id === $target->company_id) {
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
            'phone' => $meta['phone'] ?? null,
            'initials' => $meta['initials'] ?? null,
            'all_branches_access' => array_key_exists('all_branches_access', $meta)
                ? (bool) $meta['all_branches_access']
                : null,
            'branch_ids' => isset($meta['branch_ids']) && is_array($meta['branch_ids'])
                ? array_values(array_map('intval', $meta['branch_ids']))
                : null,
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'created_at' => $user->created_at?->toIso8601String(),
        ];
        if ($full) {
            $base['user_type'] = $user->user_type;
            $base['permissions'] = $user->permissions ?? [];
            $base['company'] = $user->company?->razon_social ?? null;
            $base['updated_at'] = $user->updated_at?->toIso8601String();
        }

        return $base;
    }
}
