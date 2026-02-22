<?php

use App\Models\User;
use App\Models\Client;
use App\Models\Company;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    Company::factory()->create(['activo' => true]);
    $this->user = User::factory()->create([
        'email' => 'admin@test.com',
        'password' => Hash::make('password'),
        'role_id' => null,
        'company_id' => null,
        'active' => true,
    ]);
    $this->token = $this->postJson('/api/auth/login', [
        'email' => 'admin@test.com',
        'password' => 'password',
    ])->json('access_token');
});

test('GET /api/v1/clients returns 200 with auth', function () {
    $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
        ->getJson('/api/v1/clients');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'data',
            'meta' => ['total', 'per_page', 'current_page', 'last_page'],
        ]);
    expect($response->json('success'))->toBeTrue();
});

test('POST /api/v1/clients creates client with valid data', function () {
    $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
        ->postJson('/api/v1/clients', [
            'tipo_documento' => '1',
            'numero_documento' => '12345678',
            'razon_social' => 'Cliente Test SA',
            'nombre_comercial' => 'Cliente Test',
            'email' => 'cliente@test.com',
            'telefono' => '999888777',
            'direccion' => 'Calle Test 123',
            'distrito' => 'Lima',
            'activo' => true,
        ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => ['id', 'razon_social', 'numero_documento'],
        ]);
    expect($response->json('success'))->toBeTrue();
    expect(Client::where('numero_documento', '12345678')->exists())->toBeTrue();
});
