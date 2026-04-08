<?php

namespace App\Mail;

use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class EnrollmentConfirmedMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @param Collection<Invoice> $invoices */
    public function __construct(
        public readonly Enrollment $enrollment,
        public readonly Collection $invoices,
        public readonly Guardian   $guardian,
    ) {}

    public function envelope(): Envelope
    {
        $school  = $this->enrollment->school->name;
        $student = $this->enrollment->student->full_name;

        return new Envelope(
            to:      $this->guardian->email ?? $this->guardian->user?->email,
            subject: "Inscription confirmée — {$student} — {$school}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.enrollment.confirmed',
            with: [
                'enrollment' => $this->enrollment->load('school', 'student', 'schoolClass.grade', 'academicYear'),
                'invoices'   => $this->invoices,
                'guardian'   => $this->guardian,
            ],
        );
    }

    public function attachments(): array
    {
        return $this->invoices->map(function (Invoice $invoice) {
            $invoice->loadMissing('student', 'school', 'academicYear', 'enrollment.schoolClass.grade', 'paymentAllocations.payment');

            $pdf = Pdf::loadView('pdf.invoice', compact('invoice'))
                ->setPaper('a4', 'portrait');

            $label = $invoice->invoice_type?->value === 'registration'
                ? "inscription-{$invoice->reference}.pdf"
                : "mensualite-{$invoice->installment_number}-{$invoice->reference}.pdf";

            return Attachment::fromData(
                fn () => $pdf->output(),
                $label
            )->withMime('application/pdf');
        })->all();
    }
}
