<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('users:ensure-admin
    {--email=admin@smartpet.com : Email principal de acceso}
    {--password=Admin123 : Contraseña en texto plano (cast hashed la cifra una sola vez)}
    {--name=Super Administrador : Nombre visible}
    {--also-email= : Email adicional a sincronizar con la misma clave}
', function () {
    $email = strtolower(trim((string) $this->option('email')));
    $password = (string) $this->option('password');
    $name = (string) $this->option('name');
    $alsoEmail = strtolower(trim((string) ($this->option('also-email') ?: '')));

    if ($email === '' || $password === '') {
        $this->error('email y password son obligatorios.');
        return 1;
    }

    // Asegurar roles/permisos base sin crear usuarios demo del seeder.
    $rolesSeeder = new \Database\Seeders\RolesAndPermissionsSeeder();
    $rolesSeeder->runPermissionsAndRolesOnly();

    $superAdminRole = \App\Models\Role::where('name', 'super_admin')->first();
    if (!$superAdminRole) {
        $this->error('No existe rol super_admin.');
        return 1;
    }

    $companyId = \App\Models\Company::query()->value('id');

    $upsert = function (string $userEmail) use ($name, $password, $superAdminRole, $companyId) {
        /** @var \App\Models\User $user */
        $user = \App\Models\User::updateOrCreate(
            ['email' => $userEmail],
            [
                'name' => $name,
                // NO usar Hash::make aquí: el cast "hashed" del modelo ya cifra.
                'password' => $password,
                'role_id' => $superAdminRole->id,
                'company_id' => $companyId,
                'user_type' => 'system',
                'active' => true,
                'email_verified_at' => now(),
                'failed_login_attempts' => 0,
                'locked_until' => null,
                'force_password_change' => false,
                'password_changed_at' => now(),
            ]
        );

        // Por si el updateOrCreate no tocó password al no detectar cambio:
        $user->password = $password;
        $user->active = true;
        $user->failed_login_attempts = 0;
        $user->locked_until = null;
        $user->save();

        return $user;
    };

    $primary = $upsert($email);
    $this->info("OK primary: {$primary->email} (id={$primary->id})");

    if ($alsoEmail !== '' && $alsoEmail !== $email) {
        $secondary = $upsert($alsoEmail);
        $this->info("OK also-email: {$secondary->email} (id={$secondary->id})");
    }

    $this->line("Login: {$email} / {$password}");
    return 0;
})->purpose('Crear/actualizar admin de acceso (corrige doble hash)');

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

    // NO usar Hash::make: el cast "hashed" del modelo User ya cifra la contraseña.
    $admin = \App\Models\User::updateOrCreate(
        ['email' => $adminEmail],
        [
            'name' => 'Admin Piloto',
            'password' => $adminPassword,
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

Artisan::command('vehicles:migrate-coverage-rules
    {--company= : ID de empresa (opcional)}
    {--vehicle= : ID de vehículo (opcional)}
', function () {
    /** @var \App\Services\VehicleCoverageService $service */
    $service = app(\App\Services\VehicleCoverageService::class);

    $query = \App\Models\Vehicle::query();
    if ($this->option('company')) {
        $query->where('company_id', (int) $this->option('company'));
    }
    if ($this->option('vehicle')) {
        $query->where('id', (int) $this->option('vehicle'));
    }

    $vehicles = $query->get();
    $totalRules = 0;

    foreach ($vehicles as $vehicle) {
        $created = $service->migrateVehicleFromLegacy($vehicle);
        if ($created > 0) {
            $this->line("Vehículo #{$vehicle->id} ({$vehicle->name}): {$created} regla(s) creada(s).");
            $totalRules += $created;
        }
    }

    $this->info("Migración completada. Reglas creadas: {$totalRules}");

    return 0;
})->purpose('Migrar zonas_asignadas y horario_disponibilidad a reglas de cobertura');
