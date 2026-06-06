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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
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
            'document_type' => 'nullable|in:DNI,CE,RUC,PASS',
            'document_number' => 'nullable|string|max:30|regex:/^[A-Za-z0-9.-]+$/|unique:users,document_number',
            'all_branches_access' => 'nullable|boolean',
            'branch_ids' => 'nullable|array',
            'branch_ids.*' => 'integer|exists:branches,id',
        ], [
            'document_number.regex' => 'El número de documento sólo admite letras, números, puntos y guiones.',
            'document_number.unique' => 'El número de documento ya está registrado.',
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

            $docNumber = trim((string) $request->input('document_number', ''));
            $docType = $request->input('document_type');

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'document_type' => $docNumber !== '' ? $docType : null,
                'document_number' => $docNumber !== '' ? $docNumber : null,
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
            'document_type' => 'sometimes|nullable|in:DNI,CE,RUC,PASS',
            'document_number' => [
                'sometimes',
                'nullable',
                'string',
                'max:30',
                'regex:/^[A-Za-z0-9.-]+$/',
                'unique:users,document_number,' . $id,
            ],
            'all_branches_access' => 'nullable|boolean',
            'branch_ids' => 'nullable|array',
            'branch_ids.*' => 'integer|exists:branches,id',
        ], [
            'document_number.regex' => 'El número de documento sólo admite letras, números, puntos y guiones.',
            'document_number.unique' => 'El número de documento ya está registrado.',
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
                if (!$request->boolean('active') && $this->isLastActiveSuperAdmin($user)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No puede desactivar al último super administrador activo del sistema',
                    ], 422);
                }
                $user->active = $request->boolean('active');
            }

            // Si cambia el rol y el usuario era el último super_admin, bloquear el cambio.
            if ($request->has('role_id') && (int) $request->role_id !== (int) $user->getOriginal('role_id')) {
                $oldRoleId = (int) $user->getOriginal('role_id');
                $superAdminRoleId = Role::where('name', 'super_admin')->value('id');
                if ($superAdminRoleId && $oldRoleId === (int) $superAdminRoleId && $this->isLastActiveSuperAdmin($user)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No puede quitar el rol super administrador al último super administrador activo',
                    ], 422);
                }
            }
            if ($request->has('permissions')) {
                $user->permissions = $this->normalizeUserPermissions($request->input('permissions'));
            }

            if ($request->has('document_number') || $request->has('document_type')) {
                $newDocNumber = $request->has('document_number') ? trim((string) $request->input('document_number')) : ($user->document_number ?? '');
                $newDocType = $request->has('document_type') ? $request->input('document_type') : $user->document_type;
                if ($newDocNumber === '') {
                    $user->document_number = null;
                    $user->document_type = null;
                } else {
                    $user->document_number = $newDocNumber;
                    $user->document_type = $newDocType ?: 'DNI';
                }
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

            // Detectar cambios críticos ANTES de save (Eloquent isDirty pierde estado tras save).
            $shouldRevoke = $user->id !== $authUser->id && (
                $user->isDirty('active') && !$user->active
                || $user->isDirty('role_id')
                || $user->isDirty('password')
            );

            $user->save();

            // Si el cambio fue crítico, cerramos las sesiones del usuario afectado
            // para que el cambio de rol/permisos/contraseña/desactivación tome efecto inmediato.
            $revoked = 0;
            if ($shouldRevoke) {
                $revoked = $this->revokeUserTokens($user);
                Log::info('Auto-revoke tras update', [
                    'admin_id' => $authUser->id,
                    'target_id' => $user->id,
                    'revoked' => $revoked,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Usuario actualizado correctamente'
                    . ($revoked > 0 ? ' (sesiones cerradas: ' . $revoked . ')' : ''),
                'data' => $this->userToArray($user->fresh('role')),
                'sessions_revoked' => $revoked,
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

        if ($this->isLastActiveSuperAdmin($user)) {
            return response()->json([
                'success' => false,
                'message' => 'No puede eliminar al último super administrador activo del sistema',
            ], 422);
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
            'document_type' => $user->document_type,
            'document_number' => $user->document_number,
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

    /**
     * Determina si el usuario dado es el último super_admin activo del sistema.
     * Si lo es, no se puede desactivar / eliminar / quitarle el rol.
     *
     * IMPORTANTE: lee el estado fresco desde la base de datos (no del modelo en memoria),
     * porque suele invocarse después de haber asignado campos al modelo en update().
     */
    private function isLastActiveSuperAdmin(User $user): bool
    {
        $superRoleId = Role::where('name', 'super_admin')->value('id');
        if (!$superRoleId) {
            return false;
        }

        $fresh = User::query()
            ->select(['id', 'role_id', 'active'])
            ->find($user->id);
        if (!$fresh) {
            return false;
        }
        if ((int) $fresh->role_id !== (int) $superRoleId) {
            return false;
        }
        if (!$fresh->active) {
            return false;
        }

        $activeCount = User::where('role_id', $superRoleId)
            ->where('active', true)
            ->count();

        return $activeCount <= 1;
    }

    /**
     * Reset de contraseña iniciado por un administrador.
     * - Si el admin envía 'password' en el body: setea esa contraseña.
     * - Si no envía nada: genera un token de recuperación válido por
     *   config('auth.passwords.users.expire') minutos y trata de enviarlo
     *   por correo al usuario. En debug devuelve el link en la respuesta.
     *
     * POST /api/v1/users/{id}/reset-password
     * body opcional: { "password": "Nueva123Segura" }
     */
    public function resetPassword(Request $request, int $id): JsonResponse
    {
        try {
            $user = User::findOrFail($id);
        } catch (ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado',
            ], 404);
        }

        $authUser = $request->user();
        if (!$this->canUpdateUsers($authUser)) {
            return $this->denyStaff('No tiene permiso para resetear contraseñas');
        }
        if (!$this->canAccessUser($user)) {
            return $this->denyStaff('No tiene permiso para resetear la contraseña de este usuario');
        }

        $request->validate([
            'password' => ['nullable', Password::min(8)->letters()->mixedCase()->numbers()],
        ]);

        try {
            // Modo 1: admin define la nueva contraseña directamente.
            if ($request->filled('password')) {
                $user->password = $request->password;
                $user->password_changed_at = now();
                $user->save();

                $this->revokeUserTokens($user);

                Log::info('Reset password directo por admin', [
                    'admin_id' => $authUser->id,
                    'target_id' => $user->id,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Contraseña actualizada. El usuario debe iniciar sesión nuevamente.',
                    'mode' => 'manual',
                ]);
            }

            // Modo 2: enviar enlace de recuperación al email del usuario.
            $token = Str::random(64);
            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $user->email],
                [
                    'token' => Hash::make($token),
                    'created_at' => now(),
                ]
            );

            $expireMinutes = (int) config('auth.passwords.users.expire', 60);
            $resetUrl = (config('app.frontend_url') ?: config('app.url'))
                . '/reset-password?token=' . urlencode($token)
                . '&email=' . urlencode($user->email);

            $body = "Hola {$user->name},\n\n"
                . "Un administrador ha solicitado restablecer tu contraseña en " . config('app.name') . ".\n"
                . "Usa este enlace (válido {$expireMinutes} minutos): {$resetUrl}\n\n"
                . "Si no esperabas este correo, ignóralo.";

            $mailSent = false;
            try {
                Mail::raw($body, function ($message) use ($user) {
                    $message->to($user->email)
                        ->subject('Recuperación de contraseña - ' . config('app.name'));
                });
                $mailSent = true;
            } catch (\Throwable $e) {
                Log::warning('No se pudo enviar email de reset por admin: ' . $e->getMessage());
            }

            Log::info('Reset password con email por admin', [
                'admin_id' => $authUser->id,
                'target_id' => $user->id,
                'mail_sent' => $mailSent,
            ]);

            $payload = [
                'success' => true,
                'message' => $mailSent
                    ? 'Enlace de recuperación enviado por correo al usuario.'
                    : 'Enlace generado. No se pudo enviar el correo (revisar configuración de mail).',
                'mode' => 'email',
                'mail_sent' => $mailSent,
            ];

            // En desarrollo devolvemos el link para poder probarlo sin SMTP.
            if (config('app.debug')) {
                $payload['reset_url'] = $resetUrl;
                $payload['expires_in_minutes'] = $expireMinutes;
            }

            return response()->json($payload);
        } catch (Exception $e) {
            Log::error('Error en resetPassword admin', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al resetear la contraseña',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Revoca todos los tokens (sesiones API) de un usuario.
     * Útil tras: cambio de rol, cambio de contraseña, desactivación o sospecha de fuga.
     *
     * POST /api/v1/users/{id}/revoke-tokens
     */
    public function revokeTokens(Request $request, int $id): JsonResponse
    {
        try {
            $user = User::findOrFail($id);
        } catch (ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado',
            ], 404);
        }

        $authUser = $request->user();
        if (!$this->canUpdateUsers($authUser)) {
            return $this->denyStaff('No tiene permiso para cerrar sesiones de otros usuarios');
        }
        if (!$this->canAccessUser($user)) {
            return $this->denyStaff('No tiene permiso sobre este usuario');
        }

        // No permitir cerrarte tus propias sesiones desde este endpoint (usa /auth/logout).
        if ($user->id === $authUser->id) {
            return response()->json([
                'success' => false,
                'message' => 'Para cerrar tu propia sesión usa /auth/logout',
            ], 422);
        }

        $count = $this->revokeUserTokens($user);

        Log::info('Tokens revocados por admin', [
            'admin_id' => $authUser->id,
            'target_id' => $user->id,
            'revoked' => $count,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Se cerraron {$count} sesión(es) del usuario.",
            'revoked' => $count,
        ]);
    }

    /** Elimina todos los Personal Access Tokens del usuario y devuelve cuántos se borraron. */
    private function revokeUserTokens(User $user): int
    {
        try {
            return (int) $user->tokens()->delete();
        } catch (\Throwable $e) {
            Log::warning('No se pudieron revocar tokens: ' . $e->getMessage());

            return 0;
        }
    }
}
