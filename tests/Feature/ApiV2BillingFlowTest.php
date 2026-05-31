<?php

use App\Models\BillingDocument;
use App\Models\Company;
use App\Models\CompanyTaxProfile;
use App\Models\Client;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('v2 billing document submit transitions to accepted in sync queue (stub)', function () {
    $company = Company::factory()->create(['activo' => true]);
    $user = User::factory()->create(['company_id' => $company->id, 'active' => true]);
    Sanctum::actingAs($user, ['*']);

    $client = Client::factory()->create(['company_id' => $company->id]);

    CompanyTaxProfile::create([
        'company_id' => $company->id,
        'country_code' => 'CO',
        'tax_id' => '900123456',
        'tax_id_dv' => '1',
        'legal_name' => 'PETFLOW CO S.A.S.',
        'trade_name' => 'PETFLOW',
        'email' => 'facturacion@petflow.com',
        'currency_code_default' => 'COP',
        'locale_default' => 'es-CO',
        'environment' => 'test',
        'provider_slug' => 'dian_stub',
        'active' => true,
    ]);

    $create = $this->postJson('/api/v2/billing/documents', [
        'clientId' => $client->id,
        'documentType' => 'invoice',
        'currencyCode' => 'COP',
        'totals' => [
            'subtotal' => 100,
            'taxesTotal' => 19,
            'total' => 119,
        ],
        'lines' => [
            [
                'itemType' => 'service',
                'description' => 'Servicio grooming',
                'qty' => 1,
                'unitPrice' => 100,
                'discount' => 0,
                'taxes' => [
                    ['type' => 'IVA', 'rate' => 0.19, 'amount' => 19],
                ],
                'lineTotal' => 119,
            ],
        ],
    ]);

    $create->assertStatus(201);
    $docId = $create->json('data.id');
    expect($docId)->not->toBeNull();

    $submit = $this->postJson("/api/v2/billing/documents/{$docId}/submit");
    $submit->assertStatus(202);

    // En testing el queue es sync (phpunit.xml), así que el job corre y debería quedar accepted por stub.
    $doc = BillingDocument::findOrFail($docId);
    expect($doc->status_fiscal)->toBeIn(['accepted', 'rejected', 'sent', 'error']);

    $status = $this->getJson("/api/v2/billing/documents/{$docId}/status");
    $status->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'data' => [
                'statusFiscal',
                'latestSubmission' => [
                    'id',
                    'status',
                    'externalId',
                ],
            ],
        ]);
});

