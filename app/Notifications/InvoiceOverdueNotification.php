<?php

namespace App\Notifications;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvoiceOverdueNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly Invoice $invoice) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $school = $this->invoice->school;

        return (new MailMessage)
            ->subject(__('notifications.invoice_overdue.subject', [
                'reference' => $this->invoice->reference,
            ]))
            ->greeting($school?->name ?? config('app.name'))
            ->line(__('notifications.invoice_overdue.intro', [
                'student'  => $this->invoice->student->full_name,
                'amount'   => number_format($this->invoice->balance_due) . ' ' . ($school?->currency ?? 'DJF'),
                'due_date' => $this->invoice->due_date->format('d/m/Y'),
            ]))
            ->action(__('notifications.invoice_overdue.cta'), url('/'))
            ->line(__('notifications.invoice_overdue.footer'))
            ->salutation('Cordialement, ' . ($school?->name ?? config('app.name')));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'       => 'invoice_overdue',
            'invoice_id' => $this->invoice->id,
            'reference'  => $this->invoice->reference,
            'amount'     => $this->invoice->balance_due,
            'due_date'   => $this->invoice->due_date->toDateString(),
            'student'    => $this->invoice->student->full_name,
        ];
    }
}
