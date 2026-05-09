<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('pilot:setup
    {--fresh : Ejecuta migrate:fresh antes del setup}
    {--demo : Siembra datos demo (clientes/mascotas/catálogos)}
    {--company-ruc= : RUC de la empresa piloto}
    {--company-name= : Razón social de la empresa piloto}
    {--company-trade-name= : Nombre comercial}
    {--admin-email= : Email del admin de empresa}
    {--admin-password= : Password del admin de empresa}
', function () {
    $fresh = (bool) $this->option('fresh');
    $demo = (bool) $this->option('demo');

    $companyRuc = (string) ($this->option('company-ruc') ?: '20100000001');
    $companyName = (string) ($this->option('company-name') ?: 'PetFlow Piloto S.A.C.');
    $companyTradeName = (string) ($this->option('company-trade-name') ?: 'PetFlow');

    $adminEmail = (string) ($this->option('admin-email') ?: 'admin@petflow.com');
    $adminPassword = (string) ($this->option('admin-password') ?: 'PetFlow123456');

    $this->info('=== PetFlow: setup piloto ===');

    if ($fresh) {
        $this->warn('migrate:fresh...');
        Artisan::call('migrate:fresh', ['--force' => true]);
        $this->line(Artisan::output());
    } else {
        $this->warn('migrate...');
        Artisan::call('migrate', ['--force' => true]);
        $this->line(Artisan::output());
    }

    // Seeders de catálogos base (independientes)
    $this->warn('Seed: ubigeo/roles/monedas/módulos...');
    Artisan::call('db:seed', ['--class' => \Database\Seeders\UbiRegionesSeeder::class, '--force' => true]);
    Artisan::call('db:seed', ['--class' => \Database\Seeders\UbiProvinciasSeeder::class, '--force' => true]);
    Artisan::call('db:seed', ['--class' => \Database\Seeders\UbiDistritoSeeder::class, '--force' => true]);

    // Roles y permisos (sin usuarios por defecto)
    $rolesSeeder = new \Database\Seeders\RolesAndPermissionsSeeder();
    $rolesSeeder->runPermissionsAndRolesOnly();

    Artisan::call('db:seed', ['--class' => \Database\Seeders\CurrenciesSeeder::class, '--force' => true]);
    Artisan::call('db:seed', ['--class' => \Database\Seeders\ModulesSeeder::class, '--force' => true]);

    // Empresa + sucursal
    $company = \App\Models\Company::updateOrCreate(
        ['ruc' => $companyRuc],
        [
            'razon_social' => $companyName,
            'nombre_comercial' => $companyTradeName,
            'direccion' => 'Av. Piloto 123',
            'ubigeo' => '150101',
            'distrito' => 'Lima',
            'provincia' => 'Lima',
            'departamento' => 'Lima',
            'telefono' => '01-1234567',
            'email' => 'contacto@petflow.com',
            'web' => 'https://www.petflow.com',
            'usuario_sol' => 'DEMO',
            'clave_sol' => 'DEMO',
            'endpoint_beta' => 'https://e-beta.sunat.gob.pe/ol-ti-itcpfegem-beta/billService',
            'endpoint_produccion' => 'https://e-factura.sunat.gob.pe/ol-ti-itcpfegem/billService',
            'modo_produccion' => false,
            'logo_path' => null,
            'activo' => true,
        ]
    );

    $branch = \App\Models\Branch::updateOrCreate(
        ['company_id' => $company->id, 'codigo' => '001'],
        [
            'company_id' => $company->id,
            'codigo' => '001',
            'nombre' => 'Sucursal Principal',
            'direccion' => $company->direccion,
            'ubigeo' => $company->ubigeo,
            'distrito' => $company->distrito,
            'provincia' => $company->provincia,
            'departamento' => $company->departamento,
            'activo' => true,
            'series_factura' => ['F001'],
            'series_boleta' => ['B001'],
            'series_nota_credito' => ['FC01'],
            'series_nota_debito' => ['FD01', 'BD01'],
            'series_guia_remision' => ['T001'],
        ]
    );

    // Usuario admin (empresa)
    $companyAdminRole = \App\Models\Role::where('name', 'company_admin')->first();
    if (!$companyAdminRole) {
        $this->error('No existe rol company_admin. Revisa RolesAndPermissionsSeeder.');
        return 1;
    }

    $admin = \App\Models\User::updateOrCreate(
        ['email' => $adminEmail],
        [
            'name' => 'Admin Piloto',
            'password' => Hash::make($adminPassword),
            'role_id' => $companyAdminRole->id,
            'company_id' => $company->id,
            'user_type' => 'user',
            'active' => true,
            'email_verified_at' => now(),
        ]
    );

    $token = $admin->createToken('pilot-admin', ['*'])->plainTextToken;

    // Perfil fiscal v2 (CO) con provider stub (para demo de billing v2)
    \App\Models\CompanyTaxProfile::updateOrCreate(
        ['company_id' => $company->id, 'country_code' => 'CO'],
        [
            'company_id' => $company->id,
            'country_code' => 'CO',
            'tax_id' => '900123456',
            'tax_id_dv' => '1',
            'legal_name' => $companyName,
            'trade_name' => $companyTradeName,
            'email' => $company->email,
            'address_line' => $company->direccion,
            'city' => 'Bogotá',
            'state' => 'Cundinamarca',
            'postal_code' => '110111',
            'currency_code_default' => 'COP',
            'locale_default' => 'es-CO',
            'environment' => 'test',
            'provider_slug' => 'dian_stub',
            'active' => true,
        ]
    );

    // Seeders dependientes de company/branch (core)
    $this->warn('Seed: correlativos/config/zonas/mascotas...');
    Artisan::call('db:seed', ['--class' => \Database\Seeders\CorrelativesSeeder::class, '--force' => true]);
    Artisan::call('db:seed', ['--class' => \Database\Seeders\CompanyConfigSeeder::class, '--force' => true]);
    Artisan::call('db:seed', ['--class' => \Database\Seeders\ZonesSeeder::class, '--force' => true]);
    Artisan::call('db:seed', ['--class' => \Database\Seeders\PetConfigurationSeeder::class, '--force' => true]);

    if ($demo) {
        $this->warn('Seed: demo data (catálogos + clientes/mascotas + extras)...');
        Artisan::call('db:seed', ['--class' => \Database\Seeders\DemoDataSeeder::class, '--force' => true]);
        $this->line(Artisan::output());
    }

    $this->info('OK. Setup piloto listo.');
    $this->line("Company: {$company->id} ({$company->ruc}) {$company->razon_social}");
    $this->line("Branch: {$branch->id} {$branch->nombre}");
    $this->line("Admin: {$adminEmail} / {$adminPassword}");
    $this->line('Bearer token (Sanctum):');
    $this->line($token);
    $this->line("PILOT_BEARER_TOKEN={$token}");
    $this->line('Smoke: GET /api/v2/config/masters  (con Authorization: Bearer <token>)');

    return 0;
})->purpose('Setup automático para piloto (migrate + seed + admin)');
