<?php

namespace App\Helpers;

use Illuminate\Http\Request;

class ScopeHelper
{
    /**
     * Company ID efectivo para el request (usuario o header validado para super_admin).
     */
    public static function companyId(Request $request): ?int
    {
        return $request->attributes->get('scope_company_id')
            ?? $request->get('_scope_company_id')
            ?? $request->user()?->company_id;
    }

    /**
     * Branch ID efectivo; debe pertenecer a la empresa del usuario.
     */
    public static function branchId(Request $request): ?int
    {
        return $request->attributes->get('scope_branch_id')
            ?? $request->get('_scope_branch_id');
    }

    /**
     * Validar que branch_id pertenezca a company_id (del scope o dado).
     */
    public static function branchBelongsToCompany(int $branchId, int $companyId): bool
    {
        return \App\Models\Branch::where('id', $branchId)->where('company_id', $companyId)->exists();
    }
}
