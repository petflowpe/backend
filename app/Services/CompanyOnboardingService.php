<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Company;
use App\Models\CompanyConfiguration;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompanyOnboardingService
{
    /**
     * Alta SaaS atómica: empresa + sucursal principal + company_admin + horarios base.
     *
     * @param  array{
     *   company: array,
     *   branch?: array,
     *   admin: array{name:string,email:string,password:string},
     *   working_hours?: array|null
     * }  $payload
     * @return array{company: Company, branch: Branch, admin: User}
     */
    public function onboard(array $payload): array
    {
        return DB::transaction(function () use ($payload) {
            $companyData = $payload['company'];
            $branchData = $payload['branch'] ?? [];
            $adminData = $payload['admin'];

            $role = Role::where('name', 'company_admin')->first();
            if (!$role) {
                throw ValidationException::withMessages([
                    'admin' => 'No existe el rol company_admin. Ejecute el seeder de roles.',
                ]);
            }

            $company = Company::create([
                'ruc' => $companyData['ruc'],
                'razon_social' => $companyData['razon_social'],
                'nombre_comercial' => $companyData['nombre_comercial'] ?? $companyData['razon_social'],
                'direccion' => $companyData['direccion'],
                'ubigeo' => $companyData['ubigeo'] ?? '150101',
                'distrito' => $companyData['distrito'] ?? 'Lima',
                'provincia' => $companyData['provincia'] ?? 'Lima',
                'departamento' => $companyData['departamento'] ?? 'Lima',
                'telefono' => $companyData['telefono'] ?? null,
                'email' => $companyData['email'],
                'web' => $companyData['web'] ?? null,
                // SUNAT no bloquea onboarding: se completa después en configuración.
                'usuario_sol' => $companyData['usuario_sol'] ?? 'DEMO',
                'clave_sol' => $companyData['clave_sol'] ?? 'DEMO',
                'endpoint_beta' => $companyData['endpoint_beta']
                    ?? 'https://e-beta.sunat.gob.pe/ol-ti-itcpfegem-beta/billService',
                'endpoint_produccion' => $companyData['endpoint_produccion']
                    ?? 'https://e-factura.sunat.gob.pe/ol-ti-itcpfegem/billService',
                'modo_produccion' => false,
                'activo' => true,
            ]);

            $branch = Branch::create([
                'company_id' => $company->id,
                'codigo' => $branchData['codigo'] ?? '001',
                'nombre' => $branchData['nombre'] ?? 'Sucursal Principal',
                'direccion' => $branchData['direccion'] ?? $company->direccion,
                'ubigeo' => $branchData['ubigeo'] ?? $company->ubigeo,
                'distrito' => $branchData['distrito'] ?? $company->distrito,
                'provincia' => $branchData['provincia'] ?? $company->provincia,
                'departamento' => $branchData['departamento'] ?? $company->departamento,
                'telefono' => $branchData['telefono'] ?? $company->telefono,
                'email' => $branchData['email'] ?? $company->email,
                'series_factura' => $branchData['series_factura'] ?? ['F001'],
                'series_boleta' => $branchData['series_boleta'] ?? ['B001'],
                'series_nota_credito' => $branchData['series_nota_credito'] ?? ['FC01'],
                'series_nota_debito' => $branchData['series_nota_debito'] ?? ['FD01'],
                'series_guia_remision' => $branchData['series_guia_remision'] ?? ['T001'],
                'activo' => true,
            ]);

            $admin = User::create([
                'name' => $adminData['name'],
                'email' => strtolower(trim($adminData['email'])),
                'password' => $adminData['password'], // cast hashed
                'role_id' => $role->id,
                'company_id' => $company->id,
                'user_type' => 'user',
                'active' => true,
                'email_verified_at' => now(),
                'password_changed_at' => now(),
                'metadata' => [
                    'all_branches_access' => true,
                    'onboarded_via' => 'company-onboarding',
                ],
            ]);

            $workingHours = $payload['working_hours'] ?? $this->defaultWorkingHours();
            CompanyConfiguration::withoutGlobalScopes()->updateOrCreate(
                [
                    'company_id' => $company->id,
                    'config_type' => 'document_settings',
                    'environment' => 'beta',
                ],
                [
                    'service_type' => 'facturacion',
                    'config_data' => [
                        'working_hours' => $workingHours,
                        'onboarding_completed_at' => now()->toIso8601String(),
                    ],
                    'is_active' => true,
                    'description' => 'Configuración inicial de onboarding SaaS',
                    'priority' => 1,
                ]
            );

            return [
                'company' => $company->fresh(['branches']),
                'branch' => $branch,
                'admin' => $admin->load('role'),
            ];
        });
    }

    private function defaultWorkingHours(): array
    {
        return [
            'monday' => ['open' => true, 'start' => '08:00', 'end' => '18:00'],
            'tuesday' => ['open' => true, 'start' => '08:00', 'end' => '18:00'],
            'wednesday' => ['open' => true, 'start' => '08:00', 'end' => '18:00'],
            'thursday' => ['open' => true, 'start' => '08:00', 'end' => '18:00'],
            'friday' => ['open' => true, 'start' => '08:00', 'end' => '18:00'],
            'saturday' => ['open' => true, 'start' => '09:00', 'end' => '14:00'],
            'sunday' => ['open' => false, 'start' => '00:00', 'end' => '00:00'],
        ];
    }
}
