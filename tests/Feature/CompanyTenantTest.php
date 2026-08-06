<?php

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

function companyAdminRole(): Role
{
    return Role::firstOrCreate(
        ['name' => 'company_admin'],
        ['display_name' => 'Admin de empresa', 'permissions' => ['*'], 'is_system' => true, 'active' => true]
    );
}

function superAdminRole(): Role
{
    return Role::firstOrCreate(
        ['name' => 'super_admin'],
        ['display_name' => 'Super Admin', 'permissions' => ['*'], 'is_system' => true, 'active' => true]
    );
}

test('company_admin solo ve su empresa en el listado', function () {
    $companyA = Company::factory()->create(['activo' => true, 'razon_social' => 'EMPRESA A']);
    $companyB = Company::factory()->create(['activo' => true, 'razon_social' => 'EMPRESA B']);

    $userA = User::factory()->create([
        'company_id' => $companyA->id,
        'role_id' => companyAdminRole()->id,
        'active' => true,
    ]);

    Sanctum::actingAs($userA, ['*']);

    $response = $this->getJson('/api/v1/companies');
    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($companyA->id);
    expect($response->json('data.0.razon_social'))->toBe('EMPRESA A');
});

test('company_admin no puede ver ni editar otra empresa', function () {
    $companyA = Company::factory()->create(['activo' => true]);
    $companyB = Company::factory()->create(['activo' => true, 'razon_social' => 'EMPRESA B']);

    $userA = User::factory()->create([
        'company_id' => $companyA->id,
        'role_id' => companyAdminRole()->id,
        'active' => true,
    ]);

    Sanctum::actingAs($userA, ['*']);

    $this->getJson('/api/v1/companies/' . $companyB->id)->assertStatus(403);

    $this->putJson('/api/v1/companies/' . $companyB->id, [
        'ruc' => $companyB->ruc,
        'razon_social' => 'HACKEADO',
        'direccion' => 'Calle X',
        'email' => 'hack@test.com',
        'ubigeo' => '150101',
        'distrito' => 'Lima',
    ])->assertStatus(403);
});

test('company_admin puede editar su propia empresa', function () {
    $company = Company::factory()->create(['activo' => true, 'razon_social' => 'ANTES']);

    $user = User::factory()->create([
        'company_id' => $company->id,
        'role_id' => companyAdminRole()->id,
        'active' => true,
    ]);

    Sanctum::actingAs($user, ['*']);

    $response = $this->putJson('/api/v1/companies/' . $company->id, [
        'ruc' => $company->ruc,
        'razon_social' => 'DESPUES',
        'direccion' => $company->direccion ?? 'Av Test 123',
        'email' => $company->email ?? 'test@empresa.pe',
        'ubigeo' => $company->ubigeo ?? '150101',
        'distrito' => $company->distrito ?? 'Lima',
        'provincia' => $company->provincia ?? 'Lima',
        'departamento' => $company->departamento ?? 'Lima',
        'usuario_sol' => $company->usuario_sol ?? 'MODDATOS',
        'clave_sol' => 'moddatos',
    ]);

    $response->assertStatus(200);
    expect($company->fresh()->razon_social)->toBe('DESPUES');
});

test('company_admin no puede crear empresas', function () {
    $company = Company::factory()->create(['activo' => true]);

    $user = User::factory()->create([
        'company_id' => $company->id,
        'role_id' => companyAdminRole()->id,
        'active' => true,
    ]);

    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/companies', [
        'ruc' => '20999999999',
        'razon_social' => 'Nueva Empresa',
        'direccion' => 'Calle 1',
        'email' => 'nueva@test.pe',
        'ubigeo' => '150101',
        'distrito' => 'Lima',
        'provincia' => 'Lima',
        'departamento' => 'Lima',
        'usuario_sol' => 'MODDATOS',
        'clave_sol' => 'moddatos',
    ])->assertStatus(403);
});

test('super_admin ve todas las empresas activas', function () {
    Company::factory()->create(['activo' => true]);
    Company::factory()->create(['activo' => true]);
    Company::factory()->create(['activo' => false]);

    $super = User::factory()->create([
        'role_id' => superAdminRole()->id,
        'company_id' => null,
        'active' => true,
    ]);

    Sanctum::actingAs($super, ['*']);

    $response = $this->getJson('/api/v1/companies');
    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(2);
});

test('company_admin no puede leer config de otra empresa', function () {
    $companyA = Company::factory()->create(['activo' => true]);
    $companyB = Company::factory()->create(['activo' => true]);

    $userA = User::factory()->create([
        'company_id' => $companyA->id,
        'role_id' => companyAdminRole()->id,
        'active' => true,
    ]);

    Sanctum::actingAs($userA, ['*']);

    $this->getJson('/api/v1/companies/' . $companyB->id . '/config/document_settings')
        ->assertStatus(403);
});
