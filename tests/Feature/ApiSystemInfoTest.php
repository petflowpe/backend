<?php

use App\Models\User;

test('GET /api/system/info returns 200 and system info', function () {
    $response = $this->getJson('/api/system/info');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'system_initialized',
            'user_count',
            'app_name',
            'app_env',
            'database_connected',
        ]);
});

test('GET /api/system/info returns correct structure when no users', function () {
    $response = $this->getJson('/api/system/info');

    $response->assertStatus(200);
    $data = $response->json();
    expect($data['system_initialized'])->toBeFalse();
    expect($data['user_count'])->toBe(0);
});
