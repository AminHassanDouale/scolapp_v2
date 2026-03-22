<?php

namespace App\Notifications;

use App\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AttendanceAlertNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Student $student,
        public readonly string  $alertType, // 'repeated_absence', 'late_arrival'
        public readonly int     $count,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $school = $this->student->school;

        return (new MailMessage)
            ->subject(__('notifications.attendance_alert.subject', [
                'student' => $this->student->full_name,
            ]))
            ->greeting($school?->name ?? config('app.name'))
            ->line(__("notifications.attendance_alert.{$this->alertType}", [
                'student' => $this->student->full_name,
                'count'   => $this->count,
            ]))
            ->action(__('notifications.attendance_alert.cta'), url('/'))
            ->salutation('Cordialement, ' . ($school?->name ?? config('app.name')));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'       => 'attendance_alert',
            'alert_type' => $this->alertType,
            'student_id' => $this->student->id,
            'student'    => $this->student->full_name,
            'count'      => $this->count,
        ];
    }
}
