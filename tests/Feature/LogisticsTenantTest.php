<?php

use App\Models\Client;
use App\Models\Company;
use App\Models\Role;
use App\Models\Route as RouteModel;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

test('company_admin crea zona sin enviar company_id', function () {
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

    $response = $this->postJson('/api/v1/zones', [
        'name' => 'Zona Norte A',
        'districts' => ['Los Olivos', 'Comas'],
        'active' => true,
    ]);

    $response->assertStatus(201);
    $zoneId = $response->json('data.id');
    expect(Zone::withoutGlobalScopes()->find($zoneId)->company_id)->toBe($companyA->id);

    $userB = User::factory()->create([
        'company_id' => $companyB->id,
        'role_id' => $role->id,
        'active' => true,
    ]);
    Sanctum::actingAs($userB, ['*']);

    $this->getJson('/api/v1/zones/' . $zoneId)->assertStatus(404);
});

test('zones list respeta tenant via global scope', function () {
    $companyA = Company::factory()->create(['activo' => true]);
    $companyB = Company::factory()->create(['activo' => true]);

    $role = Role::firstOrCreate(
        ['name' => 'company_admin'],
        ['display_name' => 'Admin', 'permissions' => ['*'], 'is_system' => true, 'active' => true]
    );

    $userA = User::factory()->create(['company_id' => $companyA->id, 'role_id' => $role->id, 'active' => true]);

    Zone::withoutGlobalScopes()->create([
        'company_id' => $companyA->id,
        'name' => 'Zona A',
        'active' => true,
    ]);
    Zone::withoutGlobalScopes()->create([
        'company_id' => $companyB->id,
        'name' => 'Zona B',
        'active' => true,
    ]);

    Sanctum::actingAs($userA, ['*']);

    $response = $this->getJson('/api/v1/zones');
    $response->assertStatus(200);
    $names = collect($response->json('data'))->pluck('name')->all();
    expect($names)->toContain('Zona A');
    expect($names)->not->toContain('Zona B');
});

test('save route from appointments respeta tenant', function () {
    $companyA = Company::factory()->create(['activo' => true]);
    $companyB = Company::factory()->create(['activo' => true]);

    $role = Role::firstOrCreate(
        ['name' => 'company_admin'],
        ['display_name' => 'Admin', 'permissions' => ['*'], 'is_system' => true, 'active' => true]
    );

    $userA = User::factory()->create(['company_id' => $companyA->id, 'role_id' => $role->id, 'active' => true]);

    $vehicleAId = (int) DB::table('vehicles')->insertGetId([
        'company_id' => $companyA->id,
        'name' => 'Móvil A',
        'type' => 'furgoneta_grande',
        'placa' => 'MOB-A',
        'activo' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $clientA = Client::withoutGlobalScopes()->create([
        'company_id' => $companyA->id,
        'tipo_documento' => '1',
        'numero_documento' => '11112222',
        'razon_social' => 'Cliente A',
        'email' => 'a@test.pe',
        'activo' => true,
    ]);

    $petAId = (int) DB::table('pets')->insertGetId([
        'company_id' => $companyA->id,
        'client_id' => $clientA->id,
        'name' => 'Max',
        'species' => 'Perro',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $date = now()->addDay()->toDateString();
    $appointmentAId = (int) DB::table('appointments')->insertGetId([
        'company_id' => $companyA->id,
        'client_id' => $clientA->id,
        'pet_id' => $petAId,
        'vehicle_id' => $vehicleAId,
        'date' => $date,
        'time' => '09:00',
        'status' => 'Confirmada',
        'service_type' => 'grooming',
        'service_name' => 'Baño',
        'service_category' => 'MovilVet',
        'address' => 'Av Test 100',
        'district' => 'Miraflores',
        'price' => 80,
        'total' => 80,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $vehicleBId = (int) DB::table('vehicles')->insertGetId([
        'company_id' => $companyB->id,
        'name' => 'Móvil B',
        'type' => 'furgoneta_grande',
        'placa' => 'MOB-B',
        'activo' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Sanctum::actingAs($userA, ['*']);

    $this->postJson('/api/v1/route-plans/from-appointments', [
        'vehicle_id' => $vehicleBId,
        'date' => $date,
        'appointment_ids' => [$appointmentAId],
    ])->assertStatus(404);

    $save = $this->postJson('/api/v1/route-plans/from-appointments', [
        'vehicle_id' => $vehicleAId,
        'date' => $date,
        'appointment_ids' => [$appointmentAId],
    ]);
    $save->assertStatus(200);
    expect(RouteModel::withoutGlobalScopes()->where('vehicle_id', $vehicleAId)->count())->toBe(1);
});

test('daily schedule no expone vehiculo de otra empresa', function () {
    $companyA = Company::factory()->create(['activo' => true]);
    $companyB = Company::factory()->create(['activo' => true]);

    $role = Role::firstOrCreate(
        ['name' => 'company_admin'],
        ['display_name' => 'Admin', 'permissions' => ['*'], 'is_system' => true, 'active' => true]
    );

    $userA = User::factory()->create(['company_id' => $companyA->id, 'role_id' => $role->id, 'active' => true]);

    $vehicleBId = (int) DB::table('vehicles')->insertGetId([
        'company_id' => $companyB->id,
        'name' => 'Móvil B',
        'type' => 'furgoneta_grande',
        'placa' => 'MOB-B2',
        'activo' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Sanctum::actingAs($userA, ['*']);

    $this->getJson('/api/v1/route-plans/daily-schedule?vehicle_id=' . $vehicleBId . '&date=' . now()->toDateString())
        ->assertStatus(404);
});

test('forge company_id en zones bloqueado por middleware', function () {
    $companyA = Company::factory()->create(['activo' => true]);
    $companyB = Company::factory()->create(['activo' => true]);

    $role = Role::firstOrCreate(
        ['name' => 'company_admin'],
        ['display_name' => 'Admin', 'permissions' => ['*'], 'is_system' => true, 'active' => true]
    );

    $userA = User::factory()->create(['company_id' => $companyA->id, 'role_id' => $role->id, 'active' => true]);

    Sanctum::actingAs($userA, ['*']);

    $this->postJson('/api/v1/zones', [
        'name' => 'Zona Forge',
        'company_id' => $companyB->id,
        'active' => true,
    ])->assertStatus(403);
});
