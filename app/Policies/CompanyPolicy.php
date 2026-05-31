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
        return true;
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
        return (int) $user->company_id === (int) $company->id;
    }

    public function delete(User $user, Company $company): bool
    {
        return $user->hasRole('super_admin');
    }
}
