<?php

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Pet;
use App\Models\Service;
use Illuminate\Support\Str;
use Carbon\Carbon;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "--- Verificación de Lógica de Citas Recurrentes ---\n";

try {
    // 1. Obtener datos básicos
    $client = Client::first();
    $pet = Pet::first();
    $service = Service::where('name', 'Corte de Pelo')->first();

    if (!$client || !$pet || !$service) {
        throw new Exception("No hay datos básicos (cliente, mascota o servicio) para la prueba.");
    }

    // 2. Simular Request de Cita Recurrente (Semanal, 4 ocurrencias)
    echo "Simulando creación de serie semanal (4 citas)...\n";

    $data = [
        'client_id' => $client->id,
        'pet_id' => $pet->id,
        'company_id' => 1,
        'service_id' => $service->id,
        'service_type' => 'Peluquería',
        'service_name' => 'Corte de Pelo',
        'service_category' => 'Peluquería',
        'date' => Carbon::today()->addDay()->toDateString(),
        'time' => '10:00',
        'address' => 'Av. de prueba 123',
        'price' => 50.00,
        'is_recurring' => true,
        'recurrence_type' => 'weekly',
        'recurrence_occurrences' => 4,
    ];

    $response = app(\App\Http\Controllers\Api\AppointmentController::class)->store(new \Illuminate\Http\Request($data));
    $responseData = json_decode($response->getContent(), true);

    if (!$responseData['success']) {
        throw new Exception("Error en el controller: " . ($responseData['message'] ?? 'Desconocido'));
    }

    echo "Serie creada exitosamente. Mensaje: " . $responseData['message'] . "\n";
    echo "Conteo informado: " . $responseData['series_count'] . "\n";

    // 3. Verificar en BD
    $seriesId = $responseData['data']['recurrence_series_id'];
    $appointments = Appointment::where('recurrence_series_id', $seriesId)->orderBy('date')->get();

    echo "Total de citas encontradas en BD para la serie: " . $appointments->count() . "\n";

    foreach ($appointments as $index => $appt) {
        echo "Cita " . ($index + 1) . ": {$appt->date} {$appt->time}\n";
    }

    if ($appointments->count() !== 4) {
        throw new Exception("Se esperaban 4 citas, pero hay " . $appointments->count());
    }

    echo "\n--- Verificación Exitosa ---\n";

} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
