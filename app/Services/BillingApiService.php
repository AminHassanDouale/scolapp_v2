<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BillingApiService
{
    private Client $http;
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('billing.api_url'), '/');

        $this->http = new Client([
            'base_uri' => $this->baseUrl,
            'timeout'  => config('billing.timeout', 30),
            'headers'  => [
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'verify' => true,
        ]);
    }

    // ── Authentication ─────────────────────────────────────────────────────────

    /**
     * Authenticate with system credentials.
     * Token is cached for 50 minutes (billing JWT usually expires in 60 min).
     */
    public function token(): string
    {
        return Cache::remember('billing_api_token', 3000, function () {
            $response = $this->http->post(config('billing.endpoints.login'), [
                'form_params' => [
                    'username' => config('billing.api_email'),
                    'password' => config('billing.api_password'),
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (empty($data['access_token'])) {
                throw new \RuntimeException('Billing API login failed: no access_token returned.');
            }

            return $data['access_token'];
        });
    }

    /** Force re-authentication (clears token cache). */
    public function forgetToken(): void
    {
        Cache::forget('billing_api_token');
    }

    // ── Plans ──────────────────────────────────────────────────────────────────

    public function getPlans(): array
    {
        $response = $this->http->get(config('billing.endpoints.plans'), [
            'query' => ['is_active' => 'true'],
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    // ── Invoice payment flow ───────────────────────────────────────────────────

    /**
     * Full payment session for a school invoice.
     *
     * 1. Creates a subscription in the billing system (maps to an invoice payment plan).
     * 2. Creates a D-Money payment linked to that subscription.
     *
     * Returns: ['checkout_url' => string, 'order_id' => string, 'subscription_id' => int]
     */
    public function createInvoicePayment(int $amountDjf, string $successUrl, string $cancelUrl): array
    {
        $token   = $this->token();
        $planId  = (int) config('billing.dmoney_plan_id');

        // Step 1 — create subscription
        $subResp = $this->http->post(config('billing.endpoints.subs'), [
            'headers' => $this->authHeader($token),
            'json'    => ['plan_id' => $planId],
        ]);
        $sub = json_decode($subResp->getBody()->getContents(), true);
        $subscriptionId = $sub['id'] ?? null;

        if (! $subscriptionId) {
            throw new \RuntimeException('Billing API: subscription creation failed — no ID returned.');
        }

        // Step 2 — create payment
        $payResp = $this->http->post(config('billing.endpoints.pay'), [
            'headers' => $this->authHeader($token),
            'json'    => [
                'subscription_id' => $subscriptionId,
                'amount'          => $amountDjf,
                'currency'        => 'DJF',
                'success_url'     => $successUrl,
                'cancel_url'      => $cancelUrl,
            ],
        ]);
        $payment = json_decode($payResp->getBody()->getContents(), true);

        if (empty($payment['checkout_url']) || empty($payment['order_id'])) {
            throw new \RuntimeException('Billing API: payment creation failed — missing checkout_url or order_id.');
        }

        return [
            'checkout_url'    => $payment['checkout_url'],
            'order_id'        => $payment['order_id'],
            'subscription_id' => $subscriptionId,
        ];
    }

    /**
     * Verify the status of a payment by order_id.
     * Returns: ['status' => 'completed|pending|failed', ...]
     */
    public function verifyPayment(string $orderId): array
    {
        $response = $this->http->get(config('billing.endpoints.verify') . '/' . $orderId, [
            'headers' => $this->authHeader($this->token()),
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    // ── Health ─────────────────────────────────────────────────────────────────

    public function isReachable(): bool
    {
        try {
            $this->http->get(config('billing.endpoints.health'), ['timeout' => 5]);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function testAuth(): bool
    {
        try {
            $this->forgetToken();
            $this->token();
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    // ── Webhook signature ──────────────────────────────────────────────────────

    public static function verifyWebhookSignature(string $payload, string $signature): bool
    {
        $secret   = config('billing.webhook_secret');
        $expected = hash_hmac('sha256', $payload, $secret);
        return hash_equals($expected, $signature);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function authHeader(string $token): array
    {
        return ['Authorization' => 'Bearer ' . $token];
    }
}
