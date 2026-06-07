<?php

use App\Models\Company;
use App\Models\Role;
use App\Models\Scopes\CompanyScope;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleConfiguration;
use App\Models\VehicleService;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

test('company_admin crea vehiculo en su empresa sin enviar company_id', function () {
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

    $response = $this->postJson('/api/v1/vehicles', [
        'name' => 'Furgoneta A',
        'type' => 'furgoneta_grande',
        'placa' => 'ABC-123',
        'activo' => true,
    ]);

    $response->assertStatus(201);
    $vehicleId = $response->json('data.id');
    expect(Vehicle::withoutGlobalScopes()->find($vehicleId)->company_id)->toBe($companyA->id);

    $userB = User::factory()->create([
        'company_id' => $companyB->id,
        'role_id' => $role->id,
        'active' => true,
    ]);
    Sanctum::actingAs($userB, ['*']);

    $this->getJson('/api/v1/vehicles/' . $vehicleId)->assertStatus(404);
});

test('vehicle configurations mezcla global y tenant', function () {
    $companyA = Company::factory()->create(['activo' => true]);

    $role = Role::firstOrCreate(
        ['name' => 'company_admin'],
        ['display_name' => 'Admin', 'permissions' => ['*'], 'is_system' => true, 'active' => true]
    );

    $user = User::factory()->create([
        'company_id' => $companyA->id,
        'role_id' => $role->id,
        'active' => true,
    ]);

    VehicleConfiguration::withoutGlobalScope(CompanyScope::class)->create([
        'company_id' => null,
        'type' => 'vehicle_brand',
        'name' => 'Toyota Global',
        'sort_order' => 0,
        'active' => true,
    ]);

    Sanctum::actingAs($user, ['*']);

    $response = $this->getJson('/api/v1/vehicle-configurations/all');
    $response->assertStatus(200);
    expect($response->json('data.brands'))->toContain('Toyota Global');

    $this->postJson('/api/v1/vehicle-configurations', [
        'type' => 'brands',
        'items' => ['Marca Tenant A'],
    ])->assertStatus(201);

    $after = $this->getJson('/api/v1/vehicle-configurations/all');
    $after->assertStatus(200);
    expect($after->json('data.brands'))->toContain('Marca Tenant A');
    expect($after->json('data.brands'))->not->toContain('Toyota Global');
});

test('vehicle services respetan tenant', function () {
    $companyA = Company::factory()->create(['activo' => true]);
    $companyB = Company::factory()->create(['activo' => true]);

    $role = Role::firstOrCreate(
        ['name' => 'company_admin'],
        ['display_name' => 'Admin', 'permissions' => ['*'], 'is_system' => true, 'active' => true]
    );

    $userA = User::factory()->create(['company_id' => $companyA->id, 'role_id' => $role->id, 'active' => true]);
    $userB = User::factory()->create(['company_id' => $companyB->id, 'role_id' => $role->id, 'active' => true]);

    $vehicleAId = (int) DB::table('vehicles')->insertGetId([
        'company_id' => $companyA->id,
        'name' => 'Van A',
        'type' => 'furgoneta_grande',
        'placa' => 'VAN-A',
        'activo' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $serviceBId = (int) DB::table('vehicle_services')->insertGetId([
        'company_id' => $companyB->id,
        'vehicle_id' => (int) DB::table('vehicles')->insertGetId([
            'company_id' => $companyB->id,
            'name' => 'Van B',
            'type' => 'furgoneta_grande',
            'placa' => 'VAN-B',
            'activo' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]),
        'type' => 'Cambio aceite',
        'due_date' => now()->addDays(7)->toDateString(),
        'priority' => 'medium',
        'estimated_cost' => 50,
        'status' => 'pending',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Sanctum::actingAs($userA, ['*']);

    $this->postJson('/api/v1/vehicles/' . $vehicleAId . '/services', [
        'type' => 'Revisión',
        'description' => 'Servicio programado',
        'dueDate' => now()->addDays(3)->toDateString(),
        'priority' => 'low',
        'estimatedCost' => 80,
    ])->assertStatus(201);

    $listA = $this->getJson('/api/v1/vehicle-services?status=pending');
    $listA->assertStatus(200);
    $idsA = collect($listA->json('data'))->pluck('id')->all();
    expect($idsA)->not->toContain($serviceBId);

    $this->postJson('/api/v1/vehicle-services/' . $serviceBId . '/complete', [])
        ->assertStatus(404);

    Sanctum::actingAs($userB, ['*']);
    $this->postJson('/api/v1/vehicle-services/' . $serviceBId . '/complete', [])
        ->assertStatus(200);

    expect(VehicleService::withoutGlobalScopes()->find($serviceBId)->status)->toBe('completed');
});

test('vehicle show incluye status calculado', function () {
    $companyA = Company::factory()->create(['activo' => true]);

    $role = Role::firstOrCreate(
        ['name' => 'company_admin'],
        ['display_name' => 'Admin', 'permissions' => ['*'], 'is_system' => true, 'active' => true]
    );

    $user = User::factory()->create([
        'company_id' => $companyA->id,
        'role_id' => $role->id,
        'active' => true,
    ]);

    $vehicleId = (int) DB::table('vehicles')->insertGetId([
        'company_id' => $companyA->id,
        'name' => 'Van Status',
        'type' => 'furgoneta_grande',
        'placa' => 'STS-001',
        'activo' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('vehicle_maintenances')->insert([
        'company_id' => $companyA->id,
        'vehicle_id' => $vehicleId,
        'type' => 'Reparación',
        'status' => 'in_progress',
        'description' => 'En taller',
        'date' => now()->toDateString(),
        'cost' => 100,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Sanctum::actingAs($user, ['*']);

    $response = $this->getJson('/api/v1/vehicles/' . $vehicleId);
    $response->assertStatus(200);
    expect($response->json('data.status'))->toBe('maintenance');
});
