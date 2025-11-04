<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:3000',
        'http://localhost:5173',
        'http://127.0.0.1:3000',
        'http://127.0.0.1:5173',
    ],

    // Permitir cualquier origen en desarrollo usando patrón (cambiar en producción)
    'allowed_origins_patterns' => [
        '/^http:\/\/192\.168\.\d+\.\d+:\d+$/',  // Permite IPs de red local
        '/^http:\/\/localhost:\d+$/',             // Permite localhost con cualquier puerto
        '/^http:\/\/127\.0\.0\.1:\d+$/',         // Permite 127.0.0.1 con cualquier puerto
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
