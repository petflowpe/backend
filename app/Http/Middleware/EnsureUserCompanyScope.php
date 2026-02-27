<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Establece el company_id (y opcionalmente branch_id) efectivo para el request.
 * - Usuarios con company_id: usan su empresa; super_admin puede sobreescribir con header X-Company-Id.
 * - Evita confiar solo en body/query; fuente de verdad es el usuario o el header validado.
 */
class EnsureUserCompanyScope
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user) {
            return $next($request);
        }

        $companyId = null;
        $branchId = null;

        // Super admin puede elegir empresa vía header (útil para multi-tenant)
        $requestCompanyId = $request->header('X-Company-Id') ? (int) $request->header('X-Company-Id') : null;
        $requestBranchId = $request->header('X-Branch-Id') ? (int) $request->header('X-Branch-Id') : null;

        if ($user->hasRole('super_admin') && $requestCompanyId) {
            $companyId = $requestCompanyId;
            if ($requestBranchId) {
                $branchId = $requestBranchId;
            }
        }

        if ($companyId === null && $user->company_id) {
            $companyId = $user->company_id;
            // Branch solo si pertenece a la empresa del usuario
            if ($requestBranchId) {
                $branch = \App\Models\Branch::where('id', $requestBranchId)->where('company_id', $companyId)->first();
                if ($branch) {
                    $branchId = $requestBranchId;
                }
            }
        }

        // Exponer en el request para que los controladores usen request()->attributes
        $request->attributes->set('scope_company_id', $companyId);
        $request->attributes->set('scope_branch_id', $branchId);

        // Helpers disponibles en todo el request
        $request->merge([
            '_scope_company_id' => $companyId,
            '_scope_branch_id' => $branchId,
        ]);

        // Establecer locale del usuario para respuestas API
        if (!empty($user->locale)) {
            app()->setLocale($user->locale);
        }

        return $next($request);
    }
}
