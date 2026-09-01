<x-mail::message>
@if($school)
<div style="text-align:center;margin-bottom:24px;padding-bottom:16px;border-bottom:2px solid #e5e7eb;">
    <img src="{{ $school->logo_url }}" alt="{{ $school->name }}" style="max-height:72px;max-width:220px;object-fit:contain;display:block;margin:0 auto 10px;">
    <p style="margin:0;font-size:20px;font-weight:700;color:#111827;">{{ $school->name }}</p>
</div>
@endif

# Bienvenue{{ $school ? ' chez ' . $school->name : '' }} !

Bonjour **{{ $user->name }}**,

Votre compte @if($roleLabel)**{{ $roleLabel }}** @endifa été créé avec succès. Vous pouvez dès maintenant accéder à votre espace.

---

## 🔑 Vos identifiants de connexion

<x-mail::panel>
**Adresse de connexion :** {{ $loginUrl }}

**Email :** {{ $user->email }}

**Mot de passe temporaire :** `{{ $plainPassword }}`
</x-mail::panel>

> ⚠️ **Important :** Veuillez changer votre mot de passe dès votre première connexion depuis votre profil.

<x-mail::button :url="$loginUrl" color="primary">
Accéder à mon espace
</x-mail::button>

---

Si vous avez des questions, contactez l'administration de l'établissement.

Cordialement,<br>
{{ $school->name ?? config('app.name') }}
@if($school?->phone)
Tél. : {{ $school->phone }}
@endif
@if($school?->email)
Email : {{ $school->email }}
@endif
</x-mail::message>
