<?php

namespace App\Notifications;

use App\Models\Invoice;
use App\Channels\WhatsAppChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvoiceOverdueNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly Invoice $invoice) {}

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

    public function toWhatsapp(object $notifiable): string
    {
        $school    = $this->invoice->school;
        $currency  = $school?->currency ?? 'DJF';

        return implode("\n", [
            '⚠️ *Facture en retard*',
            'Élève : ' . $this->invoice->student->full_name,
            'Référence : ' . $this->invoice->reference,
            'Montant dû : ' . number_format($this->invoice->balance_due, 0, ',', ' ') . ' ' . $currency,
            'Échéance : ' . $this->invoice->due_date->format('d/m/Y'),
            '',
            'Merci de régulariser votre situation au plus tôt.',
            '',
            '_' . ($school?->name ?? config('app.name')) . '_',
        ]);
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
