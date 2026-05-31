<?php

use App\Models\Client;
use App\Models\Company;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('v2 clients list is scoped by company and blocks forged company_id', function () {
    $companyA = Company::factory()->create(['activo' => true]);
    $companyB = Company::factory()->create(['activo' => true]);

    $userA = User::factory()->create([
        'company_id' => $companyA->id,
        'active' => true,
    ]);

    Client::factory()->create(['company_id' => $companyA->id, 'razon_social' => 'CLIENT A']);
    Client::factory()->create(['company_id' => $companyB->id, 'razon_social' => 'CLIENT B']);

    Sanctum::actingAs($userA, ['*']);

    $list = $this->getJson('/api/v2/clients');
    $list->assertStatus(200);
    expect($list->json('data'))->toBeArray();
    expect(collect($list->json('data'))->pluck('fullName')->all())->toContain('CLIENT A');
    expect(collect($list->json('data'))->pluck('fullName')->all())->not->toContain('CLIENT B');

    // Intento de forjar company_id por query debe ser bloqueado por middleware
    $forged = $this->getJson('/api/v2/clients?company_id=' . $companyB->id);
    $forged->assertStatus(403);
});

test('v2 create client forces tenant scope for non super_admin', function () {
    $companyA = Company::factory()->create(['activo' => true]);
    $companyB = Company::factory()->create(['activo' => true]);

    $userA = User::factory()->create([
        'company_id' => $companyA->id,
        'active' => true,
    ]);
    Sanctum::actingAs($userA, ['*']);

    $payload = [
        'fullName' => 'Cliente V2',
        'documentType' => 'DNI',
        'documentNumber' => '12345678',
        'status' => 'Activo',
        // intento de forjar company_id legado
        'company_id' => $companyB->id,
    ];

    $response = $this->postJson('/api/v2/clients', $payload);
    $response->assertStatus(403);
});

