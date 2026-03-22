<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Compte suspendu — ScolApp</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-base-200 flex items-center justify-center p-4">
    <div class="max-w-md w-full text-center space-y-6">
        <div class="w-20 h-20 rounded-full bg-red-100 flex items-center justify-center mx-auto">
            <svg class="w-10 h-10 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
        </div>
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Compte suspendu</h1>
            <p class="text-gray-600 mt-2">L'accès à votre établissement a été temporairement suspendu.</p>
            @if(session('school')?->suspension_reason)
                <p class="mt-3 text-sm bg-red-50 border border-red-200 rounded-lg p-3 text-red-700">
                    {{ session('school')->suspension_reason }}
                </p>
            @endif
        </div>
        <p class="text-sm text-gray-500">Contactez le support ScolApp pour plus d'informations.</p>
        <a href="{{ route('login') }}" class="btn btn-primary">Retour à la connexion</a>
    </div>
</body>
</html>
