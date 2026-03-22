<?php

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceGeneratedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Invoice $invoice) {}

    public function envelope(): Envelope
    {
        $isRegistration = $this->invoice->invoice_type?->value === 'registration';

        $school  = $this->invoice->school->name;
        $subject = $isRegistration
            ? "Frais d'inscription — {$this->invoice->reference} — {$school}"
            : "Facture de scolarité — {$this->invoice->reference} — {$school}";

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.invoice.generated',
            with: [
                'invoice' => $this->invoice->load('student', 'school', 'academicYear'),
            ],
        );
    }
}
