<?php
// Function to log to file
function log_to_file($message)
{
    file_put_contents('verify_log_m5.txt', $message . "\n", FILE_APPEND);
    echo $message . "\n";
}

// Function to handle API requests
function api_request($endpoint, $method = 'GET', $data = null, $token = null)
{
    $url = "http://localhost:8000/api/v1/" . $endpoint;
    if ($endpoint === 'auth/login')
        $url = "http://localhost:8000/api/auth/login";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

    $headers = [
        'Content-Type: application/json',
        'Accept: application/json'
    ];
    if ($token)
        $headers[] = 'Authorization: Bearer ' . $token;

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    if ($data)
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['code' => $httpCode, 'data' => json_decode($response, true)];
}

file_put_contents('verify_log_m5.txt', ""); // Clear log
log_to_file("--- Module 1: Auth Verification ---");
$login = api_request('auth/login', 'POST', [
    'email' => 'admin@sistema-sunat.com',
    'password' => 'Admin123!@#'
]);
$token = $login['data']['access_token'];

log_to_file("--- Module 5: Operations Verification ---");

// Test Vehicle Creation
$vehicleData = [
    'company_id' => 1,
    'name' => 'Furgoneta Móvil 1',
    'type' => 'furgoneta_grande',
    'placa' => 'ABC-123',
    'activo' => true
];
$createVeh = api_request('vehicles', 'POST', $vehicleData, $token);
log_to_file("Create Vehicle Code: " . $createVeh['code']);
if ($createVeh['code'] === 201) {
    $vehId = $createVeh['data']['data']['id'];
    log_to_file("Vehicle created ID: " . $vehId);

    // Update Location (GPS Simulation)
    $updateLoc = api_request("vehicles/$vehId", 'PUT', [
        'current_latitude' => -12.046374,
        'current_longitude' => -77.042793
    ], $token);
    log_to_file("Update Location Code: " . $updateLoc['code']);
}

// Test Cash Movements (Caja)
$cashData = [
    'company_id' => 1,
    'branch_id' => 1,
    'payment_method' => 'Efectivo',
    'amount' => 100.00,
    'description' => 'Ingreso por Cita 1',
    'date' => date('Y-m-d')
];
$createCash = api_request('payments', 'POST', $cashData, $token);
log_to_file("Create Payment Code: " . $createCash['code']);
if ($createCash['code'] !== 201 && $createCash['code'] !== 200) {
    log_to_file("Payment Detail: " . json_encode($createCash['data'], JSON_PRETTY_PRINT));
}
