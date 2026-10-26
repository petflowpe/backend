<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cloudflare Turnstile. Si no hay secret en .env, no exige token (compatibilidad).
 */
class TurnstileVerifier
{
    public function isEnabled(): bool
    {
        return filled(config('smartpet.turnstile.secret_key'));
    }

    public function siteKey(): ?string
    {
        $key = config('smartpet.turnstile.site_key');

        return filled($key) ? (string) $key : null;
    }

    /**
     * @return array{ok: bool, message?: string}
     */
    public function verify(?string $token, ?string $remoteIp = null): array
    {
        if (! $this->isEnabled()) {
            return ['ok' => true];
        }

        if (! filled($token)) {
            return ['ok' => false, 'message' => 'Verificación anti-bot requerida.'];
        }

        try {
            $response = Http::asForm()
                ->timeout(8)
                ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                    'secret' => config('smartpet.turnstile.secret_key'),
                    'response' => $token,
                    'remoteip' => $remoteIp,
                ]);

            $json = $response->json();
            if (! empty($json['success'])) {
                return ['ok' => true];
            }

            Log::info('Turnstile rechazado', ['codes' => $json['error-codes'] ?? []]);

            return ['ok' => false, 'message' => 'Verificación anti-bot fallida. Recarga e intenta de nuevo.'];
        } catch (\Throwable $e) {
            Log::warning('Turnstile error de red', ['error' => $e->getMessage()]);

            // Fail-open suave: no tumbar reservas si Cloudflare cae.
            return ['ok' => true];
        }
    }
}
