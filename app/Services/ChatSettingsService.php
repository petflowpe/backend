<?php

namespace App\Services;

use App\Models\CompanyConfiguration;
use Illuminate\Support\Str;

class ChatSettingsService
{
    public const CONFIG_TYPE = 'chat_settings';

    public function defaults(): array
    {
        return [
            'enabled' => true,
            'agent_name' => 'Soporte SmartPet',
            'agent_role' => 'Asistente en línea',
            'welcome_message' => '¡Hola! 👋 ¿En qué podemos ayudarte hoy?',
            'auto_replies' => [
                [
                    'id' => '1',
                    'trigger' => 'precio',
                    'response' => 'Nuestros precios dependen del servicio y tamaño de mascota. ¿Qué servicio necesitas?',
                    'is_active' => true,
                ],
                [
                    'id' => '2',
                    'trigger' => 'horario',
                    'response' => 'Atendemos de lunes a sábado según disponibilidad en tu distrito. ¿Quieres agendar una cita?',
                    'is_active' => true,
                ],
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

        return array_merge($this->defaults(), $row->config_data);
    }

    public function save(int $companyId, array $payload): array
    {
        $merged = array_merge($this->get($companyId), $payload);

        CompanyConfiguration::updateOrCreate(
            ['company_id' => $companyId, 'config_type' => self::CONFIG_TYPE],
            ['config_data' => $merged]
        );

        return $merged;
    }

    public function matchAutoReply(int $companyId, string $message): ?string
    {
        $settings = $this->get($companyId);
        if (empty($settings['enabled'])) {
            return null;
        }

        $normalized = Str::lower(Str::ascii($message));

        foreach ($settings['auto_replies'] ?? [] as $rule) {
            if (empty($rule['is_active'])) {
                continue;
            }
            $trigger = Str::lower(Str::ascii((string) ($rule['trigger'] ?? '')));
            if ($trigger !== '' && str_contains($normalized, $trigger)) {
                return (string) ($rule['response'] ?? '');
            }
        }

        return null;
    }
}
