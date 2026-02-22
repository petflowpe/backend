<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->user = User::factory()->create([
        'email' => 'reports@test.com',
        'password' => Hash::make('password'),
        'role_id' => null,
        'company_id' => null,
        'active' => true,
    ]);
    $this->token = $this->postJson('/api/auth/login', [
        'email' => 'reports@test.com',
        'password' => 'password',
    ])->json('access_token');
});

test('GET /api/v1/reports/stats returns 200 with auth', function () {
    $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
        ->getJson('/api/v1/reports/stats?company_id=1');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'data' => [
                'total_sales',
                'appointments_count',
                'active_clients',
                'total_pets',
            ],
        ]);
    expect($response->json('success'))->toBeTrue();
});
