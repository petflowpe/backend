<?php

namespace App\Services\Payment;

use App\Models\Payment;
use App\Services\PaymentGatewaySettingsService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class MercadoPagoService
{
    public function __construct(
        protected PaymentGatewaySettingsService $settings
    ) {}

    public function isConfigured(int $companyId): bool
    {
        $cfg = $this->settings->credentials($companyId, 'mercado_pago');

        return !empty($cfg['enabled']) && !empty($cfg['access_token']);
    }

    public function testConnection(int $companyId): array
    {
        $token = $this->settings->credentials($companyId, 'mercado_pago')['access_token'] ?? '';
        if ($token === '') {
            return ['ok' => false, 'message' => 'Access token de Mercado Pago no configurado'];
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->get('https://api.mercadopago.com/users/me');

        if ($response->successful()) {
            return ['ok' => true, 'message' => 'Conexión exitosa con Mercado Pago'];
        }

        return [
            'ok' => false,
            'message' => $response->json('message') ?? 'No se pudo validar credenciales',
        ];
    }

    /**
     * @return array{checkout_url: string, preference_id: string, external_reference: string}
     */
    public function createPreference(Payment $payment, string $title, string $payerEmail = null): array
    {
        $cfg = $this->settings->credentials($payment->company_id, 'mercado_pago');
        $token = $cfg['access_token'] ?? '';
        if ($token === '') {
            throw new \RuntimeException('Mercado Pago no está configurado');
        }

        $externalRef = 'SPT-MP-' . $payment->id . '-' . Str::lower(Str::random(6));
        $baseUrl = rtrim(config('app.url', env('APP_URL', 'http://localhost')), '/');
        $notificationUrl = $baseUrl . '/api/public/webhooks/mercadopago';

        $body = [
            'items' => [
                [
                    'title' => Str::limit($title, 120),
                    'quantity' => 1,
                    'unit_price' => (float) $payment->amount,
                    'currency_id' => $payment->currency === 'PEN' ? 'PEN' : 'PEN',
                ],
            ],
            'external_reference' => $externalRef,
            'notification_url' => $notificationUrl,
            'back_urls' => [
                'success' => $baseUrl . '/?tab=payments&mp=success',
                'failure' => $baseUrl . '/?tab=payments&mp=failure',
                'pending' => $baseUrl . '/?tab=payments&mp=pending',
            ],
            'auto_return' => 'approved',
        ];

        if ($payerEmail) {
            $body['payer'] = ['email' => $payerEmail];
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->post('https://api.mercadopago.com/checkout/preferences', $body);

        if (!$response->successful()) {
            throw new \RuntimeException(
                $response->json('message') ?? 'Error al crear preferencia en Mercado Pago'
            );
        }

        $data = $response->json();
        $isSandbox = ($cfg['environment'] ?? 'sandbox') === 'sandbox';
        $checkoutUrl = $isSandbox
            ? ($data['sandbox_init_point'] ?? $data['init_point'] ?? '')
            : ($data['init_point'] ?? '');

        $payment->update([
            'external_id' => (string) ($data['id'] ?? ''),
            'reference' => $externalRef,
            'metadata' => array_merge($payment->metadata ?? [], [
                'preference_id' => $data['id'] ?? null,
            ]),
        ]);

        return [
            'checkout_url' => $checkoutUrl,
            'preference_id' => (string) ($data['id'] ?? ''),
            'external_reference' => $externalRef,
        ];
    }

    public function handleWebhook(int $companyId, array $payload): ?Payment
    {
        $paymentId = $payload['data']['id'] ?? $payload['id'] ?? null;
        if (!$paymentId) {
            return null;
        }

        $cfg = $this->settings->credentials($companyId, 'mercado_pago');
        $token = $cfg['access_token'] ?? '';
        if ($token === '') {
            return null;
        }

        $mpPayment = Http::withToken($token)
            ->acceptJson()
            ->get('https://api.mercadopago.com/v1/payments/' . $paymentId);

        if (!$mpPayment->successful()) {
            return null;
        }

        $mpData = $mpPayment->json();
        $externalRef = $mpData['external_reference'] ?? null;
        if (!$externalRef) {
            return null;
        }

        $payment = Payment::where('company_id', $companyId)
            ->where('reference', $externalRef)
            ->where('gateway', 'mercado_pago')
            ->first();

        if (!$payment) {
            return null;
        }

        $status = match ($mpData['status'] ?? '') {
            'approved' => 'completed',
            'pending', 'in_process' => 'pending',
            'rejected', 'cancelled' => 'failed',
            'refunded' => 'refunded',
            default => $payment->status,
        };

        $fee = (float) ($mpData['fee_details'][0]['amount'] ?? 0);
        $amount = (float) ($mpData['transaction_amount'] ?? $payment->amount);

        $payment->update([
            'status' => $status,
            'external_id' => (string) $paymentId,
            'fee' => $fee,
            'net_amount' => max(0, $amount - $fee),
            'paid_at' => $status === 'completed' ? now() : $payment->paid_at,
            'metadata' => array_merge($payment->metadata ?? [], ['mp_payment' => $mpData]),
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
