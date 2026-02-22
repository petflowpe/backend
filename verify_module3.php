<?php
// Function to log to file
function log_to_file($message)
{
    file_put_contents('verify_log_m3.txt', $message . "\n", FILE_APPEND);
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

file_put_contents('verify_log_m3.txt', ""); // Clear log
log_to_file("--- Module 1: Auth Verification ---");
$login = api_request('auth/login', 'POST', [
    'email' => 'admin@sistema-sunat.com',
    'password' => 'Admin123!@#'
]);
$token = $login['data']['access_token'];

log_to_file("--- Module 3: Appointments Verification ---");

// We need a client and a pet. We use existing ones from Module 2 (ID 2 and 1)
$clientId = 2;
$petId = 1;

// Create Appointment
$appointmentData = [
    'client_id' => $clientId,
    'pet_id' => $petId,
    'service_type' => 'Grooming',
    'service_name' => 'Baño y Corte Golden Retriever',
    'service_category' => 'Peluquería',
    'date' => date('Y-m-d', strtotime('+1 day')),
    'time' => '10:00',
    'address' => 'Av. Marina 123, San Miguel',
    'price' => 50.00,
    'notes' => 'Cita de prueba Antigravity'
];

$createApp = api_request('appointments', 'POST', $appointmentData, $token);
log_to_file("Create Appointment Code: " . $createApp['code']);
if ($createApp['code'] === 201 || $createApp['code'] === 200) {
    $appId = $createApp['data']['data']['id'] ?? $createApp['data']['id'] ?? null;
    log_to_file("Appointment created ID: " . $appId);
} else {
    log_to_file("Create Appointment Error: " . json_encode($createApp['data'], JSON_PRETTY_PRINT));
}

// List Appointments
$listApps = api_request('appointments', 'GET', null, $token);
log_to_file("List Appointments Code: " . $listApps['code']);
log_to_file("Total Appointments: " . count($listApps['data']['data'] ?? []));
