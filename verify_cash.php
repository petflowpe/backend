<?php
// Function to log to file
function log_to_file($message)
{
    file_put_contents('verify_log_cash.txt', $message . "\n", FILE_APPEND);
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

file_put_contents('verify_log_cash.txt', ""); // Clear log
$login = api_request('auth/login', 'POST', [
    'email' => 'admin@sistema-sunat.com',
    'password' => 'Admin123!@#'
]);
$token = $login['data']['access_token'];

log_to_file("--- Cash Movement Verification ---");

// Test Expense Creation
$expenseData = [
    'company_id' => 1,
    'branch_id' => 1,
    'type' => 'EXPENSE',
    'amount' => 50.00,
    'description' => 'Compra de útiles de aseo',
    'payment_method' => 'Efectivo',
];
$createCash = api_request('cash-movements', 'POST', $expenseData, $token);
log_to_file("Create Expense Code: " . $createCash['code']);
if ($createCash['code'] === 201) {
    log_to_file("Expense created successfully");
    log_to_file("Expense ID: " . $createCash['data']['data']['id']);
} else {
    log_to_file("Error detail: " . json_encode($createCash['data'], JSON_PRETTY_PRINT));
}

// List Movements
$list = api_request('cash-movements', 'GET', null, $token);
log_to_file("List Movements Code: " . $list['code']);
if ($list['code'] === 200) {
    log_to_file("Total movements found: " . count($list['data']['data']['data']));
}
