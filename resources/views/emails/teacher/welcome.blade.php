<x-mail::message>
<div style="text-align:center;margin-bottom:24px;padding-bottom:16px;border-bottom:2px solid #e5e7eb;">
    <img src="{{ $school->logo_url }}" alt="{{ $school->name }}" style="max-height:72px;max-width:220px;object-fit:contain;display:block;margin:0 auto 10px;">
    <p style="margin:0;font-size:20px;font-weight:700;color:#111827;">{{ $school->name }}</p>
</div>

# Bienvenue chez {{ $school->name }} !

Bonjour **{{ $teacher->full_name }}**,

Votre compte enseignant a été créé avec succès. Vous pouvez dès maintenant accéder à votre espace personnel.

---

@if($plainPassword)
## 🔑 Vos identifiants de connexion

<x-mail::panel>
**Adresse de connexion :** {{ $loginUrl }}

**Email :** {{ $teacher->email }}

**Mot de passe temporaire :** `{{ $plainPassword }}`
</x-mail::panel>

> ⚠️ **Important :** Veuillez changer votre mot de passe dès votre première connexion depuis votre profil.

<x-mail::button :url="$loginUrl" color="primary">
Accéder à mon espace enseignant
</x-mail::button>

---

@endif

## Vos informations

<x-mail::table>
| Champ | Valeur |
|:------|:-------|
| **Référence** | {{ $teacher->reference ?? '—' }} |
| **Email** | {{ $teacher->email }} |
@if($teacher->phone)
| **Téléphone** | {{ $teacher->phone }} |
@endif
@if($teacher->hire_date)
| **Date d'embauche** | {{ $teacher->hire_date->format('d/m/Y') }} |
@endif
@if($teacher->specialization)
| **Spécialisation** | {{ $teacher->specialization }} |
@endif
</x-mail::table>

@if($teacher->subjects->isNotEmpty())
---

## Matières enseignées

<x-mail::table>
| Matière | Code | Coefficient |
|:--------|:----:|:-----------:|
@foreach($teacher->subjects as $subject)
| {{ $subject->name }} | {{ $subject->code ?? '—' }} | {{ $subject->default_coefficient ?? 1 }} |
@endforeach
</x-mail::table>

@endif

@if($teacher->schoolClasses->isNotEmpty())
---

## Classes assignées

<x-mail::table>
| Classe | Niveau |
|:-------|:-------|
@foreach($teacher->schoolClasses as $class)
| {{ $class->name }} | {{ $class->grade?->name ?? '—' }} |
@endforeach
</x-mail::table>

@endif

---

Si vous avez des questions, contactez l'administration de l'établissement.

Cordialement,<br>
{{ $school->name }}
@if($school->phone)
Tél. : {{ $school->phone }}
@endif
@if($school->email)
Email : {{ $school->email }}
@endif
</x-mail::message>
