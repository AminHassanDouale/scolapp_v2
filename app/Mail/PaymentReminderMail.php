<?php

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Invoice $invoice,
        public readonly string  $stage, // '7day', '14day', 'overdue'
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.payment_reminder.subject', [
                'reference' => $this->invoice->reference,
                'stage'     => $this->stage,
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.payment.reminder',
            with: [
                'invoice' => $this->invoice->load('student', 'school'),
                'stage'   => $this->stage,
            ],
        );
    }
}
