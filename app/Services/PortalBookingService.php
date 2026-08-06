<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\CompanyConfiguration;
use App\Models\Notification;
use Carbon\Carbon;
use Illuminate\Support\Str;

class PortalBookingService
{
    public const SOURCE_STAFF = 'staff';
    public const SOURCE_PORTAL_AUTH = 'portal_auth';
    public const SOURCE_PUBLIC_GUEST = 'public_guest';

    public const APPROVAL_PENDING = 'pending';
    public const APPROVAL_APPROVED = 'approved';
    public const APPROVAL_REJECTED = 'rejected';

    /** Configuración por defecto del portal por empresa. */
    public function defaultSettings(): array
    {
        return [
            'guest_booking_enabled' => false,
            'registered_only' => true,
            'require_advance' => true,
            'advance_type' => 'percent',
            'advance_value' => 30,
            'payment_mode' => 'simulated',
            'auto_confirm_on_advance' => true,
            'new_clients_require_approval' => true,
        ];
    }

    public function getSettings(int $companyId): array
    {
        $config = CompanyConfiguration::query()
            ->where('company_id', $companyId)
            ->where('config_type', 'portal_settings')
            ->where('is_active', true)
            ->first();

        $stored = is_array($config?->config_data) ? $config->config_data : [];

        return array_merge($this->defaultSettings(), $stored);
    }

    public function calculateAdvance(float $price, array $settings): float
    {
        if (empty($settings['require_advance'])) {
            return 0.0;
        }

        $type = $settings['advance_type'] ?? 'percent';
        $value = (float) ($settings['advance_value'] ?? 30);

        if ($type === 'fixed') {
            return round(min($value, $price), 2);
        }

        return round($price * ($value / 100), 2);
    }

    public function canClientBook(?Client $client, array $settings): array
    {
        if (!$client) {
            return ['allowed' => false, 'message' => 'Cliente no encontrado'];
        }

        if (!$client->activo) {
            return ['allowed' => false, 'message' => 'Tu cuenta de cliente está inactiva. Contacta a la clínica.'];
        }

        if (!empty($settings['registered_only']) && empty($settings['guest_booking_enabled'])) {
            if (!$client->portal_booking_enabled) {
                return [
                    'allowed' => false,
                    'message' => 'El auto-agendado por portal no está habilitado para tu perfil. Solicita activación al staff.',
                ];
            }
        }

        $status = $client->portal_approval_status ?? self::APPROVAL_APPROVED;
        if ($status === self::APPROVAL_PENDING) {
            return [
                'allowed' => false,
                'message' => 'Tu cuenta está pendiente de validación por el equipo. Te avisaremos cuando puedas reservar.',
            ];
        }

        if ($status === self::APPROVAL_REJECTED) {
            return [
                'allowed' => false,
                'message' => 'No tienes autorización para reservar por portal. Contacta a la clínica.',
            ];
        }

        return ['allowed' => true, 'message' => null];
    }

    public function resolveStatusForPortalBooking(
        Client $client,
        array $settings,
        bool $advancePaid,
        float $advanceAmount
    ): array {
        if ($advancePaid && $advanceAmount > 0 && !empty($settings['auto_confirm_on_advance'])) {
            return [
                'status' => 'Confirmada',
                'payment_status' => 'Pagado',
                'confirmed_at' => now(),
            ];
        }

        return [
            'status' => 'Pendiente',
            'payment_status' => 'Pendiente',
            'confirmed_at' => null,
        ];
    }

    public function applyAdvancePayment(
        Appointment $appointment,
        string $method,
        ?string $reference = null
    ): Appointment {
        $appointment->advance_paid_at = now();
        $appointment->advance_payment_method = $method;
        $appointment->advance_payment_reference = $reference;

        $settings = $this->getSettings((int) $appointment->company_id);
        if (!empty($settings['auto_confirm_on_advance'])) {
            $appointment->status = 'Confirmada';
            $appointment->payment_status = 'Pagado';
            $appointment->confirmed_at = $appointment->confirmed_at ?? now();
        }

        $appointment->save();

        $this->notifyStaffPortalBooking($appointment->fresh(['client', 'pet']), 'advance_paid');
        $this->notifyClientPortalBooking($appointment->fresh(['client', 'pet']), 'advance_paid');

        return $appointment;
    }

    public function notifyStaffPortalBooking(Appointment $appointment, string $event = 'created'): void
    {
        $clientName = $appointment->client?->razon_social ?? 'Cliente';
        $petName = $appointment->pet?->name ?? 'Mascota';
        $date = $appointment->date instanceof Carbon
            ? $appointment->date->format('d/m/Y')
            : Carbon::parse($appointment->date)->format('d/m/Y');

        $titles = [
            'created' => 'Nueva reserva desde portal',
            'advance_paid' => 'Adelanto pagado — reserva portal',
            'pending_approval' => 'Reserva portal pendiente de validación',
        ];

        $messages = [
            'created' => "{$clientName} reservó {$appointment->service_name} para {$petName} el {$date} a las {$appointment->time}.",
            'advance_paid' => "Se registró adelanto de S/ {$appointment->advance_amount} para cita de {$clientName} ({$petName}).",
            'pending_approval' => "Reserva portal de {$clientName} requiere validación del staff antes de confirmar.",
        ];

        Notification::create([
            'company_id' => $appointment->company_id,
            'user_id' => null,
            'type' => $event === 'advance_paid' ? 'success' : 'info',
            'priority' => $appointment->status === 'Pendiente' ? 'high' : 'normal',
            'category' => 'appointments',
            'title' => $titles[$event] ?? $titles['created'],
            'message' => $messages[$event] ?? $messages['created'],
            'read' => false,
            'action_required' => $appointment->status === 'Pendiente',
            'related_module' => 'appointments',
            'related_id' => (string) $appointment->id,
            'data' => [
                'appointment_id' => $appointment->id,
                'booking_source' => $appointment->booking_source,
                'status' => $appointment->status,
                'tracking_code' => $appointment->tracking_code,
            ],
        ]);
    }

    public function notifyClientPortalBooking(Appointment $appointment, string $event = 'created'): void
    {
        // Notificación a usuarios vinculados por email/documento se implementará en fase portal.
        // Reservado para integración con user_id del cliente.
    }

    public function generateTrackingCode(): string
    {
        do {
            $code = 'SPT-' . strtoupper(Str::random(6));
        } while (Appointment::where('tracking_code', $code)->exists());

        return $code;
    }
}
