<?php

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Role::firstOrCreate(
        ['name' => 'super_admin'],
        ['display_name' => 'Super Admin', 'permissions' => ['*'], 'is_system' => true, 'active' => true]
    );
    Role::firstOrCreate(
        ['name' => 'company_admin'],
        ['display_name' => 'Admin de empresa', 'permissions' => ['*'], 'is_system' => true, 'active' => true]
    );
});

it('onboard crea empresa, sucursal y company_admin en una sola transacción', function () {
    $super = User::factory()->create([
        'role_id' => Role::where('name', 'super_admin')->value('id'),
        'company_id' => null,
        'active' => true,
    ]);

    Sanctum::actingAs($super);

    $ruc = '20123456789';
    $adminEmail = 'admin.' . uniqid() . '@demo.pe';

    $response = $this->postJson('/api/v1/company-onboardings', [
        'company' => [
            'ruc' => $ruc,
            'razon_social' => 'Demo Grooming SAC',
            'nombre_comercial' => 'Demo Grooming',
            'direccion' => 'Av. Principal 123',
            'ubigeo' => '150101',
            'distrito' => 'Lima',
            'provincia' => 'Lima',
            'departamento' => 'Lima',
            'email' => 'contacto@demo.pe',
            'telefono' => '999888777',
        ],
        'branch' => [
            'nombre' => 'Sede Miraflores',
            'codigo' => '001',
        ],
        'admin' => [
            'name' => 'Admin Demo',
            'email' => $adminEmail,
            'password' => 'AdminDemo123',
        ],
    ]);

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.admin.email', $adminEmail)
        ->assertJsonPath('data.admin.role', 'company_admin');

    $this->assertDatabaseHas('companies', ['ruc' => $ruc, 'razon_social' => 'Demo Grooming SAC']);
    $companyId = Company::where('ruc', $ruc)->value('id');
    $this->assertDatabaseHas('branches', [
        'company_id' => $companyId,
        'nombre' => 'Sede Miraflores',
        'codigo' => '001',
    ]);
    $this->assertDatabaseHas('users', [
        'email' => $adminEmail,
        'company_id' => $companyId,
    ]);

    $admin = User::where('email', $adminEmail)->first();
    expect(Hash::check('AdminDemo123', $admin->password))->toBeTrue();
});

it('revierte todo si el email admin ya existe', function () {
    $super = User::factory()->create([
        'role_id' => Role::where('name', 'super_admin')->value('id'),
        'company_id' => null,
        'active' => true,
    ]);

    User::factory()->create([
        'email' => 'taken@demo.pe',
        'role_id' => Role::where('name', 'company_admin')->value('id'),
        'active' => true,
    ]);

    Sanctum::actingAs($super);

    $ruc = '20999888777';

    $this->postJson('/api/v1/company-onboardings', [
        'company' => [
            'ruc' => $ruc,
            'razon_social' => 'Otra SAC',
            'direccion' => 'Calle 1',
            'email' => 'otra@demo.pe',
        ],
        'admin' => [
            'name' => 'Admin',
            'email' => 'taken@demo.pe',
            'password' => 'AdminDemo123',
        ],
    ])->assertStatus(422);

    $this->assertDatabaseMissing('companies', ['ruc' => $ruc]);
});
