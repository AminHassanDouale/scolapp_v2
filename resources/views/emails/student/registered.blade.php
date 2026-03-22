<x-mail::message>
<div style="text-align:center;margin-bottom:24px;padding-bottom:16px;border-bottom:2px solid #e5e7eb;">
    <img src="{{ $school->logo_url }}" alt="{{ $school->name }}" style="max-height:72px;max-width:220px;object-fit:contain;display:block;margin:0 auto 10px;">
    <p style="margin:0;font-size:20px;font-weight:700;color:#111827;">{{ $school->name }}</p>
</div>

# Confirmation d'inscription

Bonjour {{ $guardian->full_name }},

Nous avons le plaisir de vous informer que l'élève **{{ $student->full_name }}** a bien été enregistré(e) dans notre établissement **{{ $school->name }}**.

Vous recevrez prochainement une invitation pour accéder à l'espace parent et suivre la scolarité de votre enfant.

{{ $school->name }}
</x-mail::message>
