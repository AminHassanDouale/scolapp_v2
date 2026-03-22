<x-mail::message>
<div style="text-align:center;margin-bottom:24px;padding-bottom:16px;border-bottom:2px solid #e5e7eb;">
    <img src="{{ $invoice->school->logo_url }}" alt="{{ $invoice->school->name }}" style="max-height:72px;max-width:220px;object-fit:contain;display:block;margin:0 auto 10px;">
    <p style="margin:0;font-size:20px;font-weight:700;color:#111827;">{{ $invoice->school->name }}</p>
</div>

@php
    $isOverdue = $stage === 'overdue';
    $daysLabel = match($stage) {
        '7day'    => 'dans 7 jours',
        '14day'   => 'dans 14 jours',
        'overdue' => 'dépassée',
        default   => $stage,
    };
@endphp

# {{ $isOverdue ? '⚠️ Facture en retard de paiement' : '📅 Rappel de paiement à venir' }}

Bonjour,

@if($isOverdue)
La facture **{{ $invoice->reference }}** de **{{ $invoice->student->full_name }}** est **en retard**. La date d'échéance ({{ $invoice->due_date?->format('d/m/Y') }}) est dépassée.

> ⚠️ **Veuillez régulariser votre situation dès que possible** pour éviter tout blocage de l'accès aux services scolaires.
@else
Nous vous rappelons que la facture **{{ $invoice->reference }}** de **{{ $invoice->student->full_name }}** arrive à échéance **{{ $daysLabel }}** ({{ $invoice->due_date?->format('d/m/Y') }}).
@endif

---

<x-mail::table>
| | |
|:--|--:|
| **Référence** | {{ $invoice->reference }} |
| **Élève** | {{ $invoice->student->full_name }} |
| **Échéance** | {{ $invoice->due_date?->format('d/m/Y') }} |
| **Montant restant** | **{{ number_format($invoice->balance_due, 0, ',', ' ') }} {{ $invoice->school->currency ?? 'DJF' }}** |
</x-mail::table>

<x-mail::button :url="url('/guardian')" color="{{ $isOverdue ? 'error' : 'primary' }}">
Accéder à mon espace parent
</x-mail::button>

---

Pour toute question, contactez l'administration de **{{ $invoice->school->name }}**.

Cordialement,<br>
{{ $invoice->school->name }}
@if($invoice->school->phone)
Tél. : {{ $invoice->school->phone }}
@endif
@if($invoice->school->email)
Email : {{ $invoice->school->email }}
@endif

<x-mail::subcopy>
Ce message est généré automatiquement — merci de ne pas y répondre directement.
Propulsé par **ScolApp SMS**.
</x-mail::subcopy>
</x-mail::message>
