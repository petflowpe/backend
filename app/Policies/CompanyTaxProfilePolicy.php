<?php

namespace App\Policies;

use App\Models\CompanyTaxProfile;
use App\Models\User;

class CompanyTaxProfilePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, CompanyTaxProfile $profile): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        return (int) $user->company_id === (int) $profile->company_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, CompanyTaxProfile $profile): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        return (int) $user->company_id === (int) $profile->company_id;
    }

    public function delete(User $user, CompanyTaxProfile $profile): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        return (int) $user->company_id === (int) $profile->company_id;
    }
}

