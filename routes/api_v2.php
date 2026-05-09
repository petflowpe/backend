<?php

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\EnsureUserCompanyScope;
use App\Http\Controllers\Api\V2\ClientController as V2ClientController;
use App\Http\Controllers\Api\V2\ConfigController as V2ConfigController;
use App\Http\Controllers\Api\V2\BillingDocumentController as V2BillingDocumentController;
use App\Http\Controllers\Api\V2\CompanyTaxProfileController as V2CompanyTaxProfileController;

/**
 * API v2
 * - Mantiene /api/v1 estable (legacy)
 * - Expone /api/v2 con contrato camelCase (para React/TS)
 */
Route::middleware(['auth:sanctum', 'throttle:api', EnsureUserCompanyScope::class])
    ->prefix('v2')
    ->group(function () {
        // Configuración / catálogos (masters) para el frontend
        Route::get('config/masters', [V2ConfigController::class, 'masters']);

        // Perfil fiscal por empresa (multi-país)
        Route::apiResource('company-tax-profiles', V2CompanyTaxProfileController::class);

        // Billing (multi-país, contrato estable)
        Route::prefix('billing')->group(function () {
            Route::get('documents', [V2BillingDocumentController::class, 'index']);
            Route::post('documents', [V2BillingDocumentController::class, 'store']);
            Route::get('documents/{billingDocument}', [V2BillingDocumentController::class, 'show']);
            Route::post('documents/{billingDocument}/submit', [V2BillingDocumentController::class, 'submit']);
            Route::get('documents/{billingDocument}/status', [V2BillingDocumentController::class, 'status']);
            Route::get('documents/{billingDocument}/artifacts/{type}', [V2BillingDocumentController::class, 'artifact']);
        });

        // Clientes (contrato camelCase)
        Route::apiResource('clients', V2ClientController::class);
        Route::post('clients/{client}/pets', [V2ClientController::class, 'addPet']);
    });

