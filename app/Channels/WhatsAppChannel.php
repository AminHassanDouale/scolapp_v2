<?php

namespace App\Channels;

use App\Services\WhatsAppService;
use Illuminate\Notifications\Notification;

class WhatsAppChannel
{
    public function __construct(private WhatsAppService $whatsapp) {}

    public function send(mixed $notifiable, Notification $notification): void
    {
        // Use whatsapp_number if set, otherwise fall back to phone
        $phone = $notifiable->whatsapp_number ?? $notifiable->phone ?? null;

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
            $doc = $notification->toWhatsappDocument($notifiable);
            $this->whatsapp->sendDocument(
                $phone,
                $doc['url'],
                $doc['filename'] ?? 'document.pdf',
                $doc['caption']  ?? ''
            );
        }
    }
}
