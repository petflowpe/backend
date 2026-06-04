<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Establece el company_id (y opcionalmente branch_id) efectivo para el request.
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

        $requestCompanyId = $request->header('X-Company-Id') ? (int) $request->header('X-Company-Id') : null;
        $requestBranchId = $request->header('X-Branch-Id') ? (int) $request->header('X-Branch-Id') : null;
        $bodyOrQueryCompanyId = $request->has('company_id') ? (int) $request->input('company_id') : null;
        $bodyOrQueryBranchId = $request->has('branch_id') ? (int) $request->input('branch_id') : null;

        if ($user->hasRole('super_admin') && $requestCompanyId) {
            $companyId = $requestCompanyId;
            if ($requestBranchId) {
                $branchId = $requestBranchId;
            }
        }

        if ($companyId === null && $user->company_id) {
            $companyId = (int) $user->company_id;
            if ($requestBranchId) {
                $branch = \App\Models\Branch::where('id', $requestBranchId)->where('company_id', $companyId)->first();
                if ($branch) {
                    $branchId = $requestBranchId;
                }
            }
        }

        if (!$user->hasRole('super_admin') && $user->company_id) {
            if ($requestCompanyId && (int) $requestCompanyId !== (int) $user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'No autorizado: company scope inválido',
                ], 403);
            }
            if ($bodyOrQueryCompanyId && (int) $bodyOrQueryCompanyId !== (int) $user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'No autorizado: company_id inválido',
                ], 403);
            }
        }

        if ($branchId === null && $bodyOrQueryBranchId && $companyId) {
            $branch = \App\Models\Branch::where('id', $bodyOrQueryBranchId)->where('company_id', $companyId)->first();
            if ($branch) {
                $branchId = $bodyOrQueryBranchId;
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No autorizado: branch_id inválido',
                ], 403);
            }
        }

        $request->attributes->set('scope_company_id', $companyId);
        $request->attributes->set('scope_branch_id', $branchId);

        $request->merge([
            '_scope_company_id' => $companyId,
            '_scope_branch_id' => $branchId,
        ]);

        if (!$user->hasRole('super_admin')) {
            $request->merge([
                'company_id' => $companyId,
                'branch_id' => $branchId,
            ]);
        }

        if (!empty($user->locale)) {
            app()->setLocale($user->locale);
        }

        return $next($request);
    }
}
