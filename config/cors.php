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

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => [
        'https://postodeenfermagem.com.br',
        'https://www.postodeenfermagem.com.br',
        'https://equipeenfermagem.com.br',
        'https://www.equipeenfermagem.com.br',
        'https://enfaci.com.br',
        'https://www.enfaci.com.br',
        'http://localhost:5173',
        'http://localhost:3000',
        'http://127.0.0.1:5173',
        'http://127.0.0.1:3000',
        
        // =======================================================
        // PARCEIROS WHITELABEL:
        // Adicione novos domínios de parceiros na lista abaixo.
        // Exemplo: 'https://app.novoparceiro.com.br'
        // =======================================================
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Content-Type', 'Authorization', 'X-Tenant-ID', 'X-Requested-With', 'Accept', 'Origin'],

    'exposed_headers' => ['X-Tenant-ID'],

    'max_age' => 86400,

    'supports_credentials' => true,

];
