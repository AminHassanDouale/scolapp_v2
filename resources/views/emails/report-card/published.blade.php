<x-mail::message>
<div style="text-align:center;margin-bottom:24px;padding-bottom:16px;border-bottom:2px solid #e5e7eb;">
    <img src="{{ $school?->logo_url }}" alt="{{ $school?->name }}" style="max-height:72px;max-width:220px;object-fit:contain;display:block;margin:0 auto 10px;">
    <p style="margin:0;font-size:20px;font-weight:700;color:#111827;">{{ $school?->name ?? config('app.name') }}</p>
</div>

# Bulletin scolaire disponible

Bonjour **{{ $guardian->full_name }}**,

Le bulletin de **{{ $reportCard->enrollment?->student?->full_name }}** pour la période **{{ $reportCard->period?->label() }}**
({{ $reportCard->enrollment?->academicYear?->name }}) est maintenant disponible.

---

## Résultats

| Informations | |
|---|---|
| Élève | {{ $reportCard->enrollment?->student?->full_name }} |
| Classe | {{ $reportCard->enrollment?->schoolClass?->name }} |
| Période | {{ $reportCard->period?->label() }} |
| Année scolaire | {{ $reportCard->enrollment?->academicYear?->name }} |
| Moyenne générale | **{{ $reportCard->average !== null ? number_format((float)$reportCard->average, 2) . '/20' : '—' }}** |
@if($reportCard->rank)
| Rang | {{ $reportCard->rank }} / {{ $reportCard->class_size }} |
@endif

---

## Notes par matière

@if($reportCard->subjectGrades->count())
| Matière | Coefficient | Moyenne |
|---|---|---|
@foreach($reportCard->subjectGrades->sortBy('subject.name') as $sg)
| {{ $sg->subject?->name }} | ×{{ number_format((float)$sg->coefficient, 1) }} | {{ $sg->average !== null ? number_format((float)$sg->average, 2).'/20' : '—' }} |
@endforeach
@endif

@if($reportCard->general_comment)
---

**Appréciation générale :** *{{ $reportCard->general_comment }}*

@endif

---

*Ce bulletin a été publié par {{ $school?->name ?? 'l\'établissement' }}.*
*Ce message est confidentiel et destiné uniquement au(x) tuteur(s) de l'élève.*

<x-mail::button :url="url('/')">
Accéder à l'espace famille
</x-mail::button>

Cordialement,<br>
{{ $school?->name ?? 'L\'établissement scolaire' }}
</x-mail::message>
