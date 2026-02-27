<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;

class ClientPolicy
{
    /**
     * Super admin puede ver cualquier cliente; otros solo de su empresa.
     */
    public function view(User $user, Client $client): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }
        return (int) $user->company_id === (int) $client->company_id;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Client $client): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }
        return (int) $user->company_id === (int) $client->company_id;
    }

    public function delete(User $user, Client $client): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }
        return (int) $user->company_id === (int) $client->company_id;
    }
}
