<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('POST /api/auth/login with invalid credentials returns 401 or 422', function () {
    $response = $this->postJson('/api/auth/login', [
        'email' => 'nonexistent@test.com',
        'password' => 'wrong',
    ]);

    expect($response->status())->toBeIn([401, 422]);
});

test('POST /api/auth/login with valid credentials returns token', function () {
    $user = User::factory()->create([
        'email' => 'testuser@example.com',
        'password' => Hash::make('password'),
        'role_id' => null,
        'company_id' => null,
        'active' => true,
    ]);

    $response = $this->postJson('/api/auth/login', [
        'email' => 'testuser@example.com',
        'password' => 'password',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'access_token',
            'token_type',
            'user' => [
                'id',
                'name',
                'email',
            ],
        ]);
    expect($response->json('token_type'))->toBe('Bearer');
});

test('GET /api/v1/auth/me requires authentication', function () {
    $response = $this->getJson('/api/v1/auth/me');

    $response->assertStatus(401);
});

test('GET /api/v1/auth/me with valid token returns user', function () {
    $user = User::factory()->create([
        'email' => 'me@example.com',
        'password' => Hash::make('password'),
        'role_id' => null,
        'company_id' => null,
        'active' => true,
    ]);

    $login = $this->postJson('/api/auth/login', [
        'email' => 'me@example.com',
        'password' => 'password',
    ]);
    $token = $login->json('access_token');

    $response = $this->withHeader('Authorization', 'Bearer ' . $token)
        ->getJson('/api/v1/auth/me');

    $response->assertStatus(200)
        ->assertJsonStructure(['user' => ['id', 'name', 'email']]);
});
