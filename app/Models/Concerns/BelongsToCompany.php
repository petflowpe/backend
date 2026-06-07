<?php

namespace App\Models\Concerns;

use App\Models\Scopes\CompanyScope;

/**
 * Trait para todos los modelos multi-tenant (con columna company_id).
 *
 * Aplica el global scope CompanyScope: para usuarios autenticados que no son
 * super_admin, todas las queries del modelo se filtran automaticamente por
 * el company_id del usuario. Para super_admin / contexto sin auth (CLI, jobs,
 * schedulers), no se filtra.
 *
 * Para escapar del scope explicitamente (vista cross-tenant del super admin,
 * jobs, comandos artisan), usar:
 *
 *   Model::withoutGlobalScope(\App\Models\Scopes\CompanyScope::class)->...;
 *
 * Los modelos siguen definiendo su propia relacion company() y sus scopes
 * locales (scopeForCompany etc.); este trait NO los redefine para evitar
 * colisiones.
 */
trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope(new CompanyScope());
    }
}
