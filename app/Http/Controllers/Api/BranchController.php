<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\HandlesStaffAuthorization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Branch\StoreBranchRequest;
use App\Http\Requests\Branch\UpdateBranchRequest;
use App\Models\Branch;
use App\Models\Company;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BranchController extends Controller
{
    use HandlesStaffAuthorization;

    public function index(Request $request): JsonResponse
    {
        try {
            $authUser = $request->user();
            if (!$this->canListUsers($authUser) && !$this->canViewRoles($authUser)) {
                return $this->denyStaff('No tiene permiso para consultar sucursales');
            }

            $query = Branch::with(['company:id,ruc,razon_social']);

            $scopeCompanyId = $this->scopedCompanyId($request, $authUser);
            if ($scopeCompanyId) {
                $query->where('company_id', $scopeCompanyId);
            } elseif ($request->has('company_id')) {
                $requestedCompanyId = (int) $request->company_id;
                if (!$this->canAccessBranchCompany($authUser, $requestedCompanyId)) {
                    return $this->denyStaff('No tiene permiso para consultar sucursales de esa empresa');
                }
                $query->where('company_id', $requestedCompanyId);
            } elseif (!$authUser->hasRole('super_admin')) {
                return $this->denyStaff('Debe indicar la empresa para consultar sucursales');
            }

            $branches = $query->get();

            return response()->json([
                'success' => true,
                'data' => $branches,
                'meta' => [
                    'total' => $branches->count(),
                    'companies_count' => $branches->unique('company_id')->count(),
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Error al listar sucursales', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener sucursales',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function store(StoreBranchRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $authUser = $request->user();
            $companyId = (int) $validated['company_id'];

            if (!$this->canMutateBranches($authUser, $companyId)) {
                return $this->denyStaff('No tiene permiso para crear sucursales');
            }

            $company = Company::where('id', $companyId)->where('activo', true)->first();
            if (!$company) {
                return response()->json([
                    'success' => false,
                    'message' => 'La empresa especificada no existe o está inactiva',
                ], 404);
            }

            $branch = Branch::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Sucursal creada exitosamente',
                'data' => $branch->load('company:id,ruc,razon_social'),
            ], 201);
        } catch (Exception $e) {
            Log::error('Error al crear sucursal', [
                'request_data' => $validated ?? [],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al crear sucursal',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function show(Branch $branch): JsonResponse
    {
        try {
            if (!$this->canAccessBranchCompany(request()->user(), (int) $branch->company_id)) {
                return $this->denyStaff('No tiene permiso para ver esta sucursal');
            }

            $branch->load(['company:id,ruc,razon_social,nombre_comercial']);

            return response()->json([
                'success' => true,
                'data' => $branch,
            ]);
        } catch (Exception $e) {
            Log::error('Error al obtener sucursal', [
                'branch_id' => $branch->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener sucursal',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function update(UpdateBranchRequest $request, Branch $branch): JsonResponse
    {
        try {
            $validated = $request->validated();
            $authUser = $request->user();
            $targetCompanyId = (int) ($validated['company_id'] ?? $branch->company_id);

            if (!$this->canMutateBranches($authUser, (int) $branch->company_id)) {
                return $this->denyStaff('No tiene permiso para editar sucursales');
            }

            if ($targetCompanyId !== (int) $branch->company_id && !$authUser->hasRole('super_admin')) {
                return $this->denyStaff('No puede mover la sucursal a otra empresa');
            }

            if (array_key_exists('nombre', $validated)) {
                $validated['nombre'] = trim((string) $validated['nombre']);
            }

            if (isset($validated['company_id'])) {
                $company = Company::where('id', $validated['company_id'])->where('activo', true)->first();
                if (!$company) {
                    return response()->json([
                        'success' => false,
                        'message' => 'La empresa especificada no existe o está inactiva',
                    ], 404);
                }
            }

            $branch->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Sucursal actualizada exitosamente',
                'data' => $branch->fresh()->load('company:id,ruc,razon_social'),
            ]);
        } catch (Exception $e) {
            Log::error('Error al actualizar sucursal', [
                'branch_id' => $branch->id,
                'request_data' => $validated ?? [],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar sucursal',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function destroy(Branch $branch): JsonResponse
    {
        try {
            if (!$this->canMutateBranches(request()->user(), (int) $branch->company_id)) {
                return $this->denyStaff('No tiene permiso para desactivar sucursales');
            }

            $branch->update(['activo' => false]);

            return response()->json([
                'success' => true,
                'message' => 'Sucursal desactivada exitosamente',
            ]);
        } catch (Exception $e) {
            Log::error('Error al desactivar sucursal', [
                'branch_id' => $branch->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al desactivar sucursal',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function activate(Branch $branch): JsonResponse
    {
        try {
            if (!$this->canMutateBranches(request()->user(), (int) $branch->company_id)) {
                return $this->denyStaff('No tiene permiso para activar sucursales');
            }

            $branch->update(['activo' => true]);

            return response()->json([
                'success' => true,
                'message' => 'Sucursal activada exitosamente',
                'data' => $branch->load('company:id,ruc,razon_social'),
            ]);
        } catch (Exception $e) {
            Log::error('Error al activar sucursal', [
                'branch_id' => $branch->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al activar sucursal',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function getByCompany(Company $company): JsonResponse
    {
        try {
            if (!$this->canAccessBranchCompany(request()->user(), (int) $company->id)) {
                return $this->denyStaff('No tiene permiso para consultar sucursales de esta empresa');
            }

            $branches = $company->branches()
                ->select([
                    'id', 'company_id', 'nombre', 'direccion',
                    'distrito', 'provincia', 'departamento',
                    'telefono', 'email', 'activo',
                    'created_at', 'updated_at',
                ])
                ->get();

            return response()->json([
                'success' => true,
                'data' => $branches,
                'meta' => [
                    'company_id' => $company->id,
                    'company_name' => $company->razon_social,
                    'total_branches' => $branches->count(),
                    'active_branches' => $branches->where('activo', true)->count(),
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Error al obtener sucursales por empresa', [
                'company_id' => $company->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener sucursales',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
