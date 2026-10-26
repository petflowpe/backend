<?php

namespace App\Services;

use App\Models\Appointment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * OTP corto para desbloquear detalle de seguimiento (cache, TTL 10 min).
 * Entrega: log + mail si hay email del cliente (sin SMS externo aún).
 */
class TrackingOtpService
{
    private const TTL_SECONDS = 600;

    public function cacheKey(string $trackingCode): string
    {
        return 'tracking_otp:' . strtoupper(trim($trackingCode));
    }

    /**
     * @return array{sent: bool, channel: string, hint?: string}
     */
    public function issue(Appointment $appointment): array
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Cache::put($this->cacheKey((string) $appointment->tracking_code), $code, self::TTL_SECONDS);

        $email = trim((string) ($appointment->client?->email ?? ''));
        $phone = preg_replace('/\D+/', '', (string) ($appointment->client?->telefono ?? '')) ?? '';

        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            try {
                Mail::raw(
                    "Tu código de seguimiento PetFlow es: {$code}\nVálido 10 minutos.\nReserva: {$appointment->tracking_code}",
                    function ($message) use ($email, $appointment) {
                        $message->to($email)
                            ->subject('Código de verificación — ' . $appointment->tracking_code);
                    }
                );

                return [
                    'sent' => true,
                    'channel' => 'email',
                    'hint' => $this->maskEmail($email),
                ];
            } catch (\Throwable $e) {
                Log::warning('No se pudo enviar OTP por email', [
                    'tracking' => $appointment->tracking_code,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Tracking OTP generado', [
            'tracking' => $appointment->tracking_code,
            'otp' => $code,
            'phone_hint' => $phone !== '' ? substr($phone, -4) : null,
        ]);

        return [
            'sent' => true,
            'channel' => $phone !== '' ? 'phone_log' : 'log',
            'hint' => $phone !== '' ? ('***' . substr($phone, -4)) : null,
        ];
    }

    public function verify(string $trackingCode, ?string $otp): bool
    {
        if (! filled($otp)) {
            return false;
        }

        $expected = Cache::get($this->cacheKey($trackingCode));
        if (! is_string($expected) || $expected === '') {
            return false;
        }

        return hash_equals($expected, trim($otp));
    }

    private function maskEmail(string $email): string
    {
        [$user, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $u = mb_substr($user, 0, 1) . '***';

        return $u . '@' . $domain;
    }
}
