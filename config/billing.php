<?php

return [

    /*
    |--------------------------------------------------------------------------
    | D-Money Payment Gateway — api.scolapp.com
    |--------------------------------------------------------------------------
    | No authentication required — just HTTPS POST/GET.
    */

    'api_url'    => env('BILLING_API_URL', 'https://api.scolapp.com'),

    /*
    | URL where D-Money posts payment notifications (must be public HTTPS).
    | Set in .env for production: BILLING_NOTIFY_URL=https://scolapp.com/webhooks/billing
    */
    'notify_url' => env('BILLING_NOTIFY_URL', 'https://scolapp.com/webhooks/billing'),

    'timeout' => 30,

    'endpoints' => [
        'health'          => '/health',
        'payment_create'  => '/payment/create',
        'payment_query'   => '/payment/query',
        'payment_notify'  => '/payment/notify',
    ],
];
