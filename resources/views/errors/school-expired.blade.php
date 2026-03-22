<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Abonnement expiré — ScolApp</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-base-200 flex items-center justify-center p-4">
    <div class="max-w-md w-full text-center space-y-6">
        <div class="w-20 h-20 rounded-full bg-amber-100 flex items-center justify-center mx-auto">
            <svg class="w-10 h-10 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Abonnement expiré</h1>
            <p class="text-gray-600 mt-2">Votre abonnement ScolApp a expiré. Veuillez renouveler pour continuer à accéder à la plateforme.</p>
        </div>
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-sm text-amber-800">
            Contactez votre administrateur ou le support ScolApp pour renouveler votre abonnement.
        </div>
        <a href="{{ route('login') }}" class="btn btn-ghost btn-sm">Retour à la connexion</a>
    </div>
</body>
</html>
