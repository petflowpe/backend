<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\CompanyOnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;
use Exception;

class CompanyOnboardingController extends Controller
{
    public function __construct(private readonly CompanyOnboardingService $service)
    {
    }

    /**
     * POST /api/v1/company-onboardings
     * Solo super_admin. Crea empresa + sucursal + admin en una transacción.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Company::class);

        $validated = $request->validate([
            'company.ruc' => 'required|string|size:11|unique:companies,ruc',
            'company.razon_social' => 'required|string|max:255',
            'company.nombre_comercial' => 'nullable|string|max:255',
            'company.direccion' => 'required|string|max:255',
            'company.ubigeo' => 'nullable|string|size:6',
            'company.distrito' => 'nullable|string|max:100',
            'company.provincia' => 'nullable|string|max:100',
            'company.departamento' => 'nullable|string|max:100',
            'company.telefono' => 'nullable|string|max:20',
            'company.email' => 'required|email|max:255',
            'company.web' => 'nullable|url|max:255',
            'company.usuario_sol' => 'nullable|string|max:50',
            'company.clave_sol' => 'nullable|string|max:100',

            'branch.codigo' => 'nullable|string|max:10',
            'branch.nombre' => 'nullable|string|max:120',
            'branch.direccion' => 'nullable|string|max:255',

            'admin.name' => 'required|string|max:255',
            'admin.email' => 'required|email|max:255|unique:users,email',
            'admin.password' => ['required', Password::min(8)->letters()->mixedCase()->numbers()],

            'working_hours' => 'nullable|array',
        ], [
            'company.ruc.unique' => 'El RUC ya está registrado',
            'admin.email.unique' => 'El correo del administrador ya está registrado',
        ]);

        try {
            $result = $this->service->onboard($validated);

            return response()->json([
                'success' => true,
                'message' => 'Empresa onboarded exitosamente',
                'data' => [
                    'company' => $result['company'],
                    'branch' => $result['branch'],
                    'admin' => [
                        'id' => $result['admin']->id,
                        'name' => $result['admin']->name,
                        'email' => $result['admin']->email,
                        'role' => $result['admin']->role?->name,
                        'company_id' => $result['admin']->company_id,
                    ],
                    'next_steps' => [
                        'Configurar SUNAT (certificado / SOL) cuando vayan a emitir CPE',
                        'Crear servicios y zonas de cobertura',
                        'Invitar staff / conductores desde Usuarios',
                    ],
                ],
            ], 201);
        } catch (Exception $e) {
            Log::error('Error en company onboarding', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo completar el onboarding: ' . $e->getMessage(),
            ], 500);
        }
    }
}
