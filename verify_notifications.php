<?php

use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "--- Verificación de Sistema de Notificaciones ---\n";

DB::beginTransaction();

try {
    // 1. Limpiar notificaciones previas de prueba
    Notification::where('title', 'LIKE', '[TEST]%')->delete();

    // 2. Crear notificaciones de prueba
    echo "Creando notificaciones de prueba...\n";

    Notification::create([
        'company_id' => 1,
        'type' => 'appointment',
        'priority' => 'high',
        'category' => 'Próxima Cita',
        'title' => '[TEST] Cita Próxima: Max',
        'message' => 'Recordatorio: La cita de Max (Golden Retriever) es en 1 hora.',
        'action_required' => true,
        'related_module' => 'appointments',
        'related_id' => '1',
        'data' => ['time' => '10:30 AM', 'pet' => 'Max']
    ]);

    Notification::create([
        'company_id' => 1,
        'type' => 'inventory',
        'priority' => 'critical',
        'category' => 'Stock Bajo',
        'title' => '[TEST] Alerta de Inventario: Shampoo Medicado',
        'message' => 'Quedan menos de 5 unidades de Shampoo Medicado en el almacén principal.',
        'action_required' => true,
        'related_module' => 'products',
        'related_id' => '2',
    ]);

    Notification::create([
        'company_id' => 1,
        'type' => 'payment',
        'priority' => 'medium',
        'category' => 'Facturación',
        'title' => '[TEST] Factura Aceptada por SUNAT',
        'message' => 'La factura F001-00124 ha sido procesada y aceptada correctamente.',
        'read' => true,
    ]);

    echo "Notificaciones creadas exitosamente.\n";

    // 3. Verificar consulta
    $count = Notification::where('company_id', 1)->count();
    echo "Total de notificaciones en BD para Empresa 1: $count\n";

    $unread = Notification::where('company_id', 1)->where('read', false)->count();
    echo "Notificaciones no leídas: $unread\n";

    DB::commit();
    echo "\n--- Verificación Exitosa ---\n";

} catch (\Exception $e) {
    DB::rollBack();
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
