<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Seeder;

class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        $companyId = Company::first()?->id ?? 1;
        $userId = User::first()?->id;

        $items = [
            [
                'type' => 'appointment',
                'priority' => 'high',
                'category' => 'Confirmación',
                'title' => 'Cita confirmada',
                'message' => 'Un cliente confirmó su cita para mañana 09:00 - Baño y corte.',
                'read' => false,
                'action_required' => false,
                'related_module' => 'appointments',
                'related_id' => '1',
            ],
            [
                'type' => 'appointment',
                'priority' => 'critical',
                'category' => 'Recordatorio',
                'title' => 'Cita en 1 hora',
                'message' => 'Próxima cita: Baño + Corte - Ver agenda.',
                'read' => false,
                'action_required' => true,
                'related_module' => 'appointments',
                'related_id' => '2',
            ],
            [
                'type' => 'payment',
                'priority' => 'medium',
                'category' => 'Pago recibido',
                'title' => 'Pago registrado',
                'message' => 'Se registró un pago de S/ 85.00 en caja.',
                'read' => true,
                'action_required' => false,
                'related_module' => 'payments',
            ],
            [
                'type' => 'inventory',
                'priority' => 'high',
                'category' => 'Stock bajo',
                'title' => 'Producto con stock bajo',
                'message' => 'Shampoo Premium tiene menos de 10 unidades. Considerar reorden.',
                'read' => false,
                'action_required' => true,
                'related_module' => 'inventory',
                'related_id' => '1',
            ],
            [
                'type' => 'system',
                'priority' => 'low',
                'category' => 'Sistema',
                'title' => 'Bienvenido al sistema',
                'message' => 'Tu sesión se ha iniciado correctamente.',
                'read' => true,
                'action_required' => false,
            ],
        ];

        if (Notification::where('company_id', $companyId)->exists()) {
            return;
        }

        foreach ($items as $item) {
            Notification::create(array_merge($item, [
                'company_id' => $companyId,
                'user_id' => $userId,
            ]));
        }

        Notification::create([
            'company_id' => $companyId,
            'user_id' => null,
            'type' => 'financial',
            'priority' => 'medium',
            'category' => 'Resumen',
            'title' => 'Cierre de caja pendiente',
            'message' => 'Recuerda realizar el cierre de caja al final del turno.',
            'read' => false,
            'action_required' => false,
            'related_module' => 'cash-register',
        ]);
    }
}
