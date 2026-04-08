<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class BillingApiService
{
    private Client $http;

    public function __construct()
    {
        $headers = [
            'Accept'       => 'application/json',
            'Content-Type' => 'application/json',
        ];

        $apiKey = config('billing.api_key');
        if (filled($apiKey)) {
            $headers['Authorization'] = 'Bearer ' . $apiKey;
        }

        $this->http = new Client([
            'base_uri' => rtrim(config('billing.api_url'), '/'),
            'timeout'  => config('billing.timeout', 30),
            'headers'  => $headers,
            'verify'   => true,
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // SYSTEM
    // ══════════════════════════════════════════════════════════════════════════

    public function healthCheck(): array
    {
        $r = $this->http->get(config('billing.endpoints.health'), ['timeout' => 5]);
        return json_decode($r->getBody()->getContents(), true) ?? [];
    }

    public function isReachable(): bool
    {
        try {
            $this->healthCheck();
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    // PAYMENT
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Create a D-Money payment session.
     *
     * @return array { success, order_id, prepay_id, checkout_url, amount, currency }
     * @throws \RuntimeException
     */
    public function createPayment(
        int    $amount,
        string $title,
        string $orderId,
        string $notifyUrl,
        string $redirectUrl,
        string $language = 'fr'
    ): array {
        $r = $this->http->post(config('billing.endpoints.payment_create'), [
            'json' => [
                'amount'       => $amount,
                'title'        => $title,
                'order_id'     => $orderId,
                'notify_url'   => $notifyUrl,
                'redirect_url' => $redirectUrl,
                'language'     => $language,
                'currency'     => 'DJF',
            ],
        ]);

        $data = json_decode($r->getBody()->getContents(), true) ?? [];

        if (empty($data['checkout_url']) || empty($data['order_id'])) {
            throw new \RuntimeException('Payment creation failed: ' . json_encode($data));
        }

        return $data;
    }

    /**
     * Verify payment status directly from D-Money.
     * Always call this before marking an order as paid.
     *
     * @return array { merch_order_id, trade_no, trade_status, total_amount, ... }
     */
    public function queryPayment(string $orderId): array
    {
        $r = $this->http->post(config('billing.endpoints.payment_query'), [
            'json' => ['merch_order_id' => $orderId],
        ]);
        return json_decode($r->getBody()->getContents(), true) ?? [];
    }

    /**
     * Read the notification log for an order.
     * Use this to poll for payment completion when you don't use a webhook.
     *
     * @return array { order_id, notifications, count, latest_status }
     */
    public function getNotification(string $orderId): array
    {
        $r = $this->http->get(config('billing.endpoints.payment_notify') . '/' . $orderId);
        return json_decode($r->getBody()->getContents(), true) ?? [];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // INVOICE PAYMENT (high-level helper used by guardian portal)
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Create a D-Money payment session for a school invoice.
     *
     * @return array { checkout_url, order_id, prepay_id }
     * @throws \RuntimeException
     */
    public function createInvoicePayment(
        int    $amountDjf,
        string $title,
        string $orderId,
        string $redirectUrl
    ): array {
        $notifyUrl = config('billing.notify_url', 'https://scolapp.com/webhooks/billing');

        return $this->createPayment(
            $amountDjf,
            $title,
            $orderId,
            $notifyUrl,
            $redirectUrl,
            'en'
        );
    }
}
