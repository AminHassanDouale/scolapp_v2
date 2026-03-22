<?php

namespace App\Mail;

use App\Models\Guardian;
use App\Models\Payment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentReceivedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Payment  $payment,
        public readonly Guardian $guardian,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Reçu de paiement — ' . $this->payment->reference,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.payment.received',
            with: [
                'payment'  => $this->payment,
                'guardian' => $this->guardian,
            ],
        );
    }

    public function attachments(): array
    {
        $payment  = $this->payment;
        $guardian = $this->guardian;
        $school   = $payment->school;
        $attachments = [];

        // Attachment 1: Payment receipt PDF
        $receiptOutput = Pdf::loadView('exports.payments.receipt-pdf', compact('payment', 'school', 'guardian'))
            ->setPaper('a4', 'portrait')
            ->output();

        $attachments[] = Attachment::fromData(
            fn () => $receiptOutput,
            'recu-' . $payment->reference . '.pdf'
        )->withMime('application/pdf');

        // Attachment 2: one PDF per invoice settled
        foreach ($payment->paymentAllocations as $alloc) {
            $invoice = $alloc->invoice;
            if (! $invoice) continue;

            $invoiceOutput = Pdf::loadView('exports.invoices.single-pdf', compact('invoice', 'school'))
                ->setPaper('a4', 'portrait')
                ->output();

            $filename = 'facture-' . $invoice->reference . '.pdf';

            $attachments[] = Attachment::fromData(
                fn () => $invoiceOutput,
                $filename
            )->withMime('application/pdf');
        }

        return $attachments;
    }
}
