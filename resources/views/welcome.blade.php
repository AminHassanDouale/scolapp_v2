<!DOCTYPE html>
<html lang="fr" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="ScolApp — La plateforme de gestion scolaire nouvelle génération. Gérez votre école intelligemment.">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>ScolApp — Plateforme de gestion scolaire</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    {{-- Tailwind CSS CDN (v3) — always loaded for this standalone landing page --}}
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'ui-sans-serif', 'system-ui'] },
                    animation: {
                        'gradient-shift': 'gradientShift 12s ease infinite',
                        'float': 'float 8s ease-in-out infinite',
                        'scroll-dot': 'scrollDot 1.5s ease-in-out infinite',
                        'ticker': 'ticker 25s linear infinite',
                    }
                }
            }
        }
    </script>
    {{-- Alpine.js CDN --}}
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        * { font-family: 'Inter', sans-serif; }

        /* ── Animated gradient background ── */
        .hero-bg {
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 40%, #312e81 70%, #0f172a 100%);
            background-size: 400% 400%;
            animation: gradientShift 12s ease infinite;
        }
        @keyframes gradientShift {
            0%   { background-position: 0% 50%; }
            50%  { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        /* ── Floating blobs ── */
        .blob {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.15;
            animation: float 8s ease-in-out infinite;
        }
        .blob-1 { width: 500px; height: 500px; background: #6366f1; top: -150px; right: -100px; animation-delay: 0s; }
        .blob-2 { width: 400px; height: 400px; background: #8b5cf6; bottom: -100px; left: -100px; animation-delay: -3s; }
        .blob-3 { width: 300px; height: 300px; background: #3b82f6; top: 40%; left: 30%; animation-delay: -6s; }
        @keyframes float {
            0%, 100% { transform: translateY(0px) scale(1); }
            50%       { transform: translateY(-30px) scale(1.05); }
        }

        /* ── Gradient text ── */
        .gradient-text {
            background: linear-gradient(135deg, #a5b4fc, #818cf8, #c4b5fd);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* ── Glow button ── */
        .btn-glow {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            box-shadow: 0 0 30px rgba(99, 102, 241, 0.4);
            transition: all 0.3s ease;
        }
        .btn-glow:hover {
            box-shadow: 0 0 50px rgba(99, 102, 241, 0.7);
            transform: translateY(-2px);
        }

        /* ── Glass card ── */
        .glass-card {
            background: rgba(255, 255, 255, 0.04);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        }
        .glass-card:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(99, 102, 241, 0.4);
            transform: translateY(-6px);
            box-shadow: 0 20px 60px rgba(99, 102, 241, 0.15);
        }

        /* ── Scroll reveal ── */
        .reveal {
            opacity: 0;
            transform: translateY(40px);
            transition: all 0.7s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        }
        .reveal.visible {
            opacity: 1;
            transform: translateY(0);
        }
        .reveal-left {
            opacity: 0;
            transform: translateX(-50px);
            transition: all 0.8s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        }
        .reveal-left.visible {
            opacity: 1;
            transform: translateX(0);
        }
        .reveal-right {
            opacity: 0;
            transform: translateX(50px);
            transition: all 0.8s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        }
        .reveal-right.visible {
            opacity: 1;
            transform: translateX(0);
        }

        /* ── Timeline ── */
        .timeline-line {
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            width: 2px;
            background: linear-gradient(to bottom, #6366f1, #8b5cf6, #6366f1);
            top: 0; bottom: 0;
        }
        @media (max-width: 768px) {
            .timeline-line { left: 28px; transform: none; }
        }
        .timeline-dot {
            width: 48px; height: 48px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 0 20px rgba(99, 102, 241, 0.5);
            z-index: 10; position: relative;
            flex-shrink: 0;
        }

        /* ── Stat card pulse ring ── */
        .pulse-ring {
            animation: pulse-ring 2s ease-in-out infinite;
        }
        @keyframes pulse-ring {
            0%   { box-shadow: 0 0 0 0 rgba(99,102,241,0.4); }
            70%  { box-shadow: 0 0 0 15px rgba(99,102,241,0); }
            100% { box-shadow: 0 0 0 0 rgba(99,102,241,0); }
        }

        /* ── Nav scroll style ── */
        .nav-scrolled {
            background: rgba(15, 23, 42, 0.95) !important;
            backdrop-filter: blur(20px);
            box-shadow: 0 1px 40px rgba(0,0,0,0.3);
        }

        /* ── Input focus ── */
        .form-input {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            color: white;
            transition: all 0.3s ease;
        }
        .form-input:focus {
            outline: none;
            border-color: #6366f1;
            background: rgba(99,102,241,0.05);
            box-shadow: 0 0 0 3px rgba(99,102,241,0.15);
        }
        .form-input::placeholder { color: rgba(255,255,255,0.3); }

        /* ── Feature icon ── */
        .feature-icon {
            background: linear-gradient(135deg, rgba(99,102,241,0.15), rgba(139,92,246,0.15));
            border: 1px solid rgba(99,102,241,0.2);
            border-radius: 16px;
            padding: 14px;
            transition: all 0.3s ease;
        }
        .glass-card:hover .feature-icon {
            background: linear-gradient(135deg, rgba(99,102,241,0.3), rgba(139,92,246,0.3));
        }

        /* ── Section divider wave ── */
        .wave-divider { line-height: 0; }

        /* ── Stagger delay ── */
        .delay-100 { transition-delay: 0.1s; }
        .delay-200 { transition-delay: 0.2s; }
        .delay-300 { transition-delay: 0.3s; }
        .delay-400 { transition-delay: 0.4s; }
        .delay-500 { transition-delay: 0.5s; }
        .delay-600 { transition-delay: 0.6s; }

        /* ── Dashboard mockup ── */
        @keyframes dashboardFloat {
            0%, 100% { transform: translateY(0) rotate(-1deg); }
            50%       { transform: translateY(-16px) rotate(1deg); }
        }
        .dashboard-float { animation: dashboardFloat 6s ease-in-out infinite; }

        /* ── Ticker strip ── */
        @keyframes ticker {
            from { transform: translateX(0); }
            to   { transform: translateX(-50%); }
        }
        .ticker-inner { animation: ticker 25s linear infinite; }
        .ticker-inner:hover { animation-play-state: paused; }

        /* ── Formation card ── */
        .formation-card {
            background: linear-gradient(135deg, rgba(30,27,75,0.8), rgba(49,46,129,0.6));
            border: 1px solid rgba(99,102,241,0.2);
            transition: all 0.4s ease;
        }
        .formation-card:hover {
            border-color: rgba(99,102,241,0.6);
            transform: scale(1.02);
            box-shadow: 0 20px 60px rgba(99,102,241,0.2);
        }
    </style>
</head>

<body class="bg-[#0f172a] text-white overflow-x-hidden" x-data="scolaApp()">

{{-- ═══════════════════════════════════════════════ --}}
{{-- NAVBAR                                          --}}
{{-- ═══════════════════════════════════════════════ --}}
<nav id="navbar" class="fixed top-0 left-0 right-0 z-50 transition-all duration-500 py-4"
     :class="{ 'nav-scrolled': scrolled }">
    <div class="max-w-7xl mx-auto px-6 flex items-center justify-between">
        {{-- Logo --}}
        <a href="#" class="flex items-center gap-3 group">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center shadow-lg shadow-indigo-500/30 group-hover:scale-110 transition-transform">
                <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                </svg>
            </div>
            <span class="text-xl font-bold gradient-text">ScolApp</span>
        </a>

        {{-- Desktop Nav --}}
        <div class="hidden md:flex items-center gap-8 text-sm font-medium text-slate-300">
            <a href="#features"   class="transition-colors hover:text-indigo-400">Fonctionnalités</a>
            <a href="#paiement"   class="transition-colors hover:text-indigo-400">Paiement</a>
            <a href="#mobile"     class="transition-colors hover:text-indigo-400">Mobile</a>
            <a href="#tarifs"     class="transition-colors hover:text-indigo-400">Tarifs</a>
            <a href="#formations" class="transition-colors hover:text-indigo-400">Formations</a>
            <a href="#about"      class="transition-colors hover:text-indigo-400">À propos</a>
            <a href="#contact"    class="transition-colors hover:text-indigo-400">Contact</a>
        </div>

        {{-- CTA --}}
        <div class="flex items-center gap-3">
            <a href="{{ route('login') }}"
               class="hidden md:inline-flex items-center gap-2 px-5 py-2.5 text-sm font-semibold text-white btn-glow rounded-xl">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                </svg>
                Se connecter
            </a>
            {{-- Mobile menu button --}}
            <button @click="mobileOpen = !mobileOpen"
                    class="md:hidden p-2 rounded-lg text-slate-400 hover:text-white hover:bg-white/10 transition">
                <svg x-show="!mobileOpen" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
                <svg x-show="mobileOpen" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </div>

    {{-- Mobile menu --}}
    <div x-show="mobileOpen"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 -translate-y-4"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-end="opacity-0 -translate-y-4"
         class="md:hidden mt-3 mx-4 rounded-2xl overflow-hidden"
         style="background: rgba(15,23,42,0.98); backdrop-filter: blur(20px); border: 1px solid rgba(99,102,241,0.2);">
        <div class="flex flex-col p-4 gap-1 text-sm font-medium">
            <a href="#features"   @click="mobileOpen=false" class="px-4 py-3 rounded-xl hover:bg-indigo-500/10 text-slate-300 hover:text-white transition">Fonctionnalités</a>
            <a href="#paiement"   @click="mobileOpen=false" class="px-4 py-3 rounded-xl hover:bg-indigo-500/10 text-slate-300 hover:text-white transition">Paiement en ligne</a>
            <a href="#mobile"     @click="mobileOpen=false" class="px-4 py-3 rounded-xl hover:bg-indigo-500/10 text-slate-300 hover:text-white transition">Application mobile</a>
            <a href="#tarifs"     @click="mobileOpen=false" class="px-4 py-3 rounded-xl hover:bg-indigo-500/10 text-slate-300 hover:text-white transition">Tarifs</a>
            <a href="#formations" @click="mobileOpen=false" class="px-4 py-3 rounded-xl hover:bg-indigo-500/10 text-slate-300 hover:text-white transition">Formations</a>
            <a href="#about"      @click="mobileOpen=false" class="px-4 py-3 rounded-xl hover:bg-indigo-500/10 text-slate-300 hover:text-white transition">À propos</a>
            <a href="#contact"    @click="mobileOpen=false" class="px-4 py-3 rounded-xl hover:bg-indigo-500/10 text-slate-300 hover:text-white transition">Contact</a>
            <a href="{{ route('login') }}" class="mt-2 text-center px-4 py-3 rounded-xl btn-glow font-semibold">Se connecter</a>
        </div>
    </div>
</nav>

{{-- ═══════════════════════════════════════════════ --}}
{{-- HERO                                            --}}
{{-- ═══════════════════════════════════════════════ --}}
<section class="hero-bg relative min-h-screen flex items-center overflow-hidden pt-20">
    {{-- Blobs --}}
    <div class="blob blob-1"></div>
    <div class="blob blob-2"></div>
    <div class="blob blob-3"></div>

    {{-- Grid overlay --}}
    <div class="absolute inset-0 opacity-5"
         style="background-image: linear-gradient(rgba(99,102,241,1) 1px, transparent 1px), linear-gradient(90deg, rgba(99,102,241,1) 1px, transparent 1px); background-size: 60px 60px;"></div>

    <div class="max-w-7xl mx-auto px-6 py-24 relative z-10">
        <div class="grid lg:grid-cols-2 gap-16 items-center">
            {{-- Left content --}}
            <div>
                {{-- Badge --}}
                <div class="reveal inline-flex items-center gap-2 px-4 py-2 rounded-full text-xs font-semibold text-indigo-300 mb-8"
                     style="background: rgba(99,102,241,0.1); border: 1px solid rgba(99,102,241,0.2);">
                    <span class="w-2 h-2 rounded-full bg-indigo-400 animate-pulse"></span>
                    SaaS Éducatif · iOS & Android · Multilingue
                </div>

                {{-- Headline --}}
                <h1 class="reveal delay-100 text-4xl sm:text-5xl lg:text-6xl font-black leading-tight mb-6">
                    La gestion scolaire
                    <span class="gradient-text block">réinventée</span>
                    pour demain.
                </h1>

                <p class="reveal delay-200 text-lg text-slate-400 leading-relaxed mb-10 max-w-lg">
                    ScolApp centralise administration, finance, présences, bulletins et communication
                    dans une plateforme SaaS moderne — accessible sur iOS, Android et web,
                    en français, arabe et anglais, avec paiement en ligne intégré.
                </p>

                {{-- CTAs --}}
                <div class="reveal delay-300 flex flex-wrap gap-4">
                    <a href="#contact"
                       class="inline-flex items-center gap-2 px-7 py-4 rounded-xl font-semibold text-white btn-glow text-base">
                        Demander une démo gratuite
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                        </svg>
                    </a>
                    <a href="#features"
                       class="inline-flex items-center gap-2 px-7 py-4 rounded-xl font-semibold text-slate-300 border border-slate-700 hover:border-indigo-500 hover:text-white transition-all duration-300 text-base">
                        Voir les fonctionnalités
                    </a>
                </div>

                {{-- Trust badges --}}
                <div class="reveal delay-400 flex flex-wrap gap-5 mt-12 text-sm text-slate-500">
                    @foreach(['Déploiement rapide', 'Formation incluse', 'Support 24/7', 'iOS & Android', 'FR · AR · EN', 'Paiement en ligne'] as $badge)
                    <span class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-green-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                        </svg>
                        {{ $badge }}
                    </span>
                    @endforeach
                </div>
            </div>

            {{-- Right — Dashboard mockup --}}
            <div class="reveal-right hidden lg:block">
                <div class="dashboard-float relative">
                    {{-- Main card --}}
                    <div class="rounded-2xl overflow-hidden shadow-2xl shadow-indigo-500/20"
                         style="background: rgba(30,27,75,0.8); border: 1px solid rgba(99,102,241,0.3); backdrop-filter: blur(20px);">
                        {{-- Top bar --}}
                        <div class="flex items-center gap-2 px-5 py-4 border-b border-white/5">
                            <span class="w-3 h-3 rounded-full bg-red-400"></span>
                            <span class="w-3 h-3 rounded-full bg-yellow-400"></span>
                            <span class="w-3 h-3 rounded-full bg-green-400"></span>
                            <span class="ml-4 text-xs text-slate-400 font-mono">scolapp.com / admin / dashboard</span>
                        </div>
                        {{-- Dashboard content --}}
                        <div class="p-6 space-y-4">
                            {{-- Stats row --}}
                            <div class="grid grid-cols-3 gap-3">
                                @foreach([['Élèves','847','↑ 12%','indigo'],['Présences','94%','↑ 3%','green'],['Paiements','98%','↑ 5%','purple']] as $s)
                                <div class="rounded-xl p-4" style="background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.06);">
                                    <p class="text-xs text-slate-400 mb-1">{{ $s[0] }}</p>
                                    <p class="text-2xl font-bold text-white">{{ $s[1] }}</p>
                                    <p class="text-xs text-{{ $s[3] }}-400 font-medium">{{ $s[2] }}</p>
                                </div>
                                @endforeach
                            </div>
                            {{-- Chart bars --}}
                            <div class="rounded-xl p-4" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05);">
                                <p class="text-xs text-slate-400 mb-3">Inscriptions par mois</p>
                                <div class="flex items-end gap-2 h-20">
                                    @foreach([40, 65, 55, 80, 70, 90, 75, 95, 85, 100, 88, 92] as $h)
                                    <div class="flex-1 rounded-t-sm transition-all" style="height: {{ $h }}%; background: linear-gradient(to top, #6366f1, #8b5cf6);"></div>
                                    @endforeach
                                </div>
                            </div>
                            {{-- Recent activity --}}
                            <div class="space-y-2">
                                @foreach([['Nouveau bulletin publié','Terminale A','2m'],['Paiement reçu','Famille Benali','5m'],['Absence signalée','3ème B · 4 élèves','12m']] as $a)
                                <div class="flex items-center gap-3 rounded-lg px-3 py-2.5" style="background: rgba(255,255,255,0.03);">
                                    <div class="w-2 h-2 rounded-full bg-indigo-400 shrink-0"></div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs text-white font-medium truncate">{{ $a[0] }}</p>
                                        <p class="text-xs text-slate-500">{{ $a[1] }}</p>
                                    </div>
                                    <span class="text-xs text-slate-600">{{ $a[2] }}</span>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    {{-- Floating badge top-right --}}
                    <div class="absolute -top-5 -right-5 rounded-2xl px-4 py-3 shadow-xl"
                         style="background: linear-gradient(135deg,#6366f1,#8b5cf6); box-shadow: 0 8px 30px rgba(99,102,241,0.4);">
                        <p class="text-xs text-indigo-200">Offre de lancement</p>
                        <p class="text-lg font-black leading-tight">Démo gratuite</p>
                    </div>
                    {{-- Floating badge bottom-left --}}
                    <div class="absolute -bottom-5 -left-5 rounded-2xl px-4 py-3 shadow-xl"
                         style="background: rgba(15,23,42,0.95); border: 1px solid rgba(99,102,241,0.3);">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-full bg-green-500/20 flex items-center justify-center">
                                <svg class="w-4 h-4 text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-xs text-slate-400">Sans engagement</p>
                                <p class="text-sm font-bold text-white">30 min · gratuit</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Scroll indicator --}}
    <div class="absolute bottom-8 left-1/2 -translate-x-1/2 flex flex-col items-center gap-2 text-slate-500 text-xs">
        <span>Défiler</span>
        <div class="w-6 h-10 rounded-full border-2 border-slate-600 flex items-start justify-center p-1">
            <div class="w-1.5 h-3 bg-indigo-400 rounded-full" style="animation: scrollDot 1.5s ease-in-out infinite;"></div>
        </div>
    </div>
    <style>
        @keyframes scrollDot {
            0%   { transform: translateY(0); opacity: 1; }
            100% { transform: translateY(14px); opacity: 0; }
        }
    </style>
</section>

{{-- ═══════════════════════════════════════════════ --}}
{{-- TICKER STRIP                                    --}}
{{-- ═══════════════════════════════════════════════ --}}
<div class="py-4 overflow-hidden border-y border-indigo-500/10" style="background: rgba(99,102,241,0.04);">
    <div class="ticker-inner flex gap-16 whitespace-nowrap items-center text-sm font-medium text-indigo-400">
        @php
        $items = ['Gestion des notes', 'Bulletins automatisés', 'Emplois du temps', 'Présences en temps réel',
                  'Paiement Waafi & D-Money', 'Application iOS & Android', 'Interface FR · AR · EN',
                  'Facturation intelligente', 'Communication parents', 'Portail élèves', 'Portail enseignants',
                  'Gestion multi-écoles', 'Rapports & Analytics', 'Notifications push', 'Sécurité avancée',
                  'Visa & MasterCard', 'CAC Pay · Exim', 'Zéro papier · 100% cloud'];
        $doubled = array_merge($items, $items);
        @endphp
        @foreach($doubled as $item)
        <span class="flex items-center gap-3">
            <span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span>
            {{ $item }}
        </span>
        @endforeach
    </div>
</div>

{{-- ═══════════════════════════════════════════════ --}}
{{-- STATS                                           --}}
{{-- ═══════════════════════════════════════════════ --}}
<section class="py-24" style="background: #0f172a;">
    <div class="max-w-7xl mx-auto px-6">
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-6">
            @foreach([
                ['80%',      'Temps admin économisé',     'Automatisation complète des tâches répétitives'],
                ['< 5 sem.', 'Délai de déploiement',      'De la signature à la mise en production'],
                ['8 rôles',  'Profils utilisateurs',      'Admin, directeur, enseignant, parent, élève…'],
                ['24/7',     'Support inclus',            'Équipe disponible en FR · AR · EN'],
            ] as $stat)
            <div class="reveal glass-card rounded-2xl p-8 text-center">
                <div class="text-4xl lg:text-5xl font-black gradient-text mb-2">{{ $stat[0] }}</div>
                <div class="text-white font-semibold text-sm mb-1">{{ $stat[1] }}</div>
                <div class="text-slate-500 text-xs leading-snug">{{ $stat[2] }}</div>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════ --}}
{{-- FEATURES                                        --}}
{{-- ═══════════════════════════════════════════════ --}}
<section id="features" class="py-28" style="background: linear-gradient(180deg, #0f172a 0%, #0d1223 100%);">
    <div class="max-w-7xl mx-auto px-6">
        {{-- Header --}}
        <div class="text-center mb-20 reveal">
            <span class="inline-block px-4 py-1.5 rounded-full text-xs font-semibold text-indigo-300 mb-5"
                  style="background: rgba(99,102,241,0.1); border: 1px solid rgba(99,102,241,0.2);">
                Tout-en-un
            </span>
            <h2 class="text-4xl lg:text-5xl font-black mb-5">
                Des outils <span class="gradient-text">puissants</span><br>pour chaque acteur
            </h2>
            <p class="text-slate-400 text-lg max-w-2xl mx-auto">
                De l'administration à la salle de classe, ScolApp couvre l'ensemble des besoins d'un établissement scolaire moderne.
            </p>
        </div>

        {{-- Feature grid --}}
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
            @php
            $features = [
                ['Gestion académique',    'Niveaux, classes, matières, emplois du temps, cycles. Tout structuré et synchronisé.', 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253', 'from-indigo-500 to-blue-500'],
                ['Finance & Facturation', 'Frais scolaires, factures, paiements, suivi des impayés. Comptabilité scolaire simplifiée.', 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z', 'from-emerald-500 to-teal-500'],
                ['Présences & Carnets',   'Saisie rapide des absences par séance. Carnets de correspondance numériques. Alertes automatiques.', 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2', 'from-amber-500 to-orange-500'],
                ['Bulletins scolaires',   'Génération PDF automatique des bulletins. Modèles personnalisables. Historique complet.', 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', 'from-pink-500 to-rose-500'],
                ['Communication',         'Annonces, messagerie interne, notifications push. Lien direct école-parents-élèves.', 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z', 'from-violet-500 to-purple-500'],
                ['Multi-établissements',  'Gérez plusieurs écoles depuis une seule plateforme. Isolation des données. Vue consolidée.', 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4', 'from-cyan-500 to-sky-500'],
                ['Paiement en ligne',     'Waafi, CAC Pay, D-Money, Exim, Visa — les parents règlent les frais scolaires depuis leur téléphone.', 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z', 'from-emerald-400 to-green-600'],
                ['Application mobile',   'Application native iOS & Android. Interface multilingue FR/AR/EN. Accès offline pour les enseignants.', 'M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z', 'from-violet-500 to-fuchsia-600'],
            ];
            @endphp
            @foreach($features as $i => $feat)
            <div class="reveal delay-{{ ($i * 100) + 100 }} glass-card rounded-2xl p-8">
                <div class="feature-icon inline-flex mb-6">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br {{ $feat[3] }} flex items-center justify-center">
                        <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $feat[2] }}"/>
                        </svg>
                    </div>
                </div>
                <h3 class="text-lg font-bold text-white mb-3">{{ $feat[0] }}</h3>
                <p class="text-slate-400 text-sm leading-relaxed">{{ $feat[1] }}</p>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════ --}}
{{-- DIGITALISATION ADVANTAGES                       --}}
{{-- ═══════════════════════════════════════════════ --}}
<section class="py-28 relative overflow-hidden" style="background: linear-gradient(180deg, #0d1223 0%, #0a0f1e 100%);">
    {{-- Decorative grid --}}
    <div class="absolute inset-0" style="opacity: 0.03; background-image: radial-gradient(circle, rgba(99,102,241,0.3) 1px, transparent 1px); background-size: 40px 40px;"></div>

    <div class="max-w-7xl mx-auto px-6 relative z-10">

        {{-- Header --}}
        <div class="grid lg:grid-cols-2 gap-16 items-center mb-20">
            <div class="reveal-left">
                <span class="inline-block px-4 py-1.5 rounded-full text-xs font-semibold text-emerald-300 mb-6"
                      style="background: rgba(16,185,129,0.08); border: 1px solid rgba(16,185,129,0.2);">
                    Zéro papier · 100% numérique
                </span>
                <h2 class="text-4xl lg:text-5xl font-black mb-6 leading-tight">
                    Dites adieu aux<br>
                    <span style="background: linear-gradient(135deg,#34d399,#10b981,#059669); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;">
                        dossiers papier
                    </span>
                </h2>
                <p class="text-slate-400 text-lg leading-relaxed">
                    ScolApp transforme radicalement la gestion de votre établissement.
                    Chaque processus devient instantané, traçable et accessible depuis n'importe où.
                </p>
            </div>
            <div class="reveal-right">
                {{-- Paper vs Digital comparison --}}
                <div class="grid grid-cols-2 gap-4">
                    <div class="rounded-2xl p-5 text-center" style="background: rgba(239,68,68,0.06); border: 1px solid rgba(239,68,68,0.15);">
                        <div class="text-3xl mb-2">📄</div>
                        <p class="text-red-400 font-bold text-sm mb-3">Avant ScolApp</p>
                        <ul class="space-y-2 text-xs text-slate-500 text-left">
                            <li class="flex items-center gap-2"><span class="text-red-500">✕</span> Registres manuscrits</li>
                            <li class="flex items-center gap-2"><span class="text-red-500">✕</span> Bulletins imprimés</li>
                            <li class="flex items-center gap-2"><span class="text-red-500">✕</span> Cahiers d'appel</li>
                            <li class="flex items-center gap-2"><span class="text-red-500">✕</span> Reçus en papier</li>
                            <li class="flex items-center gap-2"><span class="text-red-500">✕</span> Classeurs perdus</li>
                        </ul>
                    </div>
                    <div class="rounded-2xl p-5 text-center" style="background: rgba(16,185,129,0.06); border: 1px solid rgba(16,185,129,0.2);">
                        <div class="text-3xl mb-2">⚡</div>
                        <p class="text-emerald-400 font-bold text-sm mb-3">Avec ScolApp</p>
                        <ul class="space-y-2 text-xs text-slate-300 text-left">
                            <li class="flex items-center gap-2"><span class="text-emerald-400">✓</span> Données centralisées</li>
                            <li class="flex items-center gap-2"><span class="text-emerald-400">✓</span> PDF en 1 clic</li>
                            <li class="flex items-center gap-2"><span class="text-emerald-400">✓</span> Présences digitales</li>
                            <li class="flex items-center gap-2"><span class="text-emerald-400">✓</span> Reçus automatiques</li>
                            <li class="flex items-center gap-2"><span class="text-emerald-400">✓</span> Tout archivé & sécurisé</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        {{-- Advantage cards grid --}}
        @php
        // [title, description, svg-path, gradient-classes, bg-rgba, border-rgba, glow-hex]
        $advantages = [
            [
                'Zéro papier',
                'Réduisez à zéro les impressions. Bulletins, reçus, convocations — tout est généré et distribué numériquement.',
                'M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16',
                'from-emerald-500 to-teal-500',
                'rgba(16,185,129,0.06)',
                'rgba(16,185,129,0.2)',
                '#34d399',
            ],
            [
                'Suivi en temps réel',
                'Présences, paiements, notes, absences — tout est mis à jour instantanément et visible par les bonnes personnes.',
                'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0',
                'from-blue-500 to-indigo-500',
                'rgba(59,130,246,0.06)',
                'rgba(59,130,246,0.2)',
                '#60a5fa',
            ],
            [
                'Accès mobile & cloud',
                'Enseignants, parents et administrateurs accèdent à leurs données depuis n\'importe quel appareil, n\'importe où.',
                'M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z',
                'from-violet-500 to-purple-500',
                'rgba(139,92,246,0.06)',
                'rgba(139,92,246,0.2)',
                '#a78bfa',
            ],
            [
                'Notifications instantanées',
                'Les parents reçoivent une alerte dès qu\'une absence est enregistrée, un bulletin publié ou un paiement dû.',
                'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9',
                'from-amber-500 to-orange-500',
                'rgba(245,158,11,0.06)',
                'rgba(245,158,11,0.2)',
                '#fbbf24',
            ],
            [
                'Historique & traçabilité',
                'Chaque action est enregistrée. Retrouvez n\'importe quelle donnée — bulletin, paiement, absence — en quelques secondes.',
                'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01',
                'from-pink-500 to-rose-500',
                'rgba(236,72,153,0.06)',
                'rgba(236,72,153,0.2)',
                '#f472b6',
            ],
            [
                'Gain de temps massif',
                'Automatisez les tâches répétitives : calcul des moyennes, génération de bulletins, relances paiements. Jusqu\'à 80% de temps économisé.',
                'M13 10V3L4 14h7v7l9-11h-7z',
                'from-cyan-500 to-sky-500',
                'rgba(6,182,212,0.06)',
                'rgba(6,182,212,0.2)',
                '#22d3ee',
            ],
        ];
        @endphp

        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($advantages as $i => $adv)
            <div class="reveal delay-{{ $i * 100 + 100 }} group rounded-2xl p-7 transition-all duration-300 cursor-default"
                 style="background: {{ $adv[4] }}; border: 1px solid {{ $adv[5] }};">
                {{-- Icon with glow --}}
                <div class="relative mb-6 inline-flex">
                    <div class="w-14 h-14 rounded-2xl bg-gradient-to-br {{ $adv[3] }} flex items-center justify-center shadow-lg transition-transform duration-300 group-hover:scale-110">
                        <svg class="w-7 h-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $adv[2] }}"/>
                        </svg>
                    </div>
                    {{-- Glow dot --}}
                    <span class="absolute -top-1 -right-1 w-3 h-3 rounded-full animate-ping" style="background: {{ $adv[6] }};"></span>
                    <span class="absolute -top-1 -right-1 w-3 h-3 rounded-full opacity-70" style="background: {{ $adv[6] }};"></span>
                </div>
                <h3 class="text-lg font-bold text-white mb-3 transition-colors adv-title">{{ $adv[0] }}</h3>
                <p class="text-slate-400 text-sm leading-relaxed">{{ $adv[1] }}</p>
            </div>
            @endforeach
        </div>

        {{-- Bottom highlight banner --}}
        <div class="reveal mt-16 rounded-3xl p-8 lg:p-12 flex flex-col lg:flex-row items-center gap-8 text-center lg:text-left"
             style="background: linear-gradient(135deg, rgba(16,185,129,0.08), rgba(99,102,241,0.08)); border: 1px solid rgba(16,185,129,0.15);">
            <div class="w-20 h-20 rounded-3xl flex items-center justify-center shrink-0 shadow-2xl"
                 style="background: linear-gradient(135deg, #10b981, #6366f1);">
                <svg class="w-10 h-10 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0"/>
                </svg>
            </div>
            <div class="flex-1">
                <h3 class="text-2xl font-black text-white mb-2">
                    Jusqu'à <span style="background: linear-gradient(135deg,#34d399,#6366f1); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;">80% de temps économisé</span> sur les tâches administratives
                </h3>
                <p class="text-slate-400">
                    La digitalisation complète supprime les tâches répétitives : calcul des moyennes, génération de bulletins, relances paiements — tout automatisé.
                </p>
            </div>
            <a href="#contact"
               class="shrink-0 inline-flex items-center gap-2 px-7 py-4 rounded-xl font-semibold text-white transition-all duration-300 hover:scale-105"
               style="background: linear-gradient(135deg,#10b981,#059669); box-shadow: 0 0 30px rgba(16,185,129,0.3);">
                Je veux ça
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                </svg>
            </a>
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════ --}}
{{-- ONLINE PAYMENT                                  --}}
{{-- ═══════════════════════════════════════════════ --}}
<section id="paiement" class="py-28 relative overflow-hidden" style="background: linear-gradient(180deg, #0a0f1e 0%, #0d1223 100%);">
    {{-- Decorative blobs --}}
    <div class="absolute -top-40 -right-40 w-96 h-96 rounded-full opacity-10" style="background: radial-gradient(circle, #10b981, transparent); filter: blur(80px);"></div>
    <div class="absolute -bottom-40 -left-40 w-80 h-80 rounded-full opacity-10" style="background: radial-gradient(circle, #6366f1, transparent); filter: blur(80px);"></div>

    <div class="max-w-7xl mx-auto px-6 relative z-10">

        {{-- Header --}}
        <div class="text-center mb-20 reveal">
            <span class="inline-block px-4 py-1.5 rounded-full text-xs font-semibold text-emerald-300 mb-5"
                  style="background: rgba(16,185,129,0.08); border: 1px solid rgba(16,185,129,0.2);">
                Paiement en ligne sécurisé
            </span>
            <h2 class="text-4xl lg:text-5xl font-black mb-5">
                Les parents paient leurs frais<br>
                <span style="background: linear-gradient(135deg,#34d399,#10b981,#6366f1); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;">
                    en un seul clic
                </span>
            </h2>
            <p class="text-slate-400 text-lg max-w-2xl mx-auto">
                ScolApp intègre les principales solutions de paiement locales et internationales.
                Finis les files d'attente à la caisse — chaque parent règle depuis son téléphone.
            </p>
        </div>

        {{-- Payment methods grid --}}
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-16">
            @php
            $providers = [
                ['Waafi',    '#0077B6', 'W', 'Djibouti · Somalie'],
                ['CAC Pay',  '#7C3AED', 'C', 'Djibouti'],
                ['D-Money',  '#059669', 'D', 'Djibouti'],
                ['Exim Bank','#1D4ED8', 'E', 'Djibouti'],
                ['Visa',     '#1A1F71', 'V', 'International'],
                ['MasterCard','#EB001B','M', 'International'],
            ];
            @endphp
            @foreach($providers as $i => $p)
            <div class="reveal delay-{{ $i * 100 + 100 }} group rounded-2xl p-5 text-center flex flex-col items-center gap-3 cursor-default transition-all duration-300"
                 style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.07);"
                 onmouseenter="this.style.borderColor='{{ $p[1] }}44'; this.style.background='rgba(255,255,255,0.06)';"
                 onmouseleave="this.style.borderColor='rgba(255,255,255,0.07)'; this.style.background='rgba(255,255,255,0.03)';">
                <div class="w-14 h-14 rounded-2xl flex items-center justify-center font-black text-2xl text-white shadow-lg"
                     style="background: {{ $p[1] }};">
                    {{ $p[2] }}
                </div>
                <div>
                    <p class="text-white font-bold text-sm">{{ $p[0] }}</p>
                    <p class="text-slate-500 text-xs">{{ $p[3] }}</p>
                </div>
            </div>
            @endforeach
        </div>

        {{-- How it works — 3 steps --}}
        <div class="grid md:grid-cols-3 gap-6 mb-16">
            @foreach([
                ['01', 'Parent reçoit sa facture', 'Une notification push ou SMS est envoyée automatiquement dès qu\'une facture est générée.', '#6366f1', 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9'],
                ['02', 'Il choisit son mode de paiement', 'Waafi, CAC Pay, D-Money, Exim, Visa… Le parent sélectionne la méthode qui lui convient.', '#10b981', 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z'],
                ['03', 'Paiement validé & reçu instantané', 'Le solde est mis à jour en temps réel. Un reçu PDF est généré automatiquement et envoyé par email.', '#8b5cf6', 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0'],
            ] as $step)
            <div class="reveal glass-card rounded-2xl p-7 relative overflow-hidden">
                <div class="absolute top-4 right-4 text-6xl font-black opacity-5 text-white leading-none">{{ $step[0] }}</div>
                <div class="w-12 h-12 rounded-xl flex items-center justify-center mb-5"
                     style="background: {{ $step[2] }}22; border: 1px solid {{ $step[2] }}44;">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="{{ $step[2] }}" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $step[3] }}"/>
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-white mb-3">{{ $step[1] }}</h3>
                <p class="text-slate-400 text-sm leading-relaxed">{{ $step[2] }}</p>
            </div>
            @endforeach
        </div>

        {{-- Bottom trust banner --}}
        <div class="reveal rounded-3xl p-8 lg:p-12 grid md:grid-cols-3 gap-8 text-center"
             style="background: linear-gradient(135deg, rgba(99,102,241,0.07), rgba(16,185,129,0.07)); border: 1px solid rgba(99,102,241,0.15);">
            @foreach([
                ['🔒', 'Paiements chiffrés SSL', 'Toutes les transactions sont sécurisées end-to-end avec SSL 256-bit.'],
                ['📲', 'Accessible depuis l\'app', 'Les parents paient directement depuis l\'application mobile ScolApp.'],
                ['📊', 'Suivi en temps réel', 'L\'école voit chaque paiement instantanément dans son tableau de bord.'],
            ] as $t)
            <div>
                <div class="text-4xl mb-3">{{ $t[0] }}</div>
                <h4 class="text-white font-bold mb-2">{{ $t[1] }}</h4>
                <p class="text-slate-400 text-sm leading-relaxed">{{ $t[2] }}</p>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════ --}}
{{-- TIMELINE LIVRAISON                              --}}
{{-- ═══════════════════════════════════════════════ --}}
<section id="timeline" class="py-28 relative overflow-hidden" style="background: linear-gradient(180deg, #0d1223 0%, #0f172a 100%);">
    {{-- Glow --}}
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-96 h-96 rounded-full opacity-10"
         style="background: radial-gradient(circle, #6366f1, transparent); filter: blur(80px);"></div>

    <div class="max-w-5xl mx-auto px-6 relative z-10">
        {{-- Header --}}
        <div class="text-center mb-20 reveal">
            <span class="inline-block px-4 py-1.5 rounded-full text-xs font-semibold text-indigo-300 mb-5"
                  style="background: rgba(99,102,241,0.1); border: 1px solid rgba(99,102,241,0.2);">
                Processus de livraison
            </span>
            <h2 class="text-4xl lg:text-5xl font-black mb-5">
                De la signature à la <span class="gradient-text">mise en production</span>
            </h2>
            <p class="text-slate-400 text-lg max-w-2xl mx-auto">
                Un accompagnement structuré en 5 étapes pour garantir une transition fluide et un démarrage réussi.
            </p>
        </div>

        {{-- Timeline --}}
        @php
        $steps = [
            ['01', 'Analyse & Cadrage',       'Audit de vos besoins, cartographie des utilisateurs, configuration initiale de la plateforme.', 'Semaine 1', 'from-indigo-500 to-blue-600', 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01'],
            ['02', 'Import des données',       'Migration de vos données existantes (élèves, enseignants, niveaux) avec validation et nettoyage.', 'Semaine 2', 'from-purple-500 to-violet-600', 'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12'],
            ['03', 'Paramétrage avancé',       'Configuration des grilles de notation, emplois du temps, tarifs, modèles de bulletins.', 'Semaine 3', 'from-pink-500 to-rose-600', 'M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4'],
            ['04', 'Formation des équipes',    'Sessions de formation adaptées à chaque profil (admin, enseignants, comptable, directeur).', 'Semaine 4', 'from-amber-500 to-orange-600', 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
            ['05', 'Lancement & Suivi',        'Mise en production accompagnée. Support prioritaire pendant 30 jours. Bilan mensuel.', 'Semaine 5+', 'from-emerald-500 to-teal-600', 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0'],
        ];
        @endphp

        <div class="relative">
            {{-- Center line (desktop) --}}
            <div class="hidden md:block timeline-line"></div>

            <div class="space-y-12">
                @foreach($steps as $i => $step)
                <div class="reveal delay-{{ $i * 100 + 100 }} flex {{ $i % 2 === 0 ? 'md:flex-row' : 'md:flex-row-reverse' }} items-center gap-8">
                    {{-- Content card --}}
                    <div class="flex-1 {{ $i % 2 === 0 ? 'md:text-right' : 'md:text-left' }}">
                        <div class="glass-card rounded-2xl p-7 inline-block w-full md:max-w-md {{ $i % 2 === 0 ? 'md:ml-auto' : 'md:mr-auto' }}">
                            <span class="inline-block px-3 py-1 rounded-full text-xs font-bold text-indigo-300 mb-3"
                                  style="background: rgba(99,102,241,0.1);">{{ $step[3] }}</span>
                            <h3 class="text-xl font-bold text-white mb-3">{{ $step[1] }}</h3>
                            <p class="text-slate-400 text-sm leading-relaxed">{{ $step[2] }}</p>
                        </div>
                    </div>

                    {{-- Center dot --}}
                    <div class="hidden md:flex timeline-dot bg-gradient-to-br {{ $step[4] }}">
                        <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $step[5] }}"/>
                        </svg>
                    </div>

                    {{-- Spacer --}}
                    <div class="hidden md:block flex-1"></div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════ --}}
{{-- FORMATIONS                                      --}}
{{-- ═══════════════════════════════════════════════ --}}
<section id="formations" class="py-28" style="background: #0f172a;">
    <div class="max-w-7xl mx-auto px-6">
        {{-- Header --}}
        <div class="text-center mb-20 reveal">
            <span class="inline-block px-4 py-1.5 rounded-full text-xs font-semibold text-indigo-300 mb-5"
                  style="background: rgba(99,102,241,0.1); border: 1px solid rgba(99,102,241,0.2);">
                Accompagnement & Formations
            </span>
            <h2 class="text-4xl lg:text-5xl font-black mb-5">
                Des formations <span class="gradient-text">sur mesure</span>
                <br>pour chaque rôle
            </h2>
            <p class="text-slate-400 text-lg max-w-2xl mx-auto">
                Chaque membre de votre équipe bénéficie d'une formation adaptée à son profil et à ses responsabilités.
            </p>
        </div>

        @php
        $formations = [
            [
                'Administrateur',
                'Administration complète',
                'Paramétrage, gestion des utilisateurs, supervision financière et académique, rapports.',
                ['Paramétrage complet', 'Gestion utilisateurs', 'Finance & rapports', 'Supervision globale'],
                'from-indigo-600 to-purple-600',
                '2 jours',
                'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z'
            ],
            [
                'Enseignant',
                'Prise en main pédagogique',
                'Saisie des notes, gestion des présences, consultation des emplois du temps, messagerie.',
                ['Saisie des notes', 'Présences élèves', 'Emplois du temps', 'Messagerie'],
                'from-emerald-500 to-teal-600',
                '1 jour',
                'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'
            ],
            [
                'Comptable / Caissier',
                'Gestion financière',
                'Facturation, enregistrement des paiements, suivi des impayés, rapports financiers.',
                ['Facturation', 'Encaissements', 'Suivi impayés', 'Rapports fin.'],
                'from-amber-500 to-orange-600',
                '1 jour',
                'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z'
            ],
            [
                'Directeur',
                'Supervision & Rapports',
                'Tableaux de bord, statistiques académiques, suivi des absences, pilotage de l\'établissement.',
                ['Tableaux de bord', 'Stats académiques', 'Pilotage', 'Rapports'],
                'from-pink-500 to-rose-600',
                '0.5 jour',
                'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'
            ],
        ];
        @endphp

        <div class="grid md:grid-cols-2 xl:grid-cols-4 gap-6">
            @foreach($formations as $i => $f)
            <div class="reveal delay-{{ $i * 100 + 100 }} formation-card rounded-2xl p-8 flex flex-col">
                {{-- Icon --}}
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br {{ $f[4] }} flex items-center justify-center mb-6 shadow-lg">
                    <svg class="w-7 h-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $f[6] }}"/>
                    </svg>
                </div>
                {{-- Title --}}
                <span class="text-xs font-bold text-indigo-400 uppercase tracking-widest mb-1">{{ $f[0] }}</span>
                <h3 class="text-lg font-bold text-white mb-3">{{ $f[1] }}</h3>
                <p class="text-slate-400 text-sm leading-relaxed mb-6 flex-1">{{ $f[2] }}</p>
                {{-- Checklist --}}
                <ul class="space-y-2 mb-6">
                    @foreach($f[3] as $item)
                    <li class="flex items-center gap-2 text-sm text-slate-300">
                        <svg class="w-4 h-4 text-indigo-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                        </svg>
                        {{ $item }}
                    </li>
                    @endforeach
                </ul>
                {{-- Duration badge --}}
                <div class="flex items-center gap-2 pt-4 border-t border-white/5">
                    <svg class="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0"/>
                    </svg>
                    <span class="text-sm text-slate-400">Durée : <strong class="text-white">{{ $f[5] }}</strong></span>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════ --}}
{{-- MOBILE APP                                      --}}
{{-- ═══════════════════════════════════════════════ --}}
<section id="mobile" class="py-28 relative overflow-hidden" style="background: linear-gradient(180deg, #0f172a 0%, #0d1223 100%);">
    <div class="absolute top-0 right-0 w-[500px] h-[500px] opacity-10" style="background: radial-gradient(circle at top right, #8b5cf6, transparent 60%); filter: blur(40px);"></div>

    <div class="max-w-7xl mx-auto px-6 relative z-10">
        <div class="grid lg:grid-cols-2 gap-16 items-center">

            {{-- Left — Content --}}
            <div class="reveal-left">
                <span class="inline-block px-4 py-1.5 rounded-full text-xs font-semibold text-violet-300 mb-6"
                      style="background: rgba(139,92,246,0.08); border: 1px solid rgba(139,92,246,0.25);">
                    Application mobile native
                </span>
                <h2 class="text-4xl lg:text-5xl font-black mb-6 leading-tight">
                    ScolApp dans votre<br>
                    <span style="background: linear-gradient(135deg,#a78bfa,#8b5cf6,#6366f1); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;">
                        poche — partout
                    </span>
                </h2>
                <p class="text-slate-400 text-lg leading-relaxed mb-10">
                    Enseignants, parents, directeurs et élèves accèdent à leur espace depuis
                    l'application iOS ou Android. Interface fluide, notifications push en temps réel.
                </p>

                {{-- Feature list --}}
                <div class="space-y-4 mb-10">
                    @foreach([
                        ['Tableau de bord personnalisé par rôle', '#6366f1'],
                        ['Notifications push instantanées (absences, notes, paiements)', '#8b5cf6'],
                        ['Consultation des bulletins & emplois du temps', '#a78bfa'],
                        ['Paiement des frais scolaires intégré (Waafi, D-Money…)', '#10b981'],
                        ['Messagerie école–parents en temps réel', '#f59e0b'],
                    ] as $feat)
                    <div class="flex items-center gap-3">
                        <div class="w-5 h-5 rounded-full shrink-0 flex items-center justify-center"
                             style="background: {{ $feat[1] }}22; border: 1px solid {{ $feat[1] }}55;">
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="{{ $feat[1] }}" stroke-width="3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                            </svg>
                        </div>
                        <span class="text-slate-300 text-sm">{{ $feat[0] }}</span>
                    </div>
                    @endforeach
                </div>

                {{-- Store badges --}}
                <div class="flex flex-wrap gap-4">
                    {{-- App Store --}}
                    <div class="flex items-center gap-3 px-5 py-3 rounded-2xl transition-all duration-300 hover:scale-105 cursor-pointer"
                         style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.12);">
                        <svg class="w-8 h-8 text-white" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.8-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M13 3.5c.73-.83 1.94-1.46 2.94-1.5.13 1.17-.34 2.35-1.04 3.19-.69.85-1.83 1.51-2.95 1.42-.15-1.15.41-2.35 1.05-3.11z"/>
                        </svg>
                        <div>
                            <p class="text-xs text-slate-400 leading-none">Disponible sur</p>
                            <p class="text-sm font-bold text-white">App Store</p>
                        </div>
                    </div>
                    {{-- Google Play --}}
                    <div class="flex items-center gap-3 px-5 py-3 rounded-2xl transition-all duration-300 hover:scale-105 cursor-pointer"
                         style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.12);">
                        <svg class="w-8 h-8" viewBox="0 0 24 24" fill="none">
                            <path d="M3.18 23.76a2 2 0 001.26-.38l.06-.04 11.37-6.57-.03-.03-3.01-3.01-9.65 10.03z" fill="#EA4335"/>
                            <path d="M20.43 10.37l-.01-.01-2.6-1.5-3.3 2.9 3.31 3.31 2.6-1.5c.74-.43.74-1.76 0-2.2z" fill="#FBBC04"/>
                            <path d="M3.18.24C3 .42 2.89.7 2.89 1.07v21.86c0 .37.11.65.29.83l.04.04 12.25-12.26-.03-.03L3.18.24z" fill="#4285F4"/>
                            <path d="M12.81 12l3.01-3.01-11.37-6.57a2.05 2.05 0 00-1.27-.4L12.81 12z" fill="#34A853"/>
                        </svg>
                        <div>
                            <p class="text-xs text-slate-400 leading-none">Disponible sur</p>
                            <p class="text-sm font-bold text-white">Google Play</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Right — Phone mockup --}}
            <div class="reveal-right flex justify-center">
                <div class="relative">
                    {{-- Phone frame --}}
                    <div class="w-64 rounded-[44px] shadow-2xl overflow-hidden relative"
                         style="background: #0a0a1a; border: 2px solid rgba(139,92,246,0.4); box-shadow: 0 0 60px rgba(139,92,246,0.2), 0 40px 80px rgba(0,0,0,0.6);">
                        {{-- Notch --}}
                        <div class="flex justify-center pt-3 pb-1">
                            <div class="w-20 h-5 rounded-full" style="background: #1a1a2e;"></div>
                        </div>
                        {{-- Screen content --}}
                        <div class="px-4 pb-6 space-y-3">
                            {{-- Header --}}
                            <div class="flex items-center justify-between py-2">
                                <div>
                                    <p class="text-xs text-slate-400">Bonjour,</p>
                                    <p class="text-sm font-bold text-white">Parent · Ahmed</p>
                                </div>
                                <div class="w-8 h-8 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center">
                                    <span class="text-xs font-bold text-white">A</span>
                                </div>
                            </div>
                            {{-- Stat cards --}}
                            <div class="grid grid-cols-2 gap-2">
                                <div class="rounded-2xl p-3" style="background: rgba(99,102,241,0.15); border: 1px solid rgba(99,102,241,0.25);">
                                    <p class="text-xs text-indigo-300">Enfants</p>
                                    <p class="text-xl font-black text-white">2</p>
                                </div>
                                <div class="rounded-2xl p-3" style="background: rgba(16,185,129,0.12); border: 1px solid rgba(16,185,129,0.25);">
                                    <p class="text-xs text-emerald-300">Solde dû</p>
                                    <p class="text-xl font-black text-white">0 DJF</p>
                                </div>
                            </div>
                            {{-- Notification --}}
                            <div class="rounded-2xl p-3" style="background: rgba(245,158,11,0.08); border: 1px solid rgba(245,158,11,0.2);">
                                <div class="flex items-start gap-2">
                                    <div class="w-2 h-2 rounded-full bg-amber-400 shrink-0 mt-1.5 animate-pulse"></div>
                                    <div>
                                        <p class="text-xs font-semibold text-amber-300">Bulletin disponible</p>
                                        <p class="text-xs text-slate-400">Trimestre 2 · Yasmine</p>
                                    </div>
                                </div>
                            </div>
                            {{-- Payment button --}}
                            <div class="rounded-2xl p-3 text-center cursor-pointer"
                                 style="background: linear-gradient(135deg,#6366f1,#8b5cf6);">
                                <p class="text-xs font-bold text-white">💳 Payer les frais</p>
                                <p class="text-xs text-indigo-200">Waafi · D-Money · Visa</p>
                            </div>
                            {{-- Recent activity --}}
                            <div class="space-y-2">
                                @foreach([['Absence signalée','3ème A · Bilal','8:30'],['Note publiée','Maths · 18/20','hier']] as $n)
                                <div class="flex items-center gap-2 rounded-xl px-3 py-2" style="background: rgba(255,255,255,0.03);">
                                    <div class="w-1.5 h-1.5 rounded-full bg-indigo-400 shrink-0"></div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs text-white font-medium truncate">{{ $n[0] }}</p>
                                        <p class="text-xs text-slate-500">{{ $n[1] }}</p>
                                    </div>
                                    <span class="text-xs text-slate-600">{{ $n[2] }}</span>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    {{-- Floating badge --}}
                    <div class="absolute -top-4 -right-8 rounded-2xl px-4 py-3 shadow-xl"
                         style="background: linear-gradient(135deg,#10b981,#059669); box-shadow: 0 8px 30px rgba(16,185,129,0.4);">
                        <p class="text-xs text-emerald-100">Langues</p>
                        <p class="text-base font-black text-white">FR · AR · EN</p>
                    </div>

                    {{-- Floating badge bottom --}}
                    <div class="absolute -bottom-4 -left-8 rounded-2xl px-4 py-3 shadow-xl"
                         style="background: rgba(15,23,42,0.95); border: 1px solid rgba(139,92,246,0.3);">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-violet-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                            </svg>
                            <p class="text-sm font-bold text-white">iOS & Android</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════ --}}
{{-- ABOUT                                           --}}
{{-- ═══════════════════════════════════════════════ --}}
<section id="about" class="py-28 overflow-hidden" style="background: linear-gradient(180deg, #0f172a 0%, #0d1223 100%);">
    <div class="max-w-7xl mx-auto px-6">
        <div class="grid lg:grid-cols-2 gap-16 items-center">

            {{-- Left — Visual --}}
            <div class="reveal-left">
                <div class="relative">
                    {{-- Main card --}}
                    <div class="rounded-3xl p-8 relative overflow-hidden"
                         style="background: linear-gradient(135deg, rgba(99,102,241,0.12), rgba(139,92,246,0.06)); border: 1px solid rgba(99,102,241,0.2);">
                        <div class="absolute top-0 right-0 w-48 h-48 rounded-full opacity-20"
                             style="background: radial-gradient(circle, #6366f1, transparent); filter: blur(40px);"></div>
                        {{-- Values --}}
                        <div class="grid grid-cols-2 gap-4 relative z-10">
                            @foreach([
                                ['Innovation','Nous intégrons en permanence les dernières technologies.',  'M13 10V3L4 14h7v7l9-11h-7z', 'text-yellow-400'],
                                ['Fiabilité', 'Disponibilité 99.9%. Vos données toujours accessibles.',   'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z', 'text-emerald-400'],
                                ['Proximité', 'Un support humain, réactif et disponible pour vous.',       'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z', 'text-blue-400'],
                                ['Simplicité', 'Interface intuitive. Prise en main rapide, même sans formation technique.', 'M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z', 'text-purple-400'],
                            ] as $v)
                            <div class="glass-card rounded-2xl p-5">
                                <svg class="w-7 h-7 {{ $v[3] }} mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $v[2] }}"/>
                                </svg>
                                <h4 class="font-bold text-white mb-1">{{ $v[0] }}</h4>
                                <p class="text-xs text-slate-400 leading-relaxed">{{ $v[1] }}</p>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    {{-- Floating stat --}}
                    <div class="absolute -bottom-6 -right-6 rounded-2xl px-6 py-4 shadow-2xl"
                         style="background: linear-gradient(135deg,#6366f1,#8b5cf6); box-shadow: 0 8px 40px rgba(99,102,241,0.4);">
                        <p class="text-indigo-200 text-xs">Années d'expérience</p>
                        <p class="text-3xl font-black">5+</p>
                    </div>
                </div>
            </div>

            {{-- Right — Text --}}
            <div class="reveal-right">
                <span class="inline-block px-4 py-1.5 rounded-full text-xs font-semibold text-indigo-300 mb-6"
                      style="background: rgba(99,102,241,0.1); border: 1px solid rgba(99,102,241,0.2);">
                    Notre mission
                </span>
                <h2 class="text-4xl lg:text-5xl font-black mb-6">
                    Nous croyons que la technologie <span class="gradient-text">transforme l'éducation</span>
                </h2>
                <p class="text-slate-400 text-lg leading-relaxed mb-6">
                    ScolApp est née d'un constat simple : les établissements scolaires méritent des outils modernes
                    pour se concentrer sur l'essentiel — la réussite des élèves.
                </p>
                <p class="text-slate-400 leading-relaxed mb-10">
                    Notre équipe d'experts en éducation et en technologie a conçu une plateforme qui
                    répond aux vrais besoins du terrain, avec une obsession : la simplicité sans compromis sur la puissance.
                </p>

                {{-- Key points --}}
                <div class="space-y-4">
                    @foreach([
                        ['Solution 100% cloud — aucune installation requise'],
                        ['Application mobile iOS & Android native incluse'],
                        ['Interface disponible en français, arabe et anglais'],
                        ['Paiement en ligne (Waafi, D-Money, Visa…) intégré nativement'],
                    ] as $point)
                    <div class="flex items-start gap-3">
                        <div class="w-6 h-6 rounded-full bg-gradient-to-br from-indigo-500 to-purple-500 flex items-center justify-center shrink-0 mt-0.5">
                            <svg class="w-3.5 h-3.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                            </svg>
                        </div>
                        <span class="text-slate-300">{{ $point[0] }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════ --}}
{{-- PRICING                                         --}}
{{-- ═══════════════════════════════════════════════ --}}
<section id="tarifs" class="py-28 relative overflow-hidden" style="background: linear-gradient(180deg, #0d1223 0%, #0f172a 100%);">
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[700px] h-[700px] rounded-full opacity-5"
         style="background: radial-gradient(circle, #6366f1, transparent); filter: blur(100px);"></div>

    <div class="max-w-6xl mx-auto px-6 relative z-10">

        {{-- Header --}}
        <div class="text-center mb-16 reveal">
            <span class="inline-block px-4 py-1.5 rounded-full text-xs font-semibold text-indigo-300 mb-5"
                  style="background: rgba(99,102,241,0.1); border: 1px solid rgba(99,102,241,0.2);">
                Tarification transparente
            </span>
            <h2 class="text-4xl lg:text-5xl font-black mb-5">
                Un abonnement,<br>
                <span class="gradient-text">tout inclus</span>
            </h2>
            <p class="text-slate-400 text-lg max-w-xl mx-auto">
                Pas de frais cachés. Pas de modules payants. Toutes les fonctionnalités sont incluses dans chaque plan.
            </p>
        </div>

        {{-- Plans grid --}}
        <div class="grid md:grid-cols-3 gap-6 mb-12">
            @php
            $plans = [
                [
                    'Starter',
                    'Pour les petites écoles',
                    'Contactez-nous',
                    'par mois · par établissement',
                    false,
                    ['Jusqu\'à 300 élèves', '3 classes maximum', 'Gestion académique complète', 'Finance & facturation', 'Application mobile iOS/Android', 'Bulletins PDF automatiques', 'Support email 24/7', '1 administrateur'],
                    'from-slate-600 to-slate-700',
                    '#64748b',
                ],
                [
                    'Pro',
                    'Le plus populaire',
                    'Contactez-nous',
                    'par mois · par établissement',
                    true,
                    ['Élèves illimités', 'Classes illimitées', 'Tout Starter +', 'Paiement en ligne (Waafi, D-Money…)', 'Multi-niveaux & filières', 'Rapports & analytics avancés', 'Notifications push & SMS', 'Utilisateurs illimités', 'Support prioritaire 24/7'],
                    'from-indigo-600 to-purple-600',
                    '#6366f1',
                ],
                [
                    'Multi-École',
                    'Groupes scolaires',
                    'Sur devis',
                    'contrat annuel · accompagnement complet',
                    false,
                    ['Établissements multiples', 'Tout Pro +', 'Tableau de bord groupe', 'Données consolidées', 'Personnalisation avancée', 'Formation sur site incluse', 'Gestionnaire de compte dédié', 'SLA garanti 99.9%'],
                    'from-amber-600 to-orange-600',
                    '#f59e0b',
                ],
            ];
            @endphp

            @foreach($plans as $i => $plan)
            <div class="reveal delay-{{ $i * 150 + 100 }} relative flex flex-col rounded-3xl overflow-hidden transition-transform duration-300 hover:-translate-y-2"
                 style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,{{ $plan[4] ? '0.15' : '0.07' }});">

                @if($plan[4])
                {{-- Popular badge --}}
                <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r {{ $plan[6] }}"></div>
                <div class="absolute top-4 right-4">
                    <span class="px-3 py-1 rounded-full text-xs font-bold text-white"
                          style="background: linear-gradient(135deg,#6366f1,#8b5cf6);">⭐ Recommandé</span>
                </div>
                @endif

                <div class="p-8 flex flex-col flex-1">
                    {{-- Plan name --}}
                    <div class="mb-6">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br {{ $plan[6] }} flex items-center justify-center mb-4">
                            <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-black text-white">{{ $plan[0] }}</h3>
                        <p class="text-slate-500 text-sm mt-1">{{ $plan[1] }}</p>
                    </div>

                    {{-- Price --}}
                    <div class="mb-8 pb-8" style="border-bottom: 1px solid rgba(255,255,255,0.06);">
                        <p class="text-3xl font-black text-white">{{ $plan[2] }}</p>
                        <p class="text-slate-500 text-xs mt-1">{{ $plan[3] }}</p>
                    </div>

                    {{-- Features --}}
                    <ul class="space-y-3 flex-1 mb-8">
                        @foreach($plan[5] as $feat)
                        <li class="flex items-start gap-2.5 text-sm text-slate-300">
                            <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="{{ $plan[7] }}" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                            </svg>
                            {{ $feat }}
                        </li>
                        @endforeach
                    </ul>

                    {{-- CTA --}}
                    <a href="#contact"
                       class="w-full py-3.5 rounded-xl font-semibold text-center transition-all duration-300 hover:scale-105 block"
                       style="{{ $plan[4] ? 'background: linear-gradient(135deg,#6366f1,#8b5cf6); color: white; box-shadow: 0 8px 24px rgba(99,102,241,0.3);' : 'background: rgba(255,255,255,0.05); color: #cbd5e1; border: 1px solid rgba(255,255,255,0.1);' }}">
                        Demander un devis
                    </a>
                </div>
            </div>
            @endforeach
        </div>

        {{-- Bottom note --}}
        <div class="reveal text-center">
            <p class="text-slate-500 text-sm">
                Tous les plans incluent : hébergement cloud sécurisé · mises à jour automatiques · formation initiale · support multilingue FR/AR/EN
            </p>
            <p class="text-slate-600 text-xs mt-3">
                Tarification adaptée au contexte local (Djibouti). Paiement possible via Waafi, D-Money, virement bancaire.
            </p>
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════ --}}
{{-- CONTACT                                         --}}
{{-- ═══════════════════════════════════════════════ --}}
<section id="contact" class="py-28 relative overflow-hidden" style="background: #0f172a;">
    {{-- Background glow --}}
    <div class="absolute inset-0 overflow-hidden">
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] rounded-full" style="opacity: 0.08;"
             style="background: radial-gradient(circle, rgba(99,102,241,0.15), transparent 70%); filter: blur(60px);"></div>
    </div>

    <div class="max-w-7xl mx-auto px-6 relative z-10">
        <div class="grid lg:grid-cols-2 gap-16 items-start">

            {{-- Left — Info --}}
            <div class="reveal-left">
                <span class="inline-block px-4 py-1.5 rounded-full text-xs font-semibold text-indigo-300 mb-6"
                      style="background: rgba(99,102,241,0.1); border: 1px solid rgba(99,102,241,0.2);">
                    Parlons de votre projet
                </span>
                <h2 class="text-4xl lg:text-5xl font-black mb-6">
                    Prêt à moderniser <span class="gradient-text">votre école ?</span>
                </h2>
                <p class="text-slate-400 text-lg leading-relaxed mb-10">
                    Notre équipe est disponible 24h/24 et 7j/7 pour répondre à toutes vos questions et vous accompagner dans la mise en place de ScolApp.
                </p>

                <div class="space-y-6">

                    {{-- Email --}}
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl flex items-center justify-center shrink-0"
                             style="background: rgba(99,102,241,0.1); border: 1px solid rgba(99,102,241,0.2);">
                            <svg class="w-5 h-5 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 font-medium uppercase tracking-widest">Email</p>
                            <a href="mailto:contact@scolapp.com" class="text-white font-semibold hover:text-indigo-400 transition">contact@scolapp.com</a>
                        </div>
                    </div>

                    {{-- WhatsApp --}}
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl flex items-center justify-center shrink-0"
                             style="background: rgba(37,211,102,0.1); border: 1px solid rgba(37,211,102,0.25);">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor" style="color:#25d366;">
                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/>
                                <path d="M12 0C5.373 0 0 5.373 0 12c0 2.126.555 4.126 1.524 5.868L0 24l6.322-1.499A11.951 11.951 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.818a9.818 9.818 0 01-5.013-1.377l-.36-.214-3.724.883.934-3.617-.235-.372A9.818 9.818 0 012.182 12C2.182 6.573 6.573 2.182 12 2.182S21.818 6.573 21.818 12 17.427 21.818 12 21.818z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 font-medium uppercase tracking-widest">WhatsApp</p>
                            <a href="https://wa.me/25377049495" target="_blank" rel="noopener"
                               class="text-white font-semibold hover:text-green-400 transition">+253 77 04 94 95</a>
                        </div>
                    </div>

                    {{-- Support 24/7 --}}
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl flex items-center justify-center shrink-0"
                             style="background: rgba(99,102,241,0.1); border: 1px solid rgba(99,102,241,0.2);">
                            <svg class="w-5 h-5 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 font-medium uppercase tracking-widest">Disponibilité</p>
                            <p class="text-white font-semibold">Support 24h/24 — 7j/7</p>
                        </div>
                    </div>

                </div>
            </div>

            {{-- Right — Form --}}
            <div class="reveal-right">
                <div class="rounded-3xl p-8 lg:p-10"
                     style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06); backdrop-filter: blur(20px);">
                    <h3 class="text-xl font-bold text-white mb-8">Envoyer un message</h3>
                    <form id="contactForm" @submit.prevent="submitContact()" class="space-y-5">
                        <div class="grid sm:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-xs font-semibold text-slate-400 uppercase tracking-widest mb-2">Nom complet</label>
                                <input type="text" x-model="contact.name" required
                                       class="form-input w-full rounded-xl px-4 py-3.5 text-sm"
                                       placeholder="Votre nom">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-400 uppercase tracking-widest mb-2">Email</label>
                                <input type="email" x-model="contact.email" required
                                       class="form-input w-full rounded-xl px-4 py-3.5 text-sm"
                                       placeholder="email@ecole.dz">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 uppercase tracking-widest mb-2">Établissement</label>
                            <input type="text" x-model="contact.school"
                                   class="form-input w-full rounded-xl px-4 py-3.5 text-sm"
                                   placeholder="Nom de votre école">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 uppercase tracking-widest mb-2">Téléphone</label>
                            <input type="tel" x-model="contact.phone"
                                   class="form-input w-full rounded-xl px-4 py-3.5 text-sm"
                                   placeholder="+213 05...">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 uppercase tracking-widest mb-2">Message</label>
                            <textarea x-model="contact.message" required rows="4"
                                      class="form-input w-full rounded-xl px-4 py-3.5 text-sm resize-none"
                                      placeholder="Décrivez votre projet..."></textarea>
                        </div>
                        <button type="submit"
                                :disabled="contactSent || contactLoading"
                                class="w-full py-4 rounded-xl font-semibold text-white btn-glow transition-all flex items-center justify-center gap-2"
                                :class="contactSent ? 'opacity-80' : ''">
                            <template x-if="contactLoading">
                                <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                </svg>
                            </template>
                            <template x-if="!contactLoading && !contactSent">
                                <span class="flex items-center gap-2">
                                    Envoyer le message
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                                    </svg>
                                </span>
                            </template>
                            <template x-if="contactSent && !contactLoading">
                                <span class="flex items-center gap-2">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    Message envoyé !
                                </span>
                            </template>
                        </button>
                        <p x-show="contactSent" class="text-center text-green-400 text-sm">
                            ✅ Message envoyé ! Un email de confirmation vous a été adressé.
                        </p>
                        <p x-show="contactError" x-text="contactError" class="text-center text-red-400 text-sm"></p>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════ --}}
{{-- CTA BANNER                                      --}}
{{-- ═══════════════════════════════════════════════ --}}
<section class="py-20 relative overflow-hidden" style="background: linear-gradient(135deg, #312e81 0%, #4c1d95 50%, #1e1b4b 100%);">
    <div class="absolute inset-0" style="background: radial-gradient(ellipse at center, rgba(99,102,241,0.3) 0%, transparent 70%);"></div>
    <div class="max-w-4xl mx-auto px-6 text-center relative z-10">
        <div class="reveal">
            {{-- Launch offer badge --}}
            <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full text-sm font-semibold text-amber-300 mb-8"
                 style="background: rgba(245,158,11,0.1); border: 1px solid rgba(245,158,11,0.3);">
                <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></span>
                Offre de lancement — Soyez parmi les premiers
            </div>
            <h2 class="text-4xl lg:text-5xl font-black text-white mb-6">
                Digitalisez votre école<br>
                <span class="gradient-text">dès maintenant</span>
            </h2>
            <p class="text-indigo-200 text-lg mb-10 max-w-2xl mx-auto">
                Démo gratuite de 30 minutes, sans engagement. Notre équipe vous présente la plateforme et répond à toutes vos questions.
            </p>
            <div class="flex flex-wrap gap-4 justify-center">
                <a href="#contact"
                   class="inline-flex items-center gap-2 px-8 py-4 rounded-xl font-bold text-indigo-900 bg-white hover:bg-indigo-50 transition-all duration-300 hover:scale-105 shadow-xl">
                    Demander une démo gratuite
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                    </svg>
                </a>
                <a href="#tarifs"
                   class="inline-flex items-center gap-2 px-8 py-4 rounded-xl font-bold text-white border-2 border-white/30 hover:border-white/60 transition-all duration-300">
                    Voir les tarifs
                </a>
            </div>
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════ --}}
{{-- FOOTER                                          --}}
{{-- ═══════════════════════════════════════════════ --}}
<footer class="py-16 border-t border-white/5" style="background: #070d1a;">
    <div class="max-w-7xl mx-auto px-6">
        <div class="grid md:grid-cols-4 gap-12 mb-12">
            {{-- Brand --}}
            <div class="md:col-span-2">
                <div class="flex items-center gap-3 mb-5">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center">
                        <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                        </svg>
                    </div>
                    <span class="text-xl font-bold gradient-text">ScolApp</span>
                </div>
                <p class="text-slate-500 text-sm leading-relaxed max-w-sm">
                    La plateforme de gestion scolaire nouvelle génération. Simplifiez. Automatisez. Excellez.
                </p>
            </div>
            {{-- Links --}}
            <div>
                <h5 class="text-sm font-semibold text-white uppercase tracking-widest mb-5">Plateforme</h5>
                <ul class="space-y-3 text-sm text-slate-500">
                    <li><a href="#features"   class="hover:text-indigo-400 transition">Fonctionnalités</a></li>
                    <li><a href="#paiement"   class="hover:text-indigo-400 transition">Paiement en ligne</a></li>
                    <li><a href="#mobile"     class="hover:text-indigo-400 transition">Application mobile</a></li>
                    <li><a href="#tarifs"     class="hover:text-indigo-400 transition">Tarifs</a></li>
                    <li><a href="#timeline"   class="hover:text-indigo-400 transition">Livraison</a></li>
                    <li><a href="#formations" class="hover:text-indigo-400 transition">Formations</a></li>
                    <li><a href="{{ route('login') }}" class="hover:text-indigo-400 transition">Connexion</a></li>
                </ul>
            </div>
            <div>
                <h5 class="text-sm font-semibold text-white uppercase tracking-widest mb-5">Contact</h5>
                <ul class="space-y-3 text-sm text-slate-500">
                    <li><a href="#about"   class="hover:text-indigo-400 transition">À propos</a></li>
                    <li><a href="#contact" class="hover:text-indigo-400 transition">Nous contacter</a></li>
                    <li><span>contact@scolapp.com</span></li>
                </ul>
            </div>
        </div>
        <div class="border-t border-white/5 pt-8 flex flex-col sm:flex-row items-center justify-between gap-4 text-sm text-slate-600">
            <span>© {{ date('Y') }} ScolApp. Tous droits réservés.</span>
            <span>Conçu avec ❤️ pour l'éducation</span>
        </div>
    </div>
</footer>

<script>
function scolaApp() {
    return {
        scrolled: false,
        mobileOpen: false,
        contact: { name: '', email: '', school: '', phone: '', message: '' },
        contactLoading: false,
        contactSent: false,
        contactError: '',

        init() {
            // Navbar scroll effect
            window.addEventListener('scroll', () => {
                this.scrolled = window.scrollY > 50;
            });

            // Scroll reveal with IntersectionObserver
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.1, rootMargin: '0px 0px -50px 0px' });

            document.querySelectorAll('.reveal, .reveal-left, .reveal-right').forEach(el => {
                observer.observe(el);
            });

            // Smooth scroll for anchor links
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function(e) {
                    const href = this.getAttribute('href');
                    if (href === '#') return;
                    e.preventDefault();
                    const el = document.querySelector(href);
                    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
            });
        },

        async submitContact() {
            this.contactLoading = true;
            this.contactError = '';

            const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            try {
                const res = await fetch('{{ route("contact.store") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    body: JSON.stringify(this.contact),
                });

                const data = await res.json();

                if (res.ok) {
                    this.contactSent = true;
                    this.contact = { name: '', email: '', school: '', phone: '', message: '' };
                } else {
                    // Laravel validation errors
                    const errors = data.errors ? Object.values(data.errors).flat() : [];
                    this.contactError = errors[0] || data.message || 'Une erreur est survenue.';
                }
            } catch (e) {
                this.contactError = 'Impossible d\'envoyer le message. Veuillez réessayer.';
            } finally {
                this.contactLoading = false;
            }
        }
    };
}
</script>
</body>
</html>
