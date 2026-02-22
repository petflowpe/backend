<?php
// Function to log to file
function log_to_file($message)
{
    file_put_contents('verify_log_seg.txt', $message . "\n", FILE_APPEND);
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

file_put_contents('verify_log_seg.txt', ""); // Clear log
$login = api_request('auth/login', 'POST', [
    'email' => 'admin@sistema-sunat.com',
    'password' => 'Admin123!@#'
]);
$token = $login['data']['access_token'];

log_to_file("--- Auto-Segmentation Verification ---");

// Check initial client level
$clientId = 2; // María García
$client = api_request("clients/$clientId", 'GET', null, $token);
log_to_file("Initial Level: " . $client['data']['data']['nivel_fidelizacion']);

// Add 2 more pets (making it at least 3 total, since 1 exists)
for ($i = 0; $i < 2; $i++) {
    $petData = [
        'client_id' => $clientId,
        'name' => 'Pet Test ' . ($i + 2),
        'species' => 'Perro',
        'breed' => 'Tester',
        'gender' => 'Macho',
        'birth_date' => '2023-01-01',
        'weight' => 5.5,
        'activo' => true
    ];
    $createPet = api_request('pets', 'POST', $petData, $token);
    log_to_file("Create Pet " . ($i + 2) . " Code: " . $createPet['code']);
}

// Check final client level
$client = api_request("clients/$clientId", 'GET', null, $token);
log_to_file("Final Level (should be Bronce): " . $client['data']['data']['nivel_fidelizacion']);

// Add 1 more pet (total 4)
$petData = [
    'client_id' => $clientId,
    'name' => 'Pet Test 4',
    'species' => 'Perro',
    'breed' => 'Tester',
    'gender' => 'Macho',
    'birth_date' => '2023-01-01',
    'weight' => 5.5,
    'activo' => true
];
$createPet = api_request('pets', 'POST', $petData, $token);
log_to_file("Create Pet 4 Code: " . $createPet['code']);

// Check final client level
$client = api_request("clients/$clientId", 'GET', null, $token);
log_to_file("Final Level (should be Oro): " . $client['data']['data']['nivel_fidelizacion']);
