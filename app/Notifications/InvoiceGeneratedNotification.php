<?php

namespace App\Notifications;

use App\Channels\WhatsAppChannel;
use App\Models\Guardian;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class InvoiceGeneratedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Invoice  $invoice,
        public readonly Guardian $guardian,
    ) {}

    public function via(object $notifiable): array
    {
        return [WhatsAppChannel::class];
    }

    /**
     * WhatsApp text message summarising the invoice.
     */
    public function toWhatsapp(object $notifiable): string
    {
        $invoice  = $this->invoice->loadMissing('student', 'school');
        $school   = $invoice->school;
        $currency = $school?->currency ?? 'DJF';

        $isRegistration = $invoice->invoice_type?->value === 'registration';

        $typeLabel = $isRegistration
            ? "Frais d'inscription"
            : "Scolarité — versement {$invoice->installment_number}";

        return implode("\n", [
            '📄 *Facture générée*',
            'Élève : ' . $invoice->student->full_name,
            'Réf. : ' . $invoice->reference,
            'Type : ' . $typeLabel,
            'Montant : ' . number_format($invoice->total, 0, ',', ' ') . ' ' . $currency,
            'Échéance : ' . ($invoice->due_date?->format('d/m/Y') ?? '—'),
            '',
            'Veuillez trouver la facture en pièce jointe.',
            '',
            '_' . ($school?->name ?? config('app.name')) . '_',
        ]);
    }

    /**
     * WhatsApp document attachment — returns [documentUrl, filename, caption].
     * UltraMsg accepts a base64 data URI for the document field.
     */
    public function toWhatsappDocument(object $notifiable): array
    {
        $invoice = $this->invoice->load('student', 'school', 'academicYear', 'enrollment.schoolClass.grade', 'paymentAllocations.payment');

        $pdf = Pdf::loadView('pdf.invoice', compact('invoice'))
            ->setPaper('a4', 'portrait');

        $base64 = 'data:application/pdf;base64,' . base64_encode($pdf->output());
        $filename = 'facture-' . $invoice->reference . '.pdf';
        $caption  = 'Facture ' . $invoice->reference . ' — ' . ($invoice->school?->name ?? '');

        return [
            'url'      => $base64,
            'filename' => $filename,
            'caption'  => $caption,
        ];
    }
}
