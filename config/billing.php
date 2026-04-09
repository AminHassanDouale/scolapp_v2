<?php

return [

    /*
    |--------------------------------------------------------------------------
    | D-Money Payment Gateway — api.scolapp.com
    |--------------------------------------------------------------------------
    */

    'api_url'    => env('BILLING_API_URL', 'https://api.scolapp.com'),

    /*
    | API key sent as Bearer token in the Authorization header.
    | Set BILLING_API_KEY in your .env on production.
    */
    'api_key'    => env('BILLING_API_KEY', ''),

    /*
    | URL where D-Money posts payment notifications.
    | D-Money POSTs here; the gateway saves it to MySQL.
    | Laravel then polls GET /payment/notify/{order_id} to read the status.
    */
    'notify_url' => env('BILLING_NOTIFY_URL', 'https://api.scolapp.com/payment/notify'),

    /*
    | Set DMONEY_ENABLED=false in .env to disable D-Money payments gracefully
    | (e.g. while test credentials are being renewed).
    */
    'enabled' => env('DMONEY_ENABLED', true),

    'timeout' => 30,

    'endpoints' => [
        'health'          => '/health',
        'payment_create'  => '/payment/create',
        'payment_query'   => '/payment/query',
        'payment_notify'  => '/payment/notify',
    ],
];
