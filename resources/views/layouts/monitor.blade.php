<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light"
      dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'ScolApp') }} — @yield('title', __('navigation.monitor_portal'))</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700&display=swap" rel="stylesheet"/>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <style>
        .portal-gradient { background: linear-gradient(135deg, #d97706 0%, #f59e0b 50%, #fbbf24 100%); }
        .sidebar-monitor  { background: linear-gradient(180deg, #78350f 0%, #92400e 100%); }
    </style>
    @stack('head-styles')
</head>
<body class="min-h-screen font-sans antialiased bg-base-200 overflow-x-hidden">

{{-- ── Navbar (outside x-main so it renders above the drawer) ── --}}
<x-nav sticky full-width class="border-b border-amber-100 bg-white/80 backdrop-blur-md z-30">
    <x-slot:brand>
        <label for="monitor-drawer" class="lg:hidden cursor-pointer mr-2 p-1 rounded-lg hover:bg-amber-50">
            <x-icon name="o-bars-3" class="w-6 h-6 text-amber-600" />
        </label>
        <div class="flex items-center gap-2">
            @if(auth()->user()?->school?->logo)
                <img src="{{ auth()->user()->school->logo_url }}" alt="" class="w-7 h-7 rounded-lg object-cover shrink-0 ring-1 ring-current/20">
            @else
                <div class="w-7 h-7 rounded-lg portal-gradient flex items-center justify-center shrink-0 font-black text-white text-xs">{{ strtoupper(substr(auth()->user()?->school?->name ?? 'S', 0, 1)) }}</div>
            @endif
            <span class="font-black text-sm truncate max-w-36 hidden sm:block">{{ auth()->user()?->school?->name }}</span>
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
            <div class="w-8 h-8 rounded-full bg-amber-100 flex items-center justify-center shrink-0">
                <span class="text-amber-700 font-bold text-sm">{{ substr(auth()->user()?->name ?? 'M', 0, 1) }}</span>
            </div>
            <span class="text-sm font-medium hidden lg:block truncate max-w-28">{{ auth()->user()?->name }}</span>
        </div>
    </x-slot:actions>
</x-nav>

{{-- ── Main layout with sidebar ── --}}
<x-main full-width with-nav>
    <x-slot:sidebar drawer="monitor-drawer" collapsible class="sidebar-monitor text-white">
        <div class="portal-gradient p-5 mb-2">
            <div class="flex items-center gap-3">
                @if(auth()->user()?->school?->logo)
                    <img src="{{ auth()->user()->school->logo_url }}" alt="" class="w-11 h-11 rounded-xl object-cover shrink-0 ring-2 ring-white/30">
                @else
                    <div class="w-11 h-11 rounded-xl bg-white/20 flex items-center justify-center backdrop-blur-sm shrink-0 font-black text-white text-lg">{{ strtoupper(substr(auth()->user()?->school?->name ?? 'S', 0, 2)) }}</div>
                @endif
                <div class="min-w-0">
                    <p class="font-bold text-white text-sm truncate">{{ auth()->user()?->full_name }}</p>
                    <p class="text-amber-200 text-xs">{{ __('navigation.monitor_portal') }}</p>
                </div>
            </div>
            <div class="mt-3 flex items-center gap-2">
                <div class="w-2 h-2 rounded-full bg-green-400 shrink-0"></div>
                <span class="text-xs text-amber-200 truncate">{{ auth()->user()?->school?->name }}</span>
            </div>
        </div>

        <x-menu activate-by-route class="px-2">
            <x-menu-item title="{{ __('navigation.dashboard') }}"  icon="o-squares-2x2"   link="{{ route('monitor.dashboard') }}"  class="text-amber-100 hover:bg-white/10 rounded-lg" />
            <x-menu-item title="{{ __('navigation.attendance') }}" icon="o-calendar-days" link="{{ route('monitor.attendance') }}" class="text-amber-100 hover:bg-white/10 rounded-lg" />
            <x-menu-item title="{{ __('navigation.students') }}"   icon="o-user-group"    link="{{ route('monitor.students') }}"   class="text-amber-100 hover:bg-white/10 rounded-lg" />
            <x-menu-item title="{{ __('navigation.schedule') }}"   icon="o-clock"         link="{{ route('monitor.schedule') }}"   class="text-amber-100 hover:bg-white/10 rounded-lg" />
            <x-menu-separator />
            <x-menu-item title="{{ __('navigation.profile') }}" icon="o-user-circle"                    link="{{ route('profile.show') }}" class="text-amber-100 hover:bg-white/10 rounded-lg" />
            <x-menu-item title="{{ __('navigation.logout') }}"  icon="o-arrow-right-start-on-rectangle" link="{{ route('auth.logout') }}" no-wire-navigate class="text-red-300 hover:bg-red-500/20 rounded-lg" />
        </x-menu>
    </x-slot:sidebar>

    <x-slot:content>{{ $slot }}</x-slot:content>
</x-main>

<x-toast />
@livewireScripts
</body>
</html>
