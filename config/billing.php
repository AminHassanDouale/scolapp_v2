<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SaaS Billing API — D-Money Integration
    | Base URL: https://api.scolapp.com
    |--------------------------------------------------------------------------
    */

    'api_url'        => env('BILLING_API_URL', 'https://api.scolapp.com'),
    'api_email'      => env('BILLING_API_EMAIL'),
    'api_password'   => env('BILLING_API_PASSWORD'),
    'webhook_secret' => env('BILLING_WEBHOOK_SECRET'),

    /*
    | The billing system plan_id that represents a "school invoice payment".
    | Create this plan once in the billing dashboard, copy its ID here.
    */
    'dmoney_plan_id' => env('BILLING_DMONEY_PLAN_ID', 1),

    /*
    | D-Money redirect URLs after checkout
    */
    'success_url' => env('BILLING_SUCCESS_URL'),   // falls back to route() in controller
    'cancel_url'  => env('BILLING_CANCEL_URL'),

    'timeout' => 30,

    'endpoints' => [
        'login'    => '/api/v1/auth/login',
        'plans'    => '/api/v1/plans',
        'subs'     => '/api/v1/subscriptions',
        'pay'      => '/api/v1/payments/create',
        'verify'   => '/api/v1/payments/verify',
        'health'   => '/health',
    ],
];
