<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\Payment\MercadoPagoService;
use App\Services\Payment\NiubizService;
use App\Services\PaymentGatewaySettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentGatewayController extends Controller
{
    public function __construct(
        protected PaymentGatewaySettingsService $settings
    ) {}

    private function authorizeCompany(Request $request, Company $company): ?JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'No autenticado'], 401);
        }
        if ($user->hasRole('super_admin')) {
            return null;
        }
        if ((int) $user->company_id !== (int) $company->id) {
            return response()->json(['success' => false, 'message' => 'Sin permisos'], 403);
        }

        return null;
    }

    public function show(Request $request, Company $company): JsonResponse
    {
        if ($deny = $this->authorizeCompany($request, $company)) {
            return $deny;
        }

        return response()->json([
            'success' => true,
            'data' => $this->settings->forApi($company->id),
        ]);
    }

    public function update(Request $request, Company $company): JsonResponse
    {
        if ($deny = $this->authorizeCompany($request, $company)) {
            return $deny;
        }

        $validated = $request->validate([
            'mercado_pago' => ['nullable', 'array'],
            'mercado_pago.enabled' => ['nullable', 'boolean'],
            'mercado_pago.environment' => ['nullable', 'string', 'in:sandbox,production'],
            'mercado_pago.public_key' => ['nullable', 'string', 'max:255'],
            'mercado_pago.access_token' => ['nullable', 'string', 'max:500'],
            'mercado_pago.webhook_secret' => ['nullable', 'string', 'max:255'],
            'niubiz' => ['nullable', 'array'],
            'niubiz.enabled' => ['nullable', 'boolean'],
            'niubiz.environment' => ['nullable', 'string', 'in:sandbox,production'],
            'niubiz.merchant_id' => ['nullable', 'string', 'max:50'],
            'niubiz.user' => ['nullable', 'string', 'max:100'],
            'niubiz.password' => ['nullable', 'string', 'max:200'],
        ]);

        $this->settings->save($company->id, $validated);

        return response()->json([
            'success' => true,
            'data' => $this->settings->forApi($company->id),
            'message' => 'Pasarelas actualizadas',
        ]);
    }

    public function test(
        Request $request,
        Company $company,
        string $gateway,
        MercadoPagoService $mercadoPago,
        NiubizService $niubiz
    ): JsonResponse {
        if ($deny = $this->authorizeCompany($request, $company)) {
            return $deny;
        }

        $result = match ($gateway) {
            'mercado_pago' => $mercadoPago->testConnection($company->id),
            'niubiz' => $niubiz->testConnection($company->id),
            default => ['ok' => false, 'message' => 'Pasarela no válida'],
        };

        return response()->json([
            'success' => (bool) ($result['ok'] ?? false),
            'message' => $result['message'] ?? '',
        ], ($result['ok'] ?? false) ? 200 : 422);
    }
}
