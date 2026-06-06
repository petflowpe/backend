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
        'http://localhost:8000',
        'http://127.0.0.1:3000',
        'http://127.0.0.1:5173',
        'http://127.0.0.1:8000',
        'https://frontend-grooming-new.vercel.app',
        // Producción PetFlow
        'https://petflow.com',
        'https://www.petflow.com',
        'https://api.petflow.com',
        'https://srv1197160.hstgr.cloud',
        'http://srv1197160.hstgr.cloud',
        // Añade aquí tu dominio de Vercel si usas uno personalizado, ej:
        // 'https://tu-dominio.vercel.app',
    ],

    'allowed_origins_patterns' => [
        // Permitir cualquier subdominio de vercel.app
        '#^https://[a-z0-9-]+\.vercel\.app$#',
        // Desarrollo local: permitir cualquier puerto en localhost/127.0.0.1 (Vite puede usar 3000, 3003, 5173, etc.)
        '#^http://localhost:\d+$#',
        '#^http://127\.0\.0\.1:\d+$#',
    ],

    'allowed_headers' => [
        'Accept',
        'Authorization',
        'Content-Type',
        'X-Requested-With',
        'X-XSRF-TOKEN',
        // Multi-tenant headers (super_admin / multi-empresa)
        'X-Company-Id',
        'X-Branch-Id',
    ],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
