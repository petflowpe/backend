<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Global scope que filtra automaticamente cualquier modelo multi-tenant
 * por el company_id del usuario autenticado.
 *
 * Reglas:
 *  - Si no hay usuario autenticado (CLI, jobs, contexto sin auth): no filtra.
 *  - Si el usuario es super_admin: no filtra (puede ver todas las empresas).
 *  - Si el usuario tiene company_id: filtra estricto por table.company_id.
 *  - Si el usuario NO tiene company_id (y no es super_admin): no devuelve nada.
 *
 * Para casos legitimos (jobs cross-tenant, comandos artisan, super admin sin
 * scope), usar Model::withoutGlobalScope(CompanyScope::class) o
 * Model::allCompanies() expuesto desde el trait.
 */
class CompanyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        // Sin auth (CLI, jobs, schedulers): no filtramos.
        if (!Auth::hasUser()) {
            return;
        }

        $user = Auth::user();
        if (!$user) {
            return;
        }

        // super_admin ve todas las empresas (puede usar X-Company-Id para acotar).
        if (method_exists($user, 'hasRole') && $user->hasRole('super_admin')) {
            return;
        }

        $companyId = $user->company_id ?? null;
        $table = $model->getTable();
        $column = $table . '.company_id';

        if ($companyId) {
            $builder->where($column, (int) $companyId);
        } else {
            // Usuario sin empresa (no super_admin) no debe ver nada de un modelo multi-tenant.
            $builder->whereRaw('1 = 0');
        }
    }
}
