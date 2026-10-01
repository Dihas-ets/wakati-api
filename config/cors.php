<?php

$defaultOrigins = (env('APP_ENV', 'production') === 'production')
    ? ['https://wakati-web.vercel.app', 'https://wakatti-web.vercel.app']
    : [
        'http://localhost:3000',
        'http://127.0.0.1:3000',
        'http://localhost:3001',
        'http://127.0.0.1:3001',
        'http://localhost:3002',
        'http://127.0.0.1:3002',
        'http://localhost:5173',
        'http://127.0.0.1:5173',
        'http://localhost',
        'capacitor://localhost',
    ];

$envOrigins = env('CORS_ALLOWED_ORIGINS', env('FRONTEND_URL', ''));
$configuredOrigins = array_filter(array_map('trim', explode(',', (string) $envOrigins)));

$allowedOrigins = ! empty($configuredOrigins) ? $configuredOrigins : $defaultOrigins;

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Détermine les origines, méthodes et en-têtes autorisés pour sécuriser
    | les échanges entre le frontend Next.js et l'API Laravel Wakati.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_values(array_unique($allowedOrigins)),

    'allowed_origins_patterns' => [
        '#^http://localhost(:\d+)?$#',
        '#^http://127\.0\.0\.1(:\d+)?$#',
    ],

    'allowed_headers' => ['Content-Type', 'X-Requested-With', 'Authorization', 'Accept', 'X-Sync-Secret', 'Origin'],

    'exposed_headers' => ['Content-Disposition'],

    'max_age' => 86400,

    'supports_credentials' => true,

];
