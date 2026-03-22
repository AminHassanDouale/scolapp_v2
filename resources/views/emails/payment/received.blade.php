<x-mail::message>
<div style="text-align:center;margin-bottom:24px;padding-bottom:16px;border-bottom:2px solid #e5e7eb;">
    <img src="{{ $payment->school?->logo_url }}" alt="{{ $payment->school?->name }}" style="max-height:72px;max-width:220px;object-fit:contain;display:block;margin:0 auto 10px;">
    <p style="margin:0;font-size:20px;font-weight:700;color:#111827;">{{ $payment->school?->name ?? config('app.name') }}</p>
</div>

# Reçu de paiement

Bonjour **{{ $guardian->full_name }}**,

Nous vous confirmons la réception du paiement suivant pour votre enfant **{{ $payment->student?->full_name }}**.

---

## Détails du paiement

<x-mail::table>
| Champ | Valeur |
|:------|:-------|
| **Référence** | {{ $payment->reference }} |
| **Date** | {{ $payment->payment_date?->format('d/m/Y') }} |
| **Montant réglé** | {{ number_format((int) $payment->amount, 0, ',', ' ') }} DJF |
| **Méthode** | {{ match($payment->payment_method) { 'cash' => 'Espèces', 'bank_transfer' => 'Virement bancaire', 'check' => 'Chèque', 'mobile_money' => 'Mobile Money', default => ucfirst($payment->payment_method) } }} |
@if($payment->transaction_ref)
| **Référence transaction** | {{ $payment->transaction_ref }} |
@endif
@if($payment->bank_name)
| **Banque** | {{ $payment->bank_name }} |
@endif
@if($payment->meta && isset($payment->meta['provider']))
| **Opérateur** | {{ match($payment->meta['provider']) { 'd_money' => 'D-Money', 'waafi' => 'Waafi', 'cac_pay' => 'CaC Pay', 'exim_bank' => 'Exim Bank Mobile', 'saba_bank' => 'Saba Bank', default => $payment->meta['provider'] } }} |
| **Téléphone** | {{ $payment->meta['phone'] ?? '—' }} |
@endif
| **Statut** | {{ $payment->status->label() }} |
</x-mail::table>

---

## Factures réglées

<x-mail::table>
| Facture | Classe | Montant alloué |
|:--------|:-------|---------------:|
@foreach($payment->invoices as $invoice)
| {{ $invoice->reference }} | {{ $invoice->enrollment?->schoolClass?->name ?? '—' }} | {{ number_format((int) $invoice->pivot->amount, 0, ',', ' ') }} DJF |
@endforeach
</x-mail::table>

@if($payment->notes)
> **Note :** {{ $payment->notes }}
@endif

---

Ce reçu est généré automatiquement par **{{ $payment->school?->name ?? config('app.name') }}**. Veuillez le conserver pour vos archives.

Cordialement,<br>
{{ $payment->school?->name ?? config('app.name') }}

<x-mail::subcopy>
Ce message a été envoyé automatiquement — merci de ne pas y répondre directement.
Propulsé par **ScolApp SMS**.
</x-mail::subcopy>
</x-mail::message>
