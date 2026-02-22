<?php
// Function to log to file
function log_to_file($message)
{
    file_put_contents('verify_log_m4.txt', $message . "\n", FILE_APPEND);
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

file_put_contents('verify_log_m4.txt', ""); // Clear log
log_to_file("--- Module 1: Auth Verification ---");
$login = api_request('auth/login', 'POST', [
    'email' => 'admin@sistema-sunat.com',
    'password' => 'Admin123!@#'
]);
$token = $login['data']['access_token'];

log_to_file("--- Module 4: SUNAT Verification ---");

$invoiceData = [
    'company_id' => 1,
    'branch_id' => 1,
    'serie' => 'F001',
    'fecha_emision' => date('Y-m-d'),
    'moneda' => 'PEN',
    'forma_pago_tipo' => 'Contado',
    'client' => [
        'tipo_documento' => '6',
        'numero_documento' => '20123456789',
        'razon_social' => 'EMPRESA TEST S.A.C.',
        'direccion' => 'LIMA, PERU'
    ],
    'detalles' => [
        [
            'codigo' => 'SERV001',
            'descripcion' => 'SERVICIO DE PRUEBA',
            'unidad' => 'NIU',
            'cantidad' => 1,
            'mto_valor_unitario' => 100.00,
            'porcentaje_igv' => 18,
            'tip_afe_igv' => '10'
        ]
    ]
];

$createInv = api_request('invoices', 'POST', $invoiceData, $token);
log_to_file("Create Invoice Code: " . $createInv['code']);
if ($createInv['code'] === 201 || $createInv['code'] === 200) {
    $invId = $createInv['data']['data']['id'] ?? $createInv['data']['id'] ?? null;
    log_to_file("Invoice created ID: " . $invId);

    // Test Send to SUNAT
    log_to_file("Sending to SUNAT...");
    $sendSunat = api_request("invoices/$invId/send-sunat", 'POST', null, $token);
    log_to_file("Send SUNAT Code: " . $sendSunat['code']);
    log_to_file("SUNAT Response: " . json_encode($sendSunat['data'], JSON_PRETTY_PRINT));
} else {
    log_to_file("Create Invoice Error: " . json_encode($createInv['data'], JSON_PRETTY_PRINT));
}
