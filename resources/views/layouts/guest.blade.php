<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light"
      dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'ScolApp SMS') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700,800&display=swap" rel="stylesheet"/>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen font-sans antialiased bg-base-200 flex">

    {{-- Left panel — branding (hidden on mobile) --}}
    <div class="hidden lg:flex lg:w-1/2 xl:w-3/5 relative overflow-hidden flex-col justify-between p-12"
         style="background: linear-gradient(135deg, #4f46e5 0%, #6366f1 40%, #8b5cf6 70%, #a78bfa 100%)">

        {{-- Decorative blobs --}}
        <div class="absolute top-0 left-0 w-72 h-72 rounded-full opacity-20"
             style="background: white; filter: blur(80px); transform: translate(-30%, -30%)"></div>
        <div class="absolute bottom-0 right-0 w-96 h-96 rounded-full opacity-15"
             style="background: #818cf8; filter: blur(100px); transform: translate(30%, 30%)"></div>
        <div class="absolute top-1/2 left-1/3 w-48 h-48 rounded-full opacity-10"
             style="background: #c4b5fd; filter: blur(60px)"></div>

        {{-- Top: Logo --}}
        <div class="relative z-10">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-white/20 backdrop-blur flex items-center justify-center">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 14l9-5-9-5-9 5 9 5z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/>
                    </svg>
                </div>
                <span class="text-white font-bold text-xl tracking-tight">ScolApp</span>
            </div>
        </div>

        {{-- Center: Hero text --}}
        <div class="relative z-10 space-y-6">
            <div>
                <h1 class="text-4xl xl:text-5xl font-extrabold text-white leading-tight">
                    Gérez votre<br>
                    <span class="text-violet-200">école</span> avec<br>
                    simplicité.
                </h1>
                <p class="mt-4 text-indigo-200 text-base xl:text-lg leading-relaxed max-w-sm">
                    Présences, notes, emplois du temps, paiements —
                    tout en un seul endroit.
                </p>
            </div>

            {{-- Feature pills --}}
            <div class="flex flex-wrap gap-2">
                @foreach(['Élèves & Inscriptions', 'Présences', 'Notes & Bulletins', 'Paiements', 'Emplois du temps'] as $feature)
                <span class="px-3 py-1 rounded-full text-xs font-semibold bg-white/15 text-white backdrop-blur border border-white/20">
                    {{ $feature }}
                </span>
                @endforeach
            </div>
        </div>

        {{-- Bottom: stats row --}}
        <div class="relative z-10 grid grid-cols-3 gap-4">
            @foreach([['📚', '500+', 'Élèves gérés'], ['🏫', '50+', 'Établissements'], ['✅', '99.9%', 'Disponibilité']] as [$icon, $val, $label])
            <div class="bg-white/10 backdrop-blur rounded-2xl p-4 border border-white/15 text-center">
                <p class="text-2xl">{{ $icon }}</p>
                <p class="text-white font-bold text-lg mt-1">{{ $val }}</p>
                <p class="text-indigo-200 text-xs mt-0.5">{{ $label }}</p>
            </div>
            @endforeach
        </div>
    </div>

    {{-- Right panel — form --}}
    <div class="w-full lg:w-1/2 xl:w-2/5 flex flex-col items-center justify-center min-h-screen px-6 py-12 bg-base-100">

        {{-- Mobile logo (shown only on small screens) --}}
        <div class="lg:hidden text-center mb-8">
            <img src="{{ asset('images/logo_ScolApp.png') }}" alt="ScolApp" class="h-14 mx-auto object-contain">
            <p class="text-base-content/50 text-xs uppercase tracking-widest mt-2">School Management</p>
        </div>

        <div class="w-full max-w-sm">
            {{ $slot }}
        </div>

        {{-- Footer --}}
        <p class="mt-8 text-xs text-base-content/30 text-center">
            © {{ date('Y') }} ScolApp · Tous droits réservés
        </p>
    </div>

    @livewireScripts
</body>
</html>
