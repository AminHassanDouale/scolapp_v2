<?php

namespace App\Notifications;

use App\Models\Guardian;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

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
                $lines[] = "• Reste dû : *" . number_format($remaining, 0, ',', ' ') . " DJF*";
            } else {
                $lines[] = "• Solde : *Entièrement payé ✅*";
            }
        }

        $lines[] = "";
        $lines[] = "Merci pour votre confiance. 🙏";

        return implode("\n", $lines);
    }
}
