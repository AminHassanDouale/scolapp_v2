<x-mail::message>
<div style="text-align:center;margin-bottom:24px;padding-bottom:16px;border-bottom:2px solid #e5e7eb;">
    <img src="{{ $school?->logo_url }}" alt="{{ $school?->name }}" style="max-height:72px;max-width:220px;object-fit:contain;display:block;margin:0 auto 10px;">
    <p style="margin:0;font-size:20px;font-weight:700;color:#111827;">{{ $school?->name ?? config('app.name') }}</p>
</div>

# Récapitulatif d'appel

Bonjour,

Le relevé de présences pour la séance du **{{ $session->session_date?->format('d/m/Y') }}** a été enregistré. Voici le récapitulatif complet.

---

## Informations de la session

<x-mail::table>
| Champ | Détail |
|:------|:-------|
| **Classe** | {{ $session->schoolClass?->name ?? '—' }} |
| **Niveau** | {{ $session->schoolClass?->grade?->name ?? '—' }} |
| **Date** | {{ $session->session_date?->format('d/m/Y') ?? '—' }} |
| **Période** | {{ $periodLabel }} |
@if($session->subject)
| **Matière** | {{ $session->subject->name }} |
@endif
@if($session->teacher)
| **Enseignant** | {{ $session->teacher->full_name }} |
@endif
@if($session->start_time)
| **Horaire** | {{ substr($session->start_time, 0, 5) }}@if($session->end_time) – {{ substr($session->end_time, 0, 5) }}@endif |
@endif
@if($session->academicYear)
| **Année scolaire** | {{ $session->academicYear->name }} |
@endif
</x-mail::table>

---

## Statistiques de présence

<x-mail::table>
| Statut | Effectif | Taux |
|:-------|:--------:|:----:|
| ✅ Présents | **{{ $present }}** | {{ $rate }}% |
| ❌ Absents | **{{ $absent }}** | {{ $total > 0 ? round($absent / $total * 100) : 0 }}% |
| ⚠️ Retards | **{{ $late }}** | {{ $total > 0 ? round($late / $total * 100) : 0 }}% |
| 📋 Excusés | **{{ $excused }}** | {{ $total > 0 ? round($excused / $total * 100) : 0 }}% |
| **Total élèves** | **{{ $total }}** | 100% |
</x-mail::table>

---

## Liste nominative des élèves

<x-mail::table>
| # | Élève | Statut | Motif / Remarque |
|:-:|:------|:------:|:-----------------|
@foreach($entries->values() as $i => $entry)
| {{ $i + 1 }} | {{ $entry->student?->full_name ?? '—' }} | {{ $entry->status->label() }} | {{ $entry->reason ?: '—' }} |
@endforeach
</x-mail::table>

@if($session->notes)
---

**Notes de session :**
{{ $session->notes }}
@endif

---

@if($present === $total && $total > 0)
**Bonne nouvelle — tous les élèves étaient présents lors de cette séance !**
@elseif($absent > 0 || $late > 0)
Merci de prendre les mesures nécessaires pour les élèves absents ou en retard.
@endif

Cet email est généré automatiquement par le système de gestion scolaire.

Cordialement,
**{{ $school?->name ?? config('app.name') }}**
@if($school?->phone)
Tél. : {{ $school->phone }}
@endif
@if($school?->email)
Email : {{ $school->email }}
@endif
</x-mail::message>
