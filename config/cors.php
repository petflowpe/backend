<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Permite que el frontend (Vercel, localhost) llame al API con Bearer token.
    | Sin "Authorization" en allowed_headers, el navegador no envía el token
    | y las rutas protegidas devuelven 401 Unauthenticated.
    |
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:3000',
        'http://localhost:5173',
        'http://127.0.0.1:3000',
        'http://127.0.0.1:5173',
        'https://frontend-grooming-new.vercel.app',
        // Añade aquí tu dominio de Vercel si usas uno personalizado, ej:
        // 'https://tu-dominio.vercel.app',
    ],

    'allowed_origins_patterns' => [
        // Permitir cualquier subdominio de vercel.app
        '#^https://[a-z0-9-]+\.vercel\.app$#',
    ],

    'allowed_headers' => [
        'Accept',
        'Authorization',
        'Content-Type',
        'X-Requested-With',
        'X-XSRF-TOKEN',
    ],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
