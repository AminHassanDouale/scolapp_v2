<?php

namespace App\Notifications;

use App\Models\Announcement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewAnnouncementNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly Announcement $announcement) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
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
