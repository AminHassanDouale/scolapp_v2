<x-mail::message>
<div style="text-align:center;margin-bottom:24px;padding-bottom:16px;border-bottom:2px solid #e5e7eb;">
    <img src="{{ $school?->logo_url }}" alt="{{ $school?->name }}" style="max-height:72px;max-width:220px;object-fit:contain;display:block;margin:0 auto 10px;">
    <p style="margin:0;font-size:20px;font-weight:700;color:#111827;">{{ $school?->name ?? config('app.name') }}</p>
</div>

# Notification d'absence

Bonjour **{{ $guardian->full_name }}**,

Nous vous informons que votre enfant **{{ $student->full_name }}** a été signalé(e) comme
**{{ $statusLabel }}** lors de la séance du {{ $session->session_date?->format('d/m/Y') }}.

---

## Informations de la séance

<x-mail::table>
| Champ | Détail |
|:------|:-------|
| **Élève** | {{ $student->full_name }} |
| **Classe** | {{ $session->schoolClass?->name ?? '—' }} |
| **Niveau** | {{ $session->schoolClass?->grade?->name ?? '—' }} |
| **Date** | {{ $session->session_date?->format('d/m/Y') ?? '—' }} |
| **Période** | {{ $periodLabel }} |
| **Statut** | {{ $statusLabel }} |
@if($session->subject)
| **Matière** | {{ $session->subject->name }} |
@endif
@if($session->teacher)
| **Enseignant** | {{ $session->teacher->full_name }} |
@endif
@if($session->start_time)
| **Horaire** | {{ substr($session->start_time, 0, 5) }}@if($session->end_time) – {{ substr($session->end_time, 0, 5) }}@endif |
@endif
@if($reason)
| **Motif enregistré** | {{ $reason }} |
@endif
</x-mail::table>

---

@if($status === 'absent' || $status === 'late')
Si cette absence vous semble incorrecte ou si vous souhaitez fournir un justificatif, veuillez contacter l'établissement dès que possible.
@endif

@if($session->notes)
**Note de l'enseignant :** {{ $session->notes }}
@endif

Nous comptons sur votre collaboration pour assurer le suivi de la scolarité de votre enfant.

Cordialement,
**{{ $school?->name ?? config('app.name') }}**
@if($school?->phone)
Tél. : {{ $school->phone }}
@endif
@if($school?->email)
Email : {{ $school->email }}
@endif
@if($school?->address)
Adresse : {{ $school->address }}{{ $school->city ? ', ' . $school->city : '' }}
@endif
</x-mail::message>
