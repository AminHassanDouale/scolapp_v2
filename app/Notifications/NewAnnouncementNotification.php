<?php

namespace App\Notifications;

use App\Models\Announcement;
use App\Channels\WhatsAppChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewAnnouncementNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly Announcement $announcement) {}

    public function via(object $notifiable): array
    {
        $channels = ['mail', 'database'];
        $phone = method_exists($notifiable, 'routeNotificationForWhatsApp')
            ? $notifiable->routeNotificationForWhatsApp()
            : ($notifiable->whatsapp_number ?? $notifiable->phone ?? null);
        if (filled($phone)) {
            $channels[] = WhatsAppChannel::class;
        }
        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $school = $this->announcement->school;

        return (new MailMessage)
            ->subject('[' . $this->announcement->level->label() . '] ' . $this->announcement->title)
            ->greeting($school->name)
            ->line($this->announcement->body)
            ->action(__('notifications.announcement.view'), url('/'))
            ->salutation('Cordialement, ' . $school->name);
    }

    public function toWhatsapp(object $notifiable): string
    {
        $school  = $this->announcement->school;
        $excerpt = mb_strlen($this->announcement->body) > 300
            ? mb_substr($this->announcement->body, 0, 297) . '...'
            : $this->announcement->body;

        return implode("\n", [
            '📢 *Annonce : ' . $this->announcement->title . '*',
            '',
            $excerpt,
            '',
            '_' . ($school?->name ?? config('app.name')) . '_',
        ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'            => 'announcement',
            'announcement_id' => $this->announcement->id,
            'title'           => $this->announcement->title,
            'level'           => $this->announcement->level->value,
        ];
    }
}
