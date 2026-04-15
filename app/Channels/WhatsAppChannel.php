<?php

namespace App\Channels;

use App\Services\WhatsAppService;
use Illuminate\Notifications\Notification;

class WhatsAppChannel
{
    public function __construct(private WhatsAppService $whatsapp) {}

    public function send(mixed $notifiable, Notification $notification): void
    {
        // Prefer routeNotificationForWhatsApp() (supports User→Guardian bridge),
        // fall back to direct property access for simpler models.
        $phone = method_exists($notifiable, 'routeNotificationForWhatsApp')
            ? $notifiable->routeNotificationForWhatsApp()
            : ($notifiable->whatsapp_number ?? $notifiable->phone ?? null);

        if (! $phone) {
            return;
        }

        if (method_exists($notification, 'toWhatsappImage')) {
            $img = $notification->toWhatsappImage($notifiable);
            $this->whatsapp->sendImage($phone, $img['url'], $img['caption'] ?? '');
            sleep(1);
        }

        if (method_exists($notification, 'toWhatsapp')) {
            $this->whatsapp->sendMessage($phone, $notification->toWhatsapp($notifiable));
        }

        if (method_exists($notification, 'toWhatsappDocument')) {
            sleep(1);
            try {
                $doc = $notification->toWhatsappDocument($notifiable);
                $this->whatsapp->sendDocument(
                    $phone,
                    $doc['url'],
                    $doc['filename'] ?? 'document.pdf',
                    $doc['caption']  ?? ''
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('WhatsAppChannel: document send failed', [
                    'phone' => $phone,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
