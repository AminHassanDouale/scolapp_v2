<?php

namespace App\Mail;

use App\Models\Guardian;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceGeneratedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Invoice  $invoice,
        public readonly Guardian $guardian,
    ) {}

    public function envelope(): Envelope
    {
        $isRegistration = $this->invoice->invoice_type?->value === 'registration';
        $school  = $this->invoice->school->name;

        $subject = $isRegistration
            ? "Frais d'inscription — {$this->invoice->reference} — {$school}"
            : "Facture de scolarité N°{$this->invoice->installment_number} — {$this->invoice->reference} — {$school}";

        $recipientEmail = $this->guardian->email ?? $this->guardian->user?->email;

        return new Envelope(
            to:      $recipientEmail,
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.invoice.generated',
            with: [
                'invoice'   => $this->invoice->load('student', 'school', 'academicYear', 'enrollment.schoolClass.grade', 'paymentAllocations.payment'),
                'guardian'  => $this->guardian,
            ],
        );
    }

    public function attachments(): array
    {
        $invoice = $this->invoice->load('student', 'school', 'academicYear', 'enrollment.schoolClass.grade', 'paymentAllocations.payment');

        $pdf = Pdf::loadView('pdf.invoice', compact('invoice'))
            ->setPaper('a4', 'portrait');

        return [
            Attachment::fromData(
                fn () => $pdf->output(),
                "facture-{$invoice->reference}.pdf"
            )->withMime('application/pdf'),
        ];
    }
}
