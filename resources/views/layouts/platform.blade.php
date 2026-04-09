<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light"
      dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'ScolApp') }} — Plateforme Admin</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700&display=swap" rel="stylesheet"/>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <style>
        .portal-gradient { background: linear-gradient(135deg, #1e293b 0%, #334155 50%, #475569 100%); }
        .sidebar-platform { background: linear-gradient(180deg, #020617 0%, #0f172a 100%); }
    </style>
    @stack('head-styles')
</head>
<body class="min-h-screen font-sans antialiased bg-base-200 overflow-x-hidden">

{{-- ── Navbar ── --}}
<x-nav sticky full-width class="border-b border-slate-200 bg-white/90 backdrop-blur-md z-30">
    <x-slot:brand>
        <label for="platform-drawer" class="lg:hidden cursor-pointer mr-2 p-1 rounded-lg hover:bg-slate-100">
            <x-icon name="o-bars-3" class="w-6 h-6 text-slate-600" />
        </label>
        <div class="flex items-center gap-2">
            <img src="{{ asset('images/logo_ScolApp.png') }}" alt="ScolApp" class="h-8 object-contain">
            <span class="text-xs font-semibold bg-slate-800 text-white px-2 py-0.5 rounded-full hidden sm:inline">PLATFORM</span>
        </div>
    </x-slot:brand>
    <x-slot:actions>
        <x-dropdown label="{{ strtoupper(app()->getLocale()) }}" icon="o-language" class="btn-ghost btn-sm" right>
            <x-menu-item title="Français" link="{{ route('locale.switch', 'fr') }}" no-wire-navigate />
            <x-menu-item title="English"  link="{{ route('locale.switch', 'en') }}" no-wire-navigate />
            <x-menu-item title="العربية"  link="{{ route('locale.switch', 'ar') }}" no-wire-navigate />
        </x-dropdown>
        <x-theme-toggle class="btn-ghost btn-sm" />
        <div class="flex items-center gap-2 pl-2 border-l border-base-200">
            <div class="w-8 h-8 rounded-full bg-slate-800 flex items-center justify-center shrink-0">
                <span class="text-white font-bold text-sm">{{ substr(auth()->user()?->name ?? 'S', 0, 1) }}</span>
            </div>
            <span class="text-sm font-medium hidden lg:block truncate max-w-28">{{ auth()->user()?->name }}</span>
        </div>
    </x-slot:actions>
</x-nav>

{{-- ── Main ── --}}
<x-main full-width with-nav>
    <x-slot:sidebar drawer="platform-drawer" collapsible class="sidebar-platform text-white">
        <div class="portal-gradient p-5 mb-2">
            <div class="flex items-center gap-3">
                <img src="{{ asset('images/logo_ScolApp.png') }}" alt="ScolApp" class="w-11 h-11 rounded-xl object-contain shrink-0">
                <div class="min-w-0">
                    <p class="font-bold text-white text-sm truncate">{{ auth()->user()?->full_name }}</p>
                    <p class="text-slate-300 text-xs">Super Administrateur</p>
                </div>
            </div>
            <div class="mt-3 flex items-center gap-2">
                <div class="w-2 h-2 rounded-full bg-green-400 shrink-0"></div>
                <span class="text-xs text-slate-300">Plateforme ScolApp</span>
            </div>
        </div>

        <x-menu activate-by-route class="px-2">
            <x-menu-item title="Tableau de bord"  icon="o-squares-2x2"      link="{{ route('platform.dashboard') }}"      class="text-slate-100 hover:bg-white/10 rounded-lg" />
            <x-menu-item title="Écoles"           icon="o-building-library" link="{{ route('platform.schools.index') }}"   class="text-slate-100 hover:bg-white/10 rounded-lg" />
            <x-menu-item title="Utilisateurs"     icon="o-users"            link="{{ route('platform.users.index') }}"     class="text-slate-100 hover:bg-white/10 rounded-lg" />
            <x-menu-item title="Plans"            icon="o-credit-card"      link="{{ route('platform.plans.index') }}"     class="text-slate-100 hover:bg-white/10 rounded-lg" />
            <x-menu-separator />
            <x-menu-item title="Paramètres"       icon="o-cog-6-tooth"      link="{{ route('platform.settings') }}"        class="text-slate-100 hover:bg-white/10 rounded-lg" />
            <x-menu-item title="Mon profil"       icon="o-user-circle"      link="{{ route('profile.show') }}"             class="text-slate-100 hover:bg-white/10 rounded-lg" />
            <x-menu-item title="Déconnexion"      icon="o-arrow-right-start-on-rectangle" link="{{ route('auth.logout') }}" no-wire-navigate class="text-red-300 hover:bg-red-500/20 rounded-lg" />
        </x-menu>
    </x-slot:sidebar>

    <x-slot:content>{{ $slot }}</x-slot:content>
</x-main>

<x-toast />
@livewireScripts
</body>
</html>
