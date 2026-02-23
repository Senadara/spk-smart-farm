<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Backend API Configuration
    |--------------------------------------------------------------------------
    |
    | Konfigurasi koneksi ke Backend API (Node.js smart-farming-api).
    | Komunikasi internal via Docker network: http://node-api:4000/api
    |
    */

    'base_url' => env('API_BASE_URL', 'http://node-api:4000/api'),

    'timeout' => env('API_TIMEOUT', 30),
];
