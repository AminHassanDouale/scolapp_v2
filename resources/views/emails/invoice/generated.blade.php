<x-mail::message>
@php
    $isRegistration = $invoice->invoice_type?->value === 'registration' || $invoice->invoice_type === 'registration';
@endphp
<div style="text-align:center;margin-bottom:24px;padding-bottom:16px;border-bottom:2px solid #e5e7eb;">
    <img src="{{ $invoice->school->logo_url }}" alt="{{ $invoice->school->name }}" style="max-height:72px;max-width:220px;object-fit:contain;display:block;margin:0 auto 10px;">
    <p style="margin:0;font-size:20px;font-weight:700;color:#111827;">{{ $invoice->school->name }}</p>
</div>
@php
    $isFirstTuition = $invoice->invoice_type?->value === 'tuition' && $invoice->installment_number == 1;
@endphp

# {{ $isRegistration ? 'Frais d\'inscription — Paiement immédiat' : 'Échéancier de paiement — ' . $invoice->school->name }}

Bonjour {{ $guardian->name ?? 'Parent/Tuteur' }},

@if($isRegistration)
Un frais d'inscription a été généré pour **{{ $invoice->student->full_name }}** suite à son inscription à **{{ $invoice->school->name }}**.

> ⚠️ **Ce montant est dû immédiatement** à la date d'inscription.
@else
La scolarité de **{{ $invoice->student->full_name }}** à **{{ $invoice->school->name }}** a été enregistrée. Voici le détail du versement {{ $invoice->installment_number ? "n° {$invoice->installment_number}" : '' }}.
@endif

---

<x-mail::table>
| | |
|:--|--:|
| **Référence** | {{ $invoice->reference }} |
| **Élève** | {{ $invoice->student->full_name }} |
| **Type** | {{ $isRegistration ? 'Frais d\'inscription' : 'Frais de scolarité' }} |
@if($invoice->installment_number)
| **Versement** | N° {{ $invoice->installment_number }} |
@endif
| **Date d'émission** | {{ $invoice->issue_date?->format('d/m/Y') }} |
| **Échéance** | {{ $invoice->due_date?->format('d/m/Y') }} |
| **Montant** | **{{ number_format($invoice->total, 0, ',', ' ') }} DJF** |
</x-mail::table>

@if($isRegistration)
<x-mail::button :url="url('/guardian')" color="error">
Accéder à mon espace parent
</x-mail::button>
@else
<x-mail::button :url="url('/guardian')" color="primary">
Consulter mon espace parent
</x-mail::button>
@endif

---

@if(! $isRegistration && $invoice->installment_number == 1)
> 📅 **Note :** Des rappels vous seront envoyés avant chaque échéance de versement.
@endif

Cordialement,<br>
{{ $invoice->school->name }}
@if($invoice->school->phone)
Tél. : {{ $invoice->school->phone }}
@endif
@if($invoice->school->email)
Email : {{ $invoice->school->email }}
@endif
</x-mail::message>
