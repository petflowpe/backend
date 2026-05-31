<?php

use App\Models\Company;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $company = Company::factory()->create(['activo' => true]);
    $user = User::factory()->create([
        'company_id' => $company->id,
        'active' => true,
    ]);
    Sanctum::actingAs($user, ['*']);
});

test('GET /api/v2/config/masters returns catalogs', function () {
    $response = $this->getJson('/api/v2/config/masters');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'data' => [
                'documentTypes',
                'clientTypes',
                'clientStatuses',
                'petSpecies',
                'petGenders',
                'breedsBySpecies',
                'temperaments',
                'behaviors',
                'appointmentStatuses',
                'paymentMethods',
                'paymentStatuses',
                'geo' => ['regions', 'provinces', 'districts'],
                'currencies',
                'modules',
            ],
        ]);
});

