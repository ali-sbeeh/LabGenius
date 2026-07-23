<?php

// config/cors.php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    */

    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
        'oauth/*',
        'login',
        'logout',
        'register',
    ],

    'allowed_methods' => ['*'],  // GET, POST, PUT, DELETE, OPTIONS

    'allowed_origins' => [
        'http://localhost:3000',      // React dev server
        'http://localhost:4200',      // Angular dev server
        'http://localhost:8080',      // Vue dev server
        'http://localhost:8000',      // Laravel dev server
        env('FRONTEND_URL', '*'),     // From .env file
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'X-Requested-With',
        'Content-Type',
        'Accept',
        'Authorization',
        'X-CSRF-TOKEN',
        'X-API-KEY',
        'Origin',
    ],

    'exposed_headers' => [
        'X-Response-Time-MS',
        'X-RateLimit-Limit',
        'X-RateLimit-Remaining',
    ],

    'max_age' => 86400,  // 24 hours (preflight request cache)

    'supports_credentials' => true,  // Allow cookies to be sent

];
