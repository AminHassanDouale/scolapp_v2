<?php

namespace App\Notifications;

use App\Models\Guardian;
use App\Models\Payment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

class PaymentConfirmedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Payment  $payment,
        public readonly Guardian $guardian,
    ) {}

    public function via(object $notifiable): array
    {
        return ['whatsapp'];
    }

    // ── WhatsApp logo image ────────────────────────────────────────────────────

    public function toWhatsappImage(object $notifiable): array
    {
        return [
            'url'     => url('images/logo_ScolApp.png'),
            'caption' => $this->payment->school?->name ?? 'ScolApp',
        ];
    }

    // ── WhatsApp text message ──────────────────────────────────────────────────

    public function toWhatsapp(object $notifiable): string
    {
        $payment  = $this->payment;
        $school   = $payment->school;
        $student  = $payment->student;
        $invoice  = $payment->paymentAllocations->first()?->invoice;
        $amount   = number_format($payment->amount, 0, ',', ' ') . ' DJF';

        $lines = [
            "✅ *Paiement confirmé — " . ($school?->name ?? 'ScolApp') . "*",
            "",
            "Bonjour *{$this->guardian->full_name}*,",
            "",
            "Votre paiement a bien été reçu.",
            "",
            "📋 *Détails :*",
            "• Référence : *{$payment->reference}*",
            "• Montant : *{$amount}*",
            "• Date : *" . $payment->payment_date?->format('d/m/Y') . "*",
        ];

        if ($student) {
            $lines[] = "• Élève : *{$student->full_name}*";
        }

        if ($invoice) {
            $lines[] = "• Facture : *{$invoice->reference}*";
            $remaining = $invoice->fresh()->balance_due ?? 0;
            if ($remaining > 0) {
                $lines[] = "• Reste dû sur la facture : *" . number_format($remaining, 0, ',', ' ') . " DJF*";
            } else {
                $lines[] = "• Solde de la facture : *Entièrement payée ✅*";
            }
        }

        // Remaining to pay for the whole academic year
        $yearBalance = $payment->academicYearBalance();
        if ($yearBalance > 0) {
            $lines[] = "• Reste à payer (année scolaire) : *" . number_format($yearBalance, 0, ',', ' ') . " DJF*";
        } else {
            $lines[] = "• Année scolaire : *Entièrement soldée ✅*";
        }

        $lines[] = "";
        $lines[] = "Merci pour votre confiance. 🙏";

        return implode("\n", $lines);
    }

    // ── WhatsApp receipt PDF ───────────────────────────────────────────────────

    /**
     * Payment receipt attached to the WhatsApp message.
     * Returns [url, filename, caption]; the gateway fetches the public URL.
     */
    public function toWhatsappDocument(object $notifiable): array
    {
        $payment  = $this->payment->loadMissing('school', 'student', 'paymentAllocations.invoice');
        $school   = $payment->school;
        $guardian = $this->guardian;

        $pdf      = Pdf::loadView('exports.payments.receipt-pdf', compact('payment', 'school', 'guardian'))
            ->setPaper('a4', 'portrait');
        $filename = 'recu-' . $payment->reference . '.pdf';
        $tempPath = 'temp-receipts/' . $filename;

        Storage::disk('public')->put($tempPath, $pdf->output());
        $url = Storage::disk('public')->url($tempPath);

        return [
            'url'      => $url,
            'filename' => $filename,
            'caption'  => 'Reçu ' . $payment->reference . ' — ' . ($school?->name ?? config('app.name')),
        ];
    }
}
