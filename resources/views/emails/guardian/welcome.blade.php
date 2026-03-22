<x-mail::message>
<div style="text-align:center;margin-bottom:24px;padding-bottom:16px;border-bottom:2px solid #e5e7eb;">
    <img src="{{ $school->logo_url }}" alt="{{ $school->name }}" style="max-height:72px;max-width:220px;object-fit:contain;display:block;margin:0 auto 10px;">
    <p style="margin:0;font-size:20px;font-weight:700;color:#111827;">{{ $school->name }}</p>
</div>

# Espace Parent — {{ $school->name }}

Bonjour **{{ $guardian->full_name }}**,

L'inscription de votre enfant **{{ $student->full_name }}** a été enregistrée avec succès. Un espace parent a été créé pour vous permettre de suivre sa scolarité en temps réel.

---

## 🔑 Vos identifiants de connexion

<x-mail::panel>
**Email :** {{ $guardian->email }}

**Mot de passe temporaire :** `{{ $plainPassword }}`
</x-mail::panel>

> ⚠️ **Important :** Veuillez changer votre mot de passe dès votre première connexion depuis votre profil.

<x-mail::button :url="$loginUrl" color="success">
Se connecter à mon espace parent
</x-mail::button>

---

## Ce que vous pouvez faire depuis votre espace parent

<x-mail::table>
| Fonctionnalité | Description |
|:---------------|:------------|
| 📋 **Présences** | Suivre les absences et retards de votre enfant |
| 📊 **Notes** | Consulter les évaluations et résultats |
| 💰 **Factures** | Voir l'état des paiements et factures |
| 📢 **Annonces** | Lire les communications de l'établissement |
| ✉️ **Messages** | Échanger avec l'administration |
</x-mail::table>

---

## Enfant inscrit

<x-mail::table>
| Champ | Valeur |
|:------|:-------|
| **Nom complet** | {{ $student->full_name }} |
| **Référence** | {{ $student->reference }} |
@if($student->enrollments->first()?->schoolClass)
| **Classe** | {{ $student->enrollments->first()->schoolClass->name }} |
@endif
@if($student->enrollments->first()?->grade)
| **Niveau** | {{ $student->enrollments->first()->grade->name }} |
@endif
</x-mail::table>

---

Pour toute question, contactez l'administration de l'établissement.

Cordialement,<br>
{{ $school->name }}
@if($school->phone)
Tél. : {{ $school->phone }}
@endif
@if($school->email)
Email : {{ $school->email }}
@endif
</x-mail::message>
