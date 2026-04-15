<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    private Client $http;

    public function __construct()
    {
        $instanceId = config('services.ultramsg.instance_id');

        $this->http = new Client([
            'base_uri' => "https://api.ultramsg.com/{$instanceId}/",
            'timeout'  => 30,
            'headers'  => ['Content-Type' => 'application/x-www-form-urlencoded'],
        ]);
    }

    /**
     * Send a plain text WhatsApp message.
     */
    public function sendMessage(string $phone, string $message): bool
    {
        return $this->post('messages/chat', [
            'to'   => $this->normalizePhone($phone),
            'body' => $message,
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
        return $this->post('messages/image', [
            'to'      => $this->normalizePhone($phone),
            'image'   => $imageUrl,
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
        return $this->post('messages/document', [
            'to'       => $this->normalizePhone($phone),
            'document' => $documentUrl,
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

    private function post(string $endpoint, array $params): bool
    {
        try {
            $params['token'] = config('services.ultramsg.token');

            $response = $this->http->post($endpoint, ['form_params' => $params]);
            $body     = (string) $response->getBody();
            $decoded  = json_decode($body, true);

            // UltraMsg returns {"sent":"true"} on success, or {"error":"..."} on failure
            $sent = $decoded['sent'] ?? $decoded['status'] ?? null;
            if ($sent !== null && $sent !== 'true' && $sent !== true && $sent !== 1) {
                Log::warning('WhatsAppService: message not sent', [
                    'endpoint' => $endpoint,
                    'phone'    => $params['to'] ?? '?',
                    'response' => $body,
                ]);
                return false;
            }

            Log::info('WhatsAppService: sent', [
                'endpoint' => $endpoint,
                'phone'    => $params['to'] ?? '?',
                'response' => $body,
            ]);
            return true;
        } catch (\Throwable $e) {
            Log::error('WhatsAppService error', [
                'endpoint' => $endpoint,
                'phone'    => $params['to'] ?? '?',
                'error'    => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Ensure phone starts with country code (no + prefix for UltraMsg).
     */
    private function normalizePhone(string $phone): string
    {
        return ltrim(preg_replace('/\s+/', '', $phone), '+');
    }
}
