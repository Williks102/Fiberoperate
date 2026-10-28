<?php

return [
    'paths' => ['api/*'],
    'allowed_methods' => ['*'],
    // Jetons Bearer : pas de cookies, origine limitée au front.
    'allowed_origins' => array_filter(array_map('trim', explode(',', (string) env('FRONTEND_URL', 'http://localhost:3000')))),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];
