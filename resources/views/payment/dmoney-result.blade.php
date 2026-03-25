<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $success ? 'Paiement en cours' : 'Paiement annulé' }} — ScolApp</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <meta http-equiv="refresh" content="5;url={{ $redirect }}">
</head>
<body class="min-h-screen flex items-center justify-center" style="background: #0f172a;">

<div class="w-full max-w-md mx-auto p-6 text-center">

    {{-- Icon --}}
    <div class="w-24 h-24 rounded-full mx-auto mb-6 flex items-center justify-center"
         style="background: {{ $success ? 'rgba(16,185,129,0.15)' : 'rgba(239,68,68,0.12)' }}; border: 2px solid {{ $success ? '#10b981' : '#ef4444' }};">
        @if($success)
        <svg class="w-12 h-12" fill="none" viewBox="0 0 24 24" stroke="#10b981" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0"/>
        </svg>
        @else
        <svg class="w-12 h-12" fill="none" viewBox="0 0 24 24" stroke="#ef4444" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
        </svg>
        @endif
    </div>

    @if($success)
    {{-- Success --}}
    <h1 class="text-2xl font-black text-white mb-3">Paiement en cours de traitement</h1>
    <p class="text-slate-400 mb-2">
        Votre paiement D-Money a bien été soumis.<br>
        La confirmation sera effectuée automatiquement sous quelques instants.
    </p>
    @if($tx)
    <div class="mt-4 p-4 rounded-2xl text-left text-sm"
         style="background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08);">
        <p class="text-slate-400">Référence : <span class="text-white font-mono font-semibold">{{ $tx->order_id }}</span></p>
        <p class="text-slate-400 mt-1">Montant : <span class="text-white font-semibold">{{ number_format($tx->amount, 0, ',', ' ') }} DJF</span></p>
    </div>
    @endif
    <p class="text-slate-600 text-xs mt-6">Redirection automatique dans 5 secondes…</p>

    @else
    {{-- Cancel --}}
    <h1 class="text-2xl font-black text-white mb-3">Paiement annulé</h1>
    <p class="text-slate-400 mb-2">
        Vous avez annulé le paiement D-Money.<br>
        Aucun montant n'a été débité.
    </p>
    <p class="text-slate-600 text-xs mt-6">Redirection automatique dans 5 secondes…</p>
    @endif

    <a href="{{ $redirect }}"
       class="inline-flex items-center gap-2 mt-6 px-6 py-3 rounded-xl font-semibold text-white text-sm transition-all hover:opacity-90"
       style="background: linear-gradient(135deg,#6366f1,#8b5cf6);">
        Retour à mes factures
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
        </svg>
    </a>

</div>
</body>
</html>
