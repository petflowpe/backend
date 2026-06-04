<?php

namespace App\Services\Payment;

use App\Models\Payment;
use App\Services\PaymentGatewaySettingsService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class NiubizService
{
    public function __construct(
        protected PaymentGatewaySettingsService $settings
    ) {}

    protected function baseUrl(string $environment): string
    {
        return $environment === 'production'
            ? 'https://apiprod.vnforapps.com'
            : 'https://apisandbox.vnforappstest.com';
    }

    public function isConfigured(int $companyId): bool
    {
        $cfg = $this->settings->credentials($companyId, 'niubiz');

        return !empty($cfg['enabled'])
            && !empty($cfg['merchant_id'])
            && !empty($cfg['user'])
            && !empty($cfg['password']);
    }

    public function testConnection(int $companyId): array
    {
        try {
            $token = $this->securityToken($companyId);

            return $token
                ? ['ok' => true, 'message' => 'Conexión exitosa con Niubiz']
                : ['ok' => false, 'message' => 'No se obtuvo token de seguridad'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    protected function securityToken(int $companyId): ?string
    {
        $cfg = $this->settings->credentials($companyId, 'niubiz');
        $user = $cfg['user'] ?? '';
        $password = $cfg['password'] ?? '';
        if ($user === '' || $password === '') {
            throw new \RuntimeException('Credenciales Niubiz incompletas');
        }

        $base = $this->baseUrl($cfg['environment'] ?? 'sandbox');
        $response = Http::withHeaders([
            'Authorization' => 'Basic ' . base64_encode($user . ':' . $password),
        ])->post($base . '/api.security/v1/security');

        if (!$response->successful()) {
            throw new \RuntimeException('Error de autenticación Niubiz');
        }

        return trim((string) $response->body(), '"');
    }

    /**
     * @return array{checkout_url: string, session_key: string, purchase_number: string}
     */
    public function createSession(Payment $payment, string $email = 'cliente@smartpet.pe'): array
    {
        $cfg = $this->settings->credentials($payment->company_id, 'niubiz');
        $merchantId = $cfg['merchant_id'] ?? '';
        if ($merchantId === '') {
            throw new \RuntimeException('Merchant ID de Niubiz no configurado');
        }

        $token = $this->securityToken($payment->company_id);
        $base = $this->baseUrl($cfg['environment'] ?? 'sandbox');
        $purchaseNumber = (string) ($payment->id . time());
        $amount = number_format((float) $payment->amount, 2, '.', '');

        $response = Http::withToken($token)
            ->acceptJson()
            ->post($base . '/api.ecommerce/v2/ecommerce/token/session/' . $merchantId, [
                'channel' => 'web',
                'amount' => (float) $amount,
                'antifraud' => [
                    'clientIp' => request()->ip() ?? '127.0.0.1',
                    'merchantDefineData' => [
                        'MDD4' => $email,
                        'MDD21' => '0',
                        'MDD32' => (string) $payment->id,
                        'MDD75' => 'Registrado',
                        'MDD77' => '1',
                    ],
                ],
                'dataMap' => [
                    'cardholderCity' => 'Lima',
                    'cardholderCountry' => 'PE',
                    'cardholderAddress' => 'Lima',
                    'cardholderPostalCode' => '15001',
                    'cardholderState' => 'LIM',
                    'cardholderPhoneNumber' => '999999999',
                    'email' => $email,
                ],
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException(
                $response->json('errorMessage') ?? 'Error al crear sesión Niubiz'
            );
        }

        $data = $response->json();
        $sessionKey = $data['sessionKey'] ?? $data['sessionkey'] ?? '';
        if ($sessionKey === '') {
            throw new \RuntimeException('Niubiz no devolvió sessionKey');
        }

        $externalRef = 'SPT-NV-' . $payment->id . '-' . Str::lower(Str::random(4));
        $payment->update([
            'reference' => $externalRef,
            'external_id' => $purchaseNumber,
            'metadata' => array_merge($payment->metadata ?? [], [
                'session_key' => $sessionKey,
                'purchase_number' => $purchaseNumber,
            ]),
        ]);

        $checkoutUrl = $base . '/api.ecommerce/v2/ecommerce/token/checkout/' . $merchantId . '/' . $sessionKey;

        return [
            'checkout_url' => $checkoutUrl,
            'session_key' => $sessionKey,
            'purchase_number' => $purchaseNumber,
        ];
    }

    public function markCompletedFromWebhook(Payment $payment, array $payload): Payment
    {
        $status = match (strtoupper((string) ($payload['status'] ?? $payload['ACTION'] ?? ''))) {
            'AUTHORIZED', 'APPROVED', 'CONFIRM' => 'completed',
            'REJECTED', 'DENIED' => 'failed',
            default => 'pending',
        };

        $payment->update([
            'status' => $status,
            'paid_at' => $status === 'completed' ? now() : $payment->paid_at,
            'metadata' => array_merge($payment->metadata ?? [], ['niubiz_webhook' => $payload]),
        ]);

        if ($status === 'completed' && $payment->appointment_id) {
            $payment->appointment?->update([
                'payment_status' => 'Pagado',
                'payment_method' => 'Tarjeta',
            ]);
        }

        return $payment;
    }
}
