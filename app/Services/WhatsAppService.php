<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp transport backed by the OpenWA gateway (taxiconnect.online).
 *
 * Every messaging endpoint is scoped to a session:
 *   POST {base}/sessions/{sessionId}/messages/send-text|send-image|send-document
 * Auth is the `X-API-Key` header. A recipient is a chatId: `<digits>@c.us`.
 *
 * Public method signatures are kept identical to the previous provider so that
 * WhatsAppChannel and every caller keep working unchanged.
 *
 * @see docs — OpenWA WhatsApp Notification Integration Guide
 */
class WhatsAppService
{
    private Client $http;
    private string $sessionId;

    public function __construct()
    {
        $baseUri = rtrim((string) config('services.openwa.base_url', 'https://taxiconnect.online/api'), '/') . '/';
        $this->sessionId = (string) config('services.openwa.session_id', '');

        $this->http = new Client([
            'base_uri'    => $baseUri,
            'timeout'     => 30,
            'http_errors' => false, // inspect status/body ourselves
            'headers'     => [
                'X-API-Key'    => (string) config('services.openwa.api_key', ''),
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ],
        ]);
    }

    /**
     * Send a plain text WhatsApp message.
     */
    public function sendMessage(string $phone, string $message): bool
    {
        return $this->post('messages/send-text', [
            'chatId' => $this->chatId($phone),
            'text'   => $message,
        ]);
    }

    /**
     * Send an image by public URL.
     */
    public function sendImage(
        string $phone,
        string $imageUrl,
        string $caption = ''
    ): bool {
        return $this->post('messages/send-image', [
            'chatId'  => $this->chatId($phone),
            'url'     => $imageUrl,
            'caption' => $caption,
        ]);
    }

    /**
     * Send a document / PDF by public URL.
     */
    public function sendDocument(
        string $phone,
        string $documentUrl,
        string $filename = 'document.pdf',
        string $caption  = ''
    ): bool {
        return $this->post('messages/send-document', [
            'chatId'   => $this->chatId($phone),
            'url'      => $documentUrl,
            'filename' => $filename,
            'caption'  => $caption,
        ]);
    }

    /**
     * Send a text message followed by a document.
     */
    public function sendMessageWithDocument(
        string $phone,
        string $message,
        string $documentUrl,
        string $filename = 'document.pdf',
        string $caption  = ''
    ): bool {
        $this->sendMessage($phone, $message);
        sleep(1);
        return $this->sendDocument($phone, $documentUrl, $filename, $caption);
    }

    /**
     * Send the same message to multiple numbers.
     *
     * @param  string[] $phones
     * @return array<array{phone: string, success: bool}>
     */
    public function sendBulk(array $phones, string $message): array
    {
        $results = [];
        foreach ($phones as $phone) {
            $results[] = ['phone' => $phone, 'success' => $this->sendMessage($phone, $message)];
            sleep(2);
        }
        return $results;
    }

    // ── Internals ──────────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed> $payload
     */
    private function post(string $endpoint, array $payload): bool
    {
        if (blank($this->sessionId) || blank(config('services.openwa.api_key'))) {
            Log::warning('WhatsAppService: OpenWA not configured (set OPENWA_API_KEY and OPENWA_SESSION_ID).');
            return false;
        }

        try {
            $url      = "sessions/{$this->sessionId}/{$endpoint}";
            $response = $this->http->post($url, ['json' => $payload]);
            $status   = $response->getStatusCode();
            $body     = (string) $response->getBody();

            if ($status >= 200 && $status < 300) {
                Log::info('WhatsAppService: sent', [
                    'endpoint' => $endpoint,
                    'chatId'   => $payload['chatId'] ?? '?',
                    'response' => $body,
                ]);
                return true;
            }

            Log::warning('WhatsAppService: message not sent', [
                'endpoint' => $endpoint,
                'chatId'   => $payload['chatId'] ?? '?',
                'status'   => $status,
                'response' => $body,
            ]);
            return false;
        } catch (\Throwable $e) {
            Log::error('WhatsAppService error', [
                'endpoint' => $endpoint,
                'chatId'   => $payload['chatId'] ?? '?',
                'error'    => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Turn a phone number into an OpenWA chatId: `<digits>@c.us`.
     * Passes through values that are already a chatId (contain `@`).
     */
    private function chatId(string $phone): string
    {
        if (str_contains($phone, '@')) {
            return $phone;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return $digits . '@c.us';
    }
}
