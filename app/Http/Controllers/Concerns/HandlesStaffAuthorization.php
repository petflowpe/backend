<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Branch;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait HandlesStaffAuthorization
{
    /** Roles que solo super_admin puede asignar a otros usuarios. */
    private const PRIVILEGED_ASSIGNABLE_ROLES = ['super_admin', 'api_client'];

    protected function canListUsers(User $auth): bool
    {
        return $auth->hasRole('super_admin')
            || $auth->hasRole('company_admin')
            || $auth->hasPermission('users.manage')
            || $auth->hasPermission('users.view');
    }

    protected function canCreateUsers(User $auth): bool
    {
        return $auth->hasRole('super_admin')
            || $auth->hasRole('company_admin')
            || $auth->hasPermission('users.manage')
            || $auth->hasPermission('users.create');
    }

    protected function canUpdateUsers(User $auth): bool
    {
        return $auth->hasRole('super_admin')
            || $auth->hasRole('company_admin')
            || $auth->hasPermission('users.manage')
            || $auth->hasPermission('users.update');
    }

    protected function canDeleteUsers(User $auth): bool
    {
        return $auth->hasRole('super_admin')
            || $auth->hasRole('company_admin')
            || $auth->hasPermission('users.manage')
            || $auth->hasPermission('users.delete');
    }

    protected function canViewRoles(User $auth): bool
    {
        return $auth->hasRole('super_admin')
            || $auth->hasRole('company_admin')
            || $auth->hasPermission('users.roles')
            || $auth->hasPermission('users.manage');
    }

    protected function canManageRoles(User $auth): bool
    {
        return $auth->hasRole('super_admin')
            || $auth->hasPermission('users.roles')
            || $auth->hasPermission('users.manage');
    }

    /** company_admin puede gestionar roles custom, no roles de sistema protegidos. */
    protected function canManageRoleRecord(User $auth, Role $role, string $action = 'update'): bool
    {
        if ($auth->hasRole('super_admin')) {
            return true;
        }

        if (!$this->canManageRoles($auth)) {
            return false;
        }

        if ($auth->hasRole('company_admin')) {
            if ($role->is_system || in_array($role->name, ['super_admin', 'api_client'], true)) {
                return false;
            }

            return $action !== 'delete' || !$role->is_system;
        }

        return true;
    }

    protected function canAccessBranchCompany(User $auth, int $companyId): bool
    {
        if ($auth->hasRole('super_admin')) {
            return true;
        }

        if ($auth->company_id && (int) $auth->company_id === $companyId) {
            return $auth->hasRole('company_admin')
                || $auth->hasPermission('users.manage')
                || $auth->hasPermission('users.view');
        }

        return false;
    }

    protected function canMutateBranches(User $auth, int $companyId): bool
    {
        if ($auth->hasRole('super_admin')) {
            return true;
        }

        return $auth->company_id
            && (int) $auth->company_id === $companyId
            && (
                $auth->hasRole('company_admin')
                || $auth->hasPermission('users.manage')
            );
    }

    protected function scopedCompanyId(Request $request, User $auth): ?int
    {
        if ($auth->hasRole('super_admin')) {
            $requested = $request->integer('company_id');

            return $requested > 0 ? $requested : null;
        }

        return $auth->company_id ? (int) $auth->company_id : null;
    }

    protected function denyStaff(string $message = 'No tiene permiso para realizar esta acción'): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], 403);
    }

    protected function assertAssignableRole(User $auth, int $roleId): ?JsonResponse
    {
        $role = Role::query()->where('id', $roleId)->where('active', true)->first();
        if (!$role) {
            return response()->json([
                'success' => false,
                'message' => 'El rol seleccionado no existe o está inactivo',
            ], 422);
        }

        if ($auth->hasRole('super_admin')) {
            return null;
        }

        if (in_array($role->name, self::PRIVILEGED_ASSIGNABLE_ROLES, true)) {
            return $this->denyStaff('No puede asignar ese rol');
        }

        return null;
    }

    /**
     * @param  array<int, string>|null  $permissions
     */
    protected function normalizeUserPermissions(?array $permissions): ?array
    {
        if ($permissions === null) {
            return null;
        }

        $validNames = Permission::query()->where('active', true)->pluck('name')->all();
        $validSet = array_fill_keys($validNames, true);

        $normalized = [];
        foreach ($permissions as $permission) {
            $permission = trim((string) $permission);
            if ($permission === '') {
                continue;
            }
            if ($permission === '*' || isset($validSet[$permission])) {
                $normalized[] = $permission;
                continue;
            }
            if (str_ends_with($permission, '.*')) {
                $normalized[] = $permission;
            }
        }

        return array_values(array_unique($normalized));
    }

    protected function assertBranchSelection(int $companyId, bool $allAccess, array $branchIds): ?JsonResponse
    {
        if ($allAccess || $companyId <= 0) {
            return null;
        }

        if (count($branchIds) === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Debe seleccionar al menos una sede o activar acceso a todas las sedes',
            ], 422);
        }

        $allowed = Branch::query()
            ->where('company_id', $companyId)
            ->where('activo', true)
            ->whereIn('id', $branchIds)
            ->count();

        if ($allowed !== count(array_unique($branchIds))) {
            return response()->json([
                'success' => false,
                'message' => 'Una o más sedes no pertenecen a la empresa o están inactivas',
            ], 422);
        }

        return null;
    }
}
