<x-mail::message>
@php
    $school       = $enrollment->school;
    $student      = $enrollment->student;
    $registration = $invoices->first(fn($i) => $i->invoice_type?->value === 'registration');
    $tuitions     = $invoices->filter(fn($i) => $i->invoice_type?->value === 'tuition')->sortBy('installment_number');
    $totalAmount  = $invoices->sum('total');
@endphp

<div style="text-align:center;margin-bottom:24px;padding-bottom:16px;border-bottom:2px solid #e5e7eb;">
    @if($school->logo_url)
    <img src="{{ $school->logo_url }}" alt="{{ $school->name }}" style="max-height:72px;max-width:220px;object-fit:contain;display:block;margin:0 auto 10px;">
    @endif
    <p style="margin:0;font-size:20px;font-weight:700;color:#111827;">{{ $school->name }}</p>
</div>

# Inscription confirmée

Bonjour {{ $guardian->name ?? 'Parent/Tuteur' }},

L'inscription de **{{ $student->full_name }}** à **{{ $school->name }}** pour l'année scolaire **{{ $enrollment->academicYear?->name }}** a été **confirmée**.

@if($enrollment->schoolClass)
> 🏫 Classe affectée : **{{ $enrollment->schoolClass->name }}**@if($enrollment->schoolClass->grade) — {{ $enrollment->schoolClass->grade->name }}@endif
@endif

---

## Récapitulatif des factures

Vous trouverez en pièce(s) jointe(s) **{{ $invoices->count() }} facture(s)** au format PDF.

<x-mail::table>
| # | Type | Référence | Échéance | Montant |
|:--|:-----|:----------|:---------|--------:|
@if($registration)
| — | Frais d'inscription | {{ $registration->reference }} | {{ $registration->due_date?->format('d/m/Y') }} | **{{ number_format($registration->total, 0, ',', ' ') }} DJF** |
@endif
@foreach($tuitions as $inv)
| {{ $inv->installment_number }} | Mensualité | {{ $inv->reference }} | {{ $inv->due_date?->format('d/m/Y') }} | **{{ number_format($inv->total, 0, ',', ' ') }} DJF** |
@endforeach
</x-mail::table>

**Total annuel : {{ number_format($totalAmount, 0, ',', ' ') }} DJF**

---

<x-mail::button :url="url('/guardian/factures')" color="primary">
Consulter mes factures en ligne
</x-mail::button>

> 💳 Vous pouvez payer chaque mensualité directement via **D-Money** depuis votre espace parent.

> 📅 Des rappels automatiques vous seront envoyés avant chaque échéance.

---

Cordialement,<br>
{{ $school->name }}
@if($school->phone)
Tél. : {{ $school->phone }}
@endif
@if($school->email)
Email : {{ $school->email }}
@endif
</x-mail::message>
