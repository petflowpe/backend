<?php

use App\Models\Client;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    Company::factory()->create(['activo' => true]);
    $this->user = User::factory()->create([
        'email' => 'pets@test.com',
        'password' => Hash::make('password'),
        'role_id' => null,
        'company_id' => null,
        'active' => true,
    ]);
    $this->token = $this->postJson('/api/auth/login', [
        'email' => 'pets@test.com',
        'password' => 'password',
    ])->json('access_token');

    $this->client = Client::factory()->create();
});

test('POST /api/v1/pets prevents duplicate pet for same owner/species/name', function () {
    $payload = [
        'client_id' => $this->client->id,
        'name' => 'Firulais',
        'last_name' => 'Lopez',
        'species' => 'Perro',
    ];

    $first = $this->withHeader('Authorization', 'Bearer ' . $this->token)
        ->postJson('/api/v1/pets', $payload);
    $first->assertStatus(201);

    $second = $this->withHeader('Authorization', 'Bearer ' . $this->token)
        ->postJson('/api/v1/pets', $payload);
    $second->assertStatus(409)
        ->assertJsonPath('success', false);
});

test('GET /api/v1/pets/{id}/timeline returns timeline structure', function () {
    $petResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
        ->postJson('/api/v1/pets', [
            'client_id' => $this->client->id,
            'name' => 'Luna',
            'species' => 'Gato',
        ]);
    $petResponse->assertStatus(201);
    $petId = $petResponse->json('data.id');

    $timeline = $this->withHeader('Authorization', 'Bearer ' . $this->token)
        ->getJson("/api/v1/pets/{$petId}/timeline");

    $timeline->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'data' => [
                'pet',
                'timeline',
                'summary' => ['medical_records', 'vaccines', 'appointments', 'total_events'],
            ],
        ]);
});

test('GET /api/v1/pets/duplicates returns summary keys', function () {
    $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
        ->getJson('/api/v1/pets/duplicates');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'data' => ['owners', 'pets', 'species', 'breeds'],
            'summary' => ['owners', 'pets', 'species', 'breeds'],
        ]);
});

