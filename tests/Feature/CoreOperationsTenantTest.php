<?php

use App\Models\Client;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('company_admin crea cliente en su empresa sin enviar company_id', function () {
    $companyA = Company::factory()->create(['activo' => true]);
    $companyB = Company::factory()->create(['activo' => true]);

    $role = Role::firstOrCreate(
        ['name' => 'company_admin'],
        ['display_name' => 'Admin', 'permissions' => ['*'], 'is_system' => true, 'active' => true]
    );

    $user = User::factory()->create([
        'company_id' => $companyA->id,
        'role_id' => $role->id,
        'active' => true,
    ]);

    Sanctum::actingAs($user, ['*']);

    $response = $this->postJson('/api/v1/clients', [
        'tipo_documento' => '1',
        'numero_documento' => '87654321',
        'razon_social' => 'Cliente Tenant A',
        'email' => 'clientea@test.pe',
        'direccion' => 'Av Test 100',
        'distrito' => 'Lima',
        'provincia' => 'Lima',
        'departamento' => 'Lima',
        'activo' => true,
    ]);

    $response->assertStatus(201);
    $clientId = $response->json('data.id');
    expect(Client::withoutGlobalScopes()->find($clientId)->company_id)->toBe($companyA->id);

    // No debe aparecer en listado de empresa B
    $userB = User::factory()->create([
        'company_id' => $companyB->id,
        'role_id' => $role->id,
        'active' => true,
    ]);
    Sanctum::actingAs($userB, ['*']);
    $listB = $this->getJson('/api/v1/clients');
    $listB->assertStatus(200);
    $ids = collect($listB->json('data'))->pluck('id')->all();
    expect($ids)->not->toContain($clientId);
});

test('appointments confirm y send-reminder respetan tenant', function () {
    $companyA = Company::factory()->create(['activo' => true]);
    $companyB = Company::factory()->create(['activo' => true]);

    $role = Role::firstOrCreate(
        ['name' => 'company_admin'],
        ['display_name' => 'Admin', 'permissions' => ['*'], 'is_system' => true, 'active' => true]
    );

    $userA = User::factory()->create(['company_id' => $companyA->id, 'role_id' => $role->id, 'active' => true]);

    $clientB = Client::withoutGlobalScopes()->create([
        'company_id' => $companyB->id,
        'tipo_documento' => '1',
        'numero_documento' => '11111111',
        'razon_social' => 'Cliente B',
        'email' => 'b@test.pe',
        'activo' => true,
    ]);

    $petBId = \Illuminate\Support\Facades\DB::table('pets')->insertGetId([
        'company_id' => $companyB->id,
        'client_id' => $clientB->id,
        'name' => 'Firulais',
        'species' => 'Perro',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $appointmentBId = \Illuminate\Support\Facades\DB::table('appointments')->insertGetId([
        'company_id' => $companyB->id,
        'client_id' => $clientB->id,
        'pet_id' => $petBId,
        'date' => now()->addDay()->toDateString(),
        'time' => '10:00',
        'status' => 'Pendiente',
        'service_type' => 'consulta',
        'service_name' => 'Consulta',
        'service_category' => 'MovilVet',
        'address' => 'Calle 1',
        'price' => 50,
        'total' => 50,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Sanctum::actingAs($userA, ['*']);

    $this->postJson('/api/v1/appointments/' . $appointmentBId . '/confirm')->assertStatus(404);
    $this->postJson('/api/v1/appointments/' . $appointmentBId . '/send-reminder')->assertStatus(404);
});
