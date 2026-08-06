<?php

use App\Models\Client;
use App\Models\Company;
use App\Models\MedicalRecord;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

it('impide que company_admin lea historial clínico de otra empresa', function () {
    $companyA = Company::factory()->create(['activo' => true]);
    $companyB = Company::factory()->create(['activo' => true]);

    $role = Role::firstOrCreate(
        ['name' => 'company_admin'],
        ['display_name' => 'Admin de empresa', 'permissions' => ['*'], 'is_system' => true, 'active' => true]
    );

    $userA = User::factory()->create([
        'company_id' => $companyA->id,
        'role_id' => $role->id,
        'active' => true,
    ]);

    $clientB = Client::withoutGlobalScopes()->create([
        'company_id' => $companyB->id,
        'tipo_documento' => '1',
        'numero_documento' => 'DOC-B-' . uniqid(),
        'razon_social' => 'Cliente B',
        'activo' => true,
    ]);

    $petBId = DB::table('pets')->insertGetId([
        'company_id' => $companyB->id,
        'client_id' => $clientB->id,
        'name' => 'Mascota B',
        'species' => 'Perro',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $recordB = MedicalRecord::withoutGlobalScopes()->create([
        'company_id' => $companyB->id,
        'client_id' => $clientB->id,
        'pet_id' => $petBId,
        'date' => now()->toDateString(),
        'type' => 'Consulta',
        'title' => 'HC B',
        'description' => 'No debe verse desde A',
    ]);

    Sanctum::actingAs($userA);

    $this->getJson('/api/v1/medical-records/' . $recordB->id)
        ->assertNotFound();

    $list = $this->getJson('/api/v1/medical-records')->assertOk();
    $ids = collect($list->json('data'))->pluck('id')->all();
    expect($ids)->not->toContain($recordB->id);
});

it('impide crear historial clínico con mascota de otra empresa', function () {
    $companyA = Company::factory()->create(['activo' => true]);
    $companyB = Company::factory()->create(['activo' => true]);

    $role = Role::firstOrCreate(
        ['name' => 'company_admin'],
        ['display_name' => 'Admin de empresa', 'permissions' => ['*'], 'is_system' => true, 'active' => true]
    );

    $userA = User::factory()->create([
        'company_id' => $companyA->id,
        'role_id' => $role->id,
        'active' => true,
    ]);

    $clientB = Client::withoutGlobalScopes()->create([
        'company_id' => $companyB->id,
        'tipo_documento' => '1',
        'numero_documento' => 'DOC-B2-' . uniqid(),
        'razon_social' => 'Cliente B2',
        'activo' => true,
    ]);

    $petBId = DB::table('pets')->insertGetId([
        'company_id' => $companyB->id,
        'client_id' => $clientB->id,
        'name' => 'Mascota B2',
        'species' => 'Perro',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Sanctum::actingAs($userA);

    $this->postJson('/api/v1/medical-records', [
        'pet_id' => $petBId,
        'client_id' => $clientB->id,
        'date' => now()->toDateString(),
        'type' => 'Consulta',
        'description' => 'Intento cross-tenant',
    ])->assertForbidden();
});
