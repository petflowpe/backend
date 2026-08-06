<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Global scope que filtra automaticamente cualquier modelo multi-tenant
 * por el company_id del usuario autenticado / scope del request.
 *
 * Reglas:
 *  - Si el middleware dejó `scope_company_id` en el request: filtrar por ese valor.
 *  - Si no hay usuario autenticado (CLI, jobs, contexto sin auth): no filtra.
 *  - Si el usuario es super_admin sin scope: no filtra (puede ver todas las empresas).
 *  - Si el usuario tiene company_id: filtra estricto por table.company_id.
 *  - Si el usuario NO tiene company_id (y no es super_admin): no devuelve nada.
 *
 * Para casos legitimos (jobs cross-tenant, comandos artisan, super admin sin
 * scope), usar Model::withoutGlobalScope(CompanyScope::class).
 */
class CompanyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $table = $model->getTable();
        $column = $table . '.company_id';

        // Preferir el scope establecido por EnsureUserCompanyScope (incluye X-Company-Id).
        try {
            $request = request();
            if ($request) {
                $scoped = $request->attributes->get('scope_company_id');
                if ($scoped !== null && $scoped !== '') {
                    $builder->where($column, (int) $scoped);
                    return;
                }
            }
        } catch (\Throwable $e) {
            // Sin contenedor HTTP (algunos contextos CLI): continuar con Auth.
        }

        if (!Auth::hasUser()) {
            return;
        }

        $user = Auth::user();
        if (!$user) {
            return;
        }

        if (method_exists($user, 'hasRole') && $user->hasRole('super_admin')) {
            return;
        }

        $companyId = $user->company_id ?? null;

        if ($companyId) {
            $builder->where($column, (int) $companyId);
        } else {
            $builder->whereRaw('1 = 0');
        }
    }
}
