<?php
// Function to log to file
function log_to_file($message)
{
    file_put_contents('verify_log.txt', $message . "\n", FILE_APPEND);
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

file_put_contents('verify_log.txt', ""); // Clear log
log_to_file("--- Module 1: Auth Verification ---");
$login = api_request('auth/login', 'POST', [
    'email' => 'admin@sistema-sunat.com',
    'password' => 'Admin123!@#'
]);

if ($login['code'] !== 200) {
    log_to_file("Auth failed: " . print_r($login, true));
    die();
}
$token = $login['data']['access_token'];
log_to_file("Token obtained: " . substr($token, 0, 10) . "...");

log_to_file("--- Module 2: Clients Verification ---");
$clientData = [
    'tipo_documento' => '1',
    'numero_documento' => '99999999',
    'razon_social' => 'Cliente de Prueba Antigravity',
    'direccion' => 'Calle Falsa 123',
    'email' => 'test' . rand(0, 1000) . '@example.com',
    'telefono' => '987654321'
];

$createClient = api_request('clients', 'POST', $clientData, $token);
log_to_file("Create Client Code: " . $createClient['code']);
if ($createClient['code'] === 201 || $createClient['code'] === 200) {
    $clientId = $createClient['data']['data']['id'] ?? $createClient['data']['id'] ?? null;
    log_to_file("Client created ID: " . $clientId);
} else if ($createClient['code'] === 400 && strpos($createClient['data']['message'] ?? '', 'Ya existe') !== false) {
    $listClients = api_request('clients', 'GET', null, $token);
    foreach ($listClients['data']['data'] as $c) {
        if ($c['numero_documento'] === $clientData['numero_documento']) {
            $clientId = $c['id'];
            log_to_file("Using existing client ID: " . $clientId);
            break;
        }
    }
} else {
    log_to_file("Create Client Error: " . json_encode($createClient['data'], JSON_PRETTY_PRINT));
    $clientId = null;
}

// Get Clients
$listClients = api_request('clients', 'GET', null, $token);
log_to_file("List Clients Code: " . $listClients['code']);
log_to_file("Total Clients: " . count($listClients['data']['data'] ?? []));

log_to_file("--- Module 2: Pets Verification ---");
if ($clientId) {
    // Create Pet
    $petData = [
        'client_id' => $clientId,
        'name' => 'Rex Test',
        'species' => 'Perro',
        'breed' => 'Golden Retriever',
        'gender' => 'Macho',
        'birth_date' => '2023-01-01',
        'weight' => 25.5
    ];
    $createPet = api_request('pets', 'POST', $petData, $token);
    log_to_file("Create Pet Code: " . $createPet['code']);
    if ($createPet['code'] === 201) {
        $petId = $createPet['data']['data']['id'];
        log_to_file("Pet created ID: " . $petId);
    } else {
        log_to_file("Create Pet Error: " . json_encode($createPet['data'], JSON_PRETTY_PRINT));
    }
} else {
    log_to_file("Skipping Pet verification as Client wasn't created.");
}
