<?php

namespace App\Services;

use App\Models\CompanyConfiguration;

class PaymentGatewaySettingsService
{
    public const CONFIG_TYPE = 'payment_gateways';

    public function defaults(): array
    {
        return [
            'mercado_pago' => [
                'enabled' => false,
                'environment' => 'sandbox',
                'public_key' => '',
                'access_token' => '',
                'webhook_secret' => '',
            ],
            'niubiz' => [
                'enabled' => false,
                'environment' => 'sandbox',
                'merchant_id' => '',
                'user' => '',
                'password' => '',
            ],
        ];
    }

    public function get(int $companyId): array
    {
        $row = CompanyConfiguration::where('company_id', $companyId)
            ->where('config_type', self::CONFIG_TYPE)
            ->first();

        if (!$row || !is_array($row->config_data)) {
            return $this->defaults();
        }

        return array_replace_recursive($this->defaults(), $row->config_data);
    }

    public function save(int $companyId, array $payload): array
    {
        $current = $this->get($companyId);

        foreach (['mercado_pago', 'niubiz'] as $gateway) {
            if (!isset($payload[$gateway]) || !is_array($payload[$gateway])) {
                continue;
            }
            $incoming = $payload[$gateway];
            if (array_key_exists('access_token', $incoming) && $incoming['access_token'] === '') {
                unset($incoming['access_token']);
            }
            if (array_key_exists('password', $incoming) && $incoming['password'] === '') {
                unset($incoming['password']);
            }
            $current[$gateway] = array_merge($current[$gateway] ?? [], $incoming);
        }

        CompanyConfiguration::updateOrCreate(
            ['company_id' => $companyId, 'config_type' => self::CONFIG_TYPE],
            ['config_data' => $current, 'is_active' => true]
        );

        return $current;
    }

    public function forApi(int $companyId): array
    {
        $config = $this->get($companyId);
        $mp = $config['mercado_pago'] ?? [];
        $nv = $config['niubiz'] ?? [];

        return [
            'mercado_pago' => [
                'enabled' => (bool) ($mp['enabled'] ?? false),
                'environment' => $mp['environment'] ?? 'sandbox',
                'public_key' => $mp['public_key'] ?? '',
                'has_access_token' => !empty($mp['access_token']),
                'has_webhook_secret' => !empty($mp['webhook_secret']),
            ],
            'niubiz' => [
                'enabled' => (bool) ($nv['enabled'] ?? false),
                'environment' => $nv['environment'] ?? 'sandbox',
                'merchant_id' => $nv['merchant_id'] ?? '',
                'user' => $nv['user'] ?? '',
                'has_password' => !empty($nv['password']),
            ],
        ];
    }

    public function credentials(int $companyId, string $gateway): array
    {
        $config = $this->get($companyId);

        return $config[$gateway] ?? [];
    }
}
