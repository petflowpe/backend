<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

class CompanyPolicy
{
    /**
     * Super admin puede ver cualquier empresa; otros solo la suya.
     */
    public function view(User $user, Company $company): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }
        return (int) $user->company_id === (int) $company->id;
    }

    public function viewAny(User $user): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        return $user->company_id !== null
            && (
                $user->hasRole('company_admin')
                || $user->hasPermission('companies.view')
                || $user->hasPermission('companies.manage')
            );
    }

    public function create(User $user): bool
    {
        return $user->hasRole('super_admin');
    }

    public function update(User $user, Company $company): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        if ((int) $user->company_id !== (int) $company->id) {
            return false;
        }

        return $user->hasRole('company_admin')
            || $user->hasPermission('companies.manage');
    }

    public function delete(User $user, Company $company): bool
    {
        return $user->hasRole('super_admin');
    }

    /** Activar / desactivar empresa (solo super_admin). */
    public function activate(User $user, Company $company): bool
    {
        return $user->hasRole('super_admin');
    }

    /** Cambiar modo producción / beta. */
    public function toggleProduction(User $user, Company $company): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        return (int) $user->company_id === (int) $company->id
            && $user->hasRole('company_admin');
    }
}
