<!DOCTYPE html>
<html lang="fr" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="ScolApp — La plateforme de gestion scolaire nouvelle génération. Zéro papier, suivi en temps réel, paiement en ligne (D-Money, Waafi, CAC Pay, Exim Pay), notifications Email + WhatsApp, réconciliation et recouvrement automatisés.">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>ScolApp — La gestion scolaire, réinventée</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'ui-sans-serif', 'system-ui'] },
                    colors: {
                        ink: '#070b18',
                        brand: { 400: '#818cf8', 500: '#6366f1', 600: '#4f46e5' },
                        aqua: { 400: '#22d3ee', 500: '#06b6d4' },
                    },
                    animation: {
                        'gradient-shift': 'gradientShift 14s ease infinite',
                        'float': 'float 9s ease-in-out infinite',
                        'float-slow': 'float 13s ease-in-out infinite',
                        'spin-slow': 'spin 22s linear infinite',
                        'pulse-ring': 'pulseRing 2.6s cubic-bezier(0.4,0,0.6,1) infinite',
                    }
                }
            }
        }
    </script>
    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        * { font-family: 'Inter', sans-serif; }
        html, body { background: #070b18; }
        ::selection { background: #6366f1; color: #fff; }

        ::-webkit-scrollbar { width: 10px; }
        ::-webkit-scrollbar-track { background: #070b18; }
        ::-webkit-scrollbar-thumb { background: #1e293b; border-radius: 6px; }
        ::-webkit-scrollbar-thumb:hover { background: #334155; }

        /* ── Animated aurora hero ── */
        .hero-bg {
            background: radial-gradient(1200px 600px at 70% -10%, rgba(99,102,241,0.28), transparent 60%),
                        radial-gradient(900px 500px at 10% 20%, rgba(6,182,212,0.18), transparent 55%),
                        linear-gradient(160deg, #070b18 0%, #0c1230 45%, #0a0f24 100%);
        }
        .grid-overlay {
            background-image: linear-gradient(rgba(148,163,184,0.06) 1px, transparent 1px),
                              linear-gradient(90deg, rgba(148,163,184,0.06) 1px, transparent 1px);
            background-size: 46px 46px;
            -webkit-mask-image: radial-gradient(circle at 50% 40%, black, transparent 75%);
            mask-image: radial-gradient(circle at 50% 40%, black, transparent 75%);
        }
        .blob { position: absolute; border-radius: 50%; filter: blur(90px); opacity: .5; }
        .blob-1 { width: 520px; height: 520px; background: #4f46e5; top: -160px; right: -120px; animation: float 10s ease-in-out infinite; }
        .blob-2 { width: 420px; height: 420px; background: #06b6d4; bottom: -140px; left: -120px; animation: float 13s ease-in-out infinite reverse; }
        .blob-3 { width: 340px; height: 340px; background: #7c3aed; top: 45%; left: 42%; animation: float 16s ease-in-out infinite; opacity:.35; }

        @keyframes gradientShift { 0%,100%{background-position:0% 50%} 50%{background-position:100% 50%} }
        @keyframes float { 0%,100%{transform:translate(0,0)} 50%{transform:translate(24px,-30px)} }
        @keyframes pulseRing { 0%{transform:scale(.9);opacity:.7} 70%{transform:scale(1.35);opacity:0} 100%{opacity:0} }

        .grad-text {
            background: linear-gradient(100deg,#a5b4fc 0%,#6366f1 35%,#22d3ee 100%);
            -webkit-background-clip: text; background-clip: text; color: transparent;
        }
        .grad-text-warm {
            background: linear-gradient(100deg,#fbbf24,#f472b6);
            -webkit-background-clip: text; background-clip: text; color: transparent;
        }

        .glass { background: rgba(255,255,255,0.035); border: 1px solid rgba(255,255,255,0.07); backdrop-filter: blur(14px); }
        .glass:hover { border-color: rgba(129,140,248,0.4); }
        .card-lift { transition: transform .4s cubic-bezier(.2,.7,.2,1), border-color .4s, box-shadow .4s; }
        .card-lift:hover { transform: translateY(-8px); box-shadow: 0 24px 60px -24px rgba(79,70,229,0.5); }

        .btn-glow {
            background: linear-gradient(100deg,#4f46e5,#6366f1 45%,#06b6d4);
            background-size: 200% 100%;
            box-shadow: 0 12px 34px -12px rgba(79,70,229,0.7);
            transition: background-position .5s, transform .2s, box-shadow .3s;
        }
        .btn-glow:hover { background-position: 100% 0; transform: translateY(-2px); box-shadow: 0 18px 44px -12px rgba(6,182,212,0.6); }

        .marquee { display: flex; gap: 3rem; width: max-content; animation: ticker 28s linear infinite; }
        .marquee-track:hover .marquee { animation-play-state: paused; }
        @keyframes ticker { from{transform:translateX(0)} to{transform:translateX(-50%)} }

        .reveal { opacity: 0; transform: translateY(34px); transition: opacity .8s cubic-bezier(.2,.7,.2,1), transform .8s cubic-bezier(.2,.7,.2,1); }
        .reveal.in { opacity: 1; transform: none; }
        .reveal-left { opacity: 0; transform: translateX(-40px); transition: all .8s cubic-bezier(.2,.7,.2,1); }
        .reveal-left.in { opacity: 1; transform: none; }
        .reveal-right { opacity: 0; transform: translateX(40px); transition: all .8s cubic-bezier(.2,.7,.2,1); }
        .reveal-right.in { opacity: 1; transform: none; }

        .form-input { background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.09); color: #e2e8f0; transition: border-color .2s, box-shadow .2s; }
        .form-input:focus { outline: none; border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.18); }
        .form-input::placeholder { color: #64748b; }

        .bar { animation: grow 1.6s cubic-bezier(.2,.7,.2,1) both; transform-origin: bottom; }
        @keyframes grow { from{transform:scaleY(0)} to{transform:scaleY(1)} }

        .dot-live { position: relative; }
        .dot-live::after { content:''; position:absolute; inset:0; border-radius:9999px; background:#34d399; animation: pulseRing 2.4s infinite; }

        .chapter-num { font-size: 3.25rem; line-height: 1; font-weight: 900; color: transparent; -webkit-text-stroke: 1px rgba(129,140,248,0.35); }

        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-ink text-slate-200 antialiased overflow-x-hidden" x-data="site()" x-init="init()">

{{-- ══════════════════ NAV ══════════════════ --}}
<nav class="fixed top-0 inset-x-0 z-50 transition-all duration-500"
     :class="scrolled ? 'py-3 bg-ink/80 backdrop-blur-xl border-b border-white/5' : 'py-5'">
    <div class="max-w-7xl mx-auto px-5 flex items-center justify-between">
        <a href="#top" class="flex items-center gap-2.5">
            <img src="{{ asset('images/logo_ScolApp.png') }}" alt="ScolApp" class="h-9 w-9 object-contain">
            <span class="text-white font-extrabold text-lg tracking-tight">Scol<span class="grad-text">App</span></span>
        </a>
        <div class="hidden lg:flex items-center gap-6 text-sm font-medium text-slate-300">
            <a href="#academique" class="hover:text-white transition">Académique</a>
            <a href="#finance" class="hover:text-white transition">Finance</a>
            <a href="#communication" class="hover:text-white transition">Communication</a>
            <a href="#portals" class="hover:text-white transition">Portails</a>
            <a href="#integrations" class="hover:text-white transition">Intégrations</a>
            <a href="#tarifs" class="hover:text-white transition">Tarifs</a>
            <a href="#faq" class="hover:text-white transition">FAQ</a>
        </div>
        <div class="hidden lg:flex items-center gap-3">
            <a href="{{ route('login') }}" class="text-sm font-semibold text-slate-200 hover:text-white transition px-4 py-2">Se connecter</a>
            <a href="#contact" class="btn-glow text-sm font-semibold text-white px-5 py-2.5 rounded-xl">Demander une démo</a>
        </div>
        <button @click="mobileOpen = !mobileOpen" class="lg:hidden text-white p-2">
            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
    </div>
    <div x-show="mobileOpen" x-cloak x-transition class="lg:hidden mt-3 mx-4 rounded-2xl glass p-5 space-y-3">
        <a href="#academique" @click="mobileOpen=false" class="block text-slate-200 py-1">Académique</a>
        <a href="#finance" @click="mobileOpen=false" class="block text-slate-200 py-1">Finance</a>
        <a href="#communication" @click="mobileOpen=false" class="block text-slate-200 py-1">Communication</a>
        <a href="#portals" @click="mobileOpen=false" class="block text-slate-200 py-1">Portails</a>
        <a href="#integrations" @click="mobileOpen=false" class="block text-slate-200 py-1">Intégrations</a>
        <a href="#tarifs" @click="mobileOpen=false" class="block text-slate-200 py-1">Tarifs</a>
        <a href="#faq" @click="mobileOpen=false" class="block text-slate-200 py-1">FAQ</a>
        <div class="pt-3 border-t border-white/10 flex gap-3">
            <a href="{{ route('login') }}" class="flex-1 text-center py-2.5 rounded-xl border border-white/10 text-white text-sm font-semibold">Connexion</a>
            <a href="#contact" @click="mobileOpen=false" class="flex-1 text-center btn-glow py-2.5 rounded-xl text-white text-sm font-semibold">Démo</a>
        </div>
    </div>
</nav>

{{-- ══════════════════ HERO ══════════════════ --}}
<section id="top" class="hero-bg relative min-h-screen flex items-center overflow-hidden pt-28 pb-20">
    <div class="blob blob-1"></div><div class="blob blob-2"></div><div class="blob blob-3"></div>
    <div class="absolute inset-0 grid-overlay"></div>

    <div class="relative max-w-7xl mx-auto px-5 grid lg:grid-cols-2 gap-16 items-center">
        <div>
            <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full glass text-xs font-semibold text-slate-200 mb-7">
                <span class="w-2 h-2 rounded-full bg-emerald-400 dot-live"></span>
                Nouvelle génération · 100% en ligne · Djibouti &amp; Afrique
            </div>

            <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold text-white leading-[1.08] tracking-tight">
                La gestion de votre école,<br>
                <span class="grad-text">enfin</span>
                <span x-text="rotw" class="grad-text-warm"></span><br>
                et sans papier.
            </h1>

            <p class="mt-6 text-lg text-slate-400 max-w-xl leading-relaxed">
                ScolApp centralise <strong class="text-slate-200">inscriptions, présences, notes, finances et communication</strong>
                dans une seule plateforme. Suivi en <strong class="text-slate-200">temps réel</strong>, rapports dynamiques,
                paiement en ligne des factures et notifications <strong class="text-slate-200">Email + WhatsApp</strong> automatiques.
            </p>

            <div class="mt-9 flex flex-col sm:flex-row gap-4">
                <a href="#contact" class="btn-glow text-center text-white font-semibold px-7 py-4 rounded-xl">Demander une démo gratuite</a>
                <a href="#academique" class="text-center font-semibold px-7 py-4 rounded-xl border border-white/12 text-white hover:bg-white/5 transition">Découvrir les fonctionnalités</a>
            </div>

            <div class="mt-10 flex flex-wrap items-center gap-x-8 gap-y-3 text-sm text-slate-400">
                <span class="flex items-center gap-2"><svg class="w-4 h-4 text-emerald-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-8 8a1 1 0 01-1.4 0l-4-4a1 1 0 011.4-1.4L8 12.6l7.3-7.3a1 1 0 011.4 0z" clip-rule="evenodd"/></svg> Zéro installation</span>
                <span class="flex items-center gap-2"><svg class="w-4 h-4 text-emerald-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-8 8a1 1 0 01-1.4 0l-4-4a1 1 0 011.4-1.4L8 12.6l7.3-7.3a1 1 0 011.4 0z" clip-rule="evenodd"/></svg> FR · العربية · EN</span>
                <span class="flex items-center gap-2"><svg class="w-4 h-4 text-emerald-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-8 8a1 1 0 01-1.4 0l-4-4a1 1 0 011.4-1.4L8 12.6l7.3-7.3a1 1 0 011.4 0z" clip-rule="evenodd"/></svg> Données sécurisées</span>
            </div>
        </div>

        <div class="relative animate-float-slow">
            <div class="glass rounded-3xl p-5 shadow-2xl">
                <div class="flex items-center justify-between mb-5">
                    <div>
                        <p class="text-xs text-slate-500">Tableau de bord · École Démo</p>
                        <p class="text-white font-bold text-lg">Encaissements du mois</p>
                    </div>
                    <span class="flex items-center gap-1.5 text-xs text-emerald-400 font-semibold">
                        <span class="w-2 h-2 rounded-full bg-emerald-400"></span> Temps réel
                    </span>
                </div>
                <div class="flex items-end gap-2 h-40 mb-5">
                    @php $bars=[45,62,55,78,70,88,95,72,84,99]; @endphp
                    @foreach($bars as $i => $h)
                    <div class="flex-1 rounded-t-lg bar" style="height: {{ $h }}%; animation-delay: {{ $i*0.08 }}s; background: linear-gradient(180deg,#22d3ee,#6366f1);"></div>
                    @endforeach
                </div>
                <div class="grid grid-cols-3 gap-3">
                    <div class="rounded-xl bg-white/5 p-3"><p class="text-slate-500 text-[11px]">Recouvré</p><p class="text-white font-bold">4.8M <span class="text-[10px] text-slate-500">DJF</span></p></div>
                    <div class="rounded-xl bg-white/5 p-3"><p class="text-slate-500 text-[11px]">Taux</p><p class="text-emerald-400 font-bold">92%</p></div>
                    <div class="rounded-xl bg-white/5 p-3"><p class="text-slate-500 text-[11px]">En retard</p><p class="text-amber-400 font-bold">7</p></div>
                </div>
            </div>
            <div class="absolute -bottom-8 -left-6 sm:-left-10 w-60 glass rounded-2xl p-4 animate-float shadow-2xl">
                <div class="flex items-center gap-2 mb-2">
                    <span class="w-7 h-7 rounded-full bg-emerald-500 flex items-center justify-center text-white text-xs font-bold">WA</span>
                    <p class="text-xs text-slate-300 font-semibold">Reçu envoyé</p>
                </div>
                <p class="text-[11px] text-slate-400 leading-relaxed">✅ Paiement confirmé — 25 000 DJF. Reçu PDF joint. Merci !</p>
            </div>
            <div class="absolute -top-6 -right-4 sm:-right-8 w-52 glass rounded-2xl p-4 animate-float-slow shadow-2xl">
                <p class="text-[11px] text-amber-300 font-semibold mb-1">⏰ Rappel automatique</p>
                <p class="text-[11px] text-slate-400 leading-relaxed">Prochaine échéance dans 3 jours — parent notifié par Email + WhatsApp.</p>
            </div>
        </div>
    </div>
</section>

{{-- ══════════════════ PAYMENT PARTNERS TICKER ══════════════════ --}}
<section class="py-12 border-y border-white/5 bg-ink">
    <p class="text-center text-xs font-semibold uppercase tracking-[0.25em] text-slate-500 mb-8">
        Paiement en ligne des factures — intégré nativement
    </p>
    <div class="marquee-track overflow-hidden">
        <div class="marquee">
            @php $pays=['D-Money','Waafi','CAC Pay','Exim Pay','D-Money','Waafi','CAC Pay','Exim Pay']; @endphp
            @foreach($pays as $p)
            <div class="flex items-center gap-3 text-2xl font-extrabold text-slate-400 whitespace-nowrap">
                <span class="w-9 h-9 rounded-lg bg-gradient-to-br from-indigo-500/30 to-cyan-500/30 flex items-center justify-center text-base">💳</span>
                {{ $p }}
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ══════════════════ PROBLEM vs SOLUTION ══════════════════ --}}
<section class="py-28 relative">
    <div class="max-w-7xl mx-auto px-5">
        <div class="text-center max-w-2xl mx-auto mb-16 reveal">
            <p class="text-sm font-semibold uppercase tracking-widest grad-text mb-3">Le changement</p>
            <h2 class="text-3xl sm:text-4xl font-extrabold text-white">Les systèmes classiques vous ralentissent.<br>ScolApp vous fait avancer.</h2>
        </div>

        <div class="grid md:grid-cols-2 gap-6">
            <div class="reveal-left rounded-3xl p-8 border border-red-500/15" style="background: linear-gradient(180deg, rgba(239,68,68,0.06), transparent);">
                <div class="flex items-center gap-3 mb-6">
                    <span class="w-10 h-10 rounded-xl bg-red-500/15 flex items-center justify-center text-xl">📁</span>
                    <h3 class="text-white font-bold text-lg">Sans ScolApp</h3>
                </div>
                <ul class="space-y-4 text-sm text-slate-400">
                    @foreach([
                        'Registres papier &amp; fichiers Excel dispersés, souvent perdus',
                        'Parents jamais informés à temps des notes, absences et factures',
                        'Encaissements suivis à la main → erreurs et retards de recouvrement',
                        'Réconciliation bancaire manuelle, longue et source d\'erreurs',
                        'Aucune relance automatique → beaucoup d\'impayés',
                        'Rapports figés, produits en fin de trimestre dans la douleur',
                    ] as $x)
                    <li class="flex gap-3"><span class="text-red-400 mt-0.5">✕</span><span>{!! $x !!}</span></li>
                    @endforeach
                </ul>
            </div>
            <div class="reveal-right rounded-3xl p-8 border border-emerald-500/20" style="background: linear-gradient(180deg, rgba(16,185,129,0.08), transparent);">
                <div class="flex items-center gap-3 mb-6">
                    <span class="w-10 h-10 rounded-xl bg-emerald-500/15 flex items-center justify-center text-xl">⚡</span>
                    <h3 class="text-white font-bold text-lg">Avec ScolApp</h3>
                </div>
                <ul class="space-y-4 text-sm text-slate-300">
                    @foreach([
                        'Zéro papier — tout est numérique, factures &amp; reçus en PDF',
                        'Parents notifiés instantanément par Email + WhatsApp',
                        'Suivi des encaissements en temps réel, par mode de paiement',
                        'Réconciliation automatique des paiements et des factures',
                        'Relances et rappels d\'échéance envoyés automatiquement',
                        'Rapports dynamiques, à jour à la seconde, exportables PDF/Excel',
                    ] as $x)
                    <li class="flex gap-3"><span class="text-emerald-400 mt-0.5">✓</span><span>{!! $x !!}</span></li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</section>

{{-- ══════════════════ BLOC 1 · ACADÉMIQUE ══════════════════ --}}
<section id="academique" class="py-28 relative" style="background: linear-gradient(180deg,#070b18,#0b1024);">
    <div class="max-w-7xl mx-auto px-5">
        <div class="flex items-end gap-5 mb-14 reveal">
            <span class="chapter-num">01</span>
            <div>
                <p class="text-sm font-semibold uppercase tracking-widest grad-text mb-2">Bloc académique</p>
                <h2 class="text-3xl sm:text-4xl font-extrabold text-white">Toute la vie scolaire, en ligne</h2>
                <p class="mt-3 text-slate-400 max-w-2xl">De la maternelle au lycée : notes, bulletins, présences et emplois du temps — saisis en ligne, à jour en temps réel.</p>
            </div>
        </div>

        {{-- Infographic: central hub with numbered branches (responsive) --}}
        @php
            $acadLeft = [
                ['01','📝','amber','Bulletins en ligne','Saisie des notes en ligne par les enseignants — bulletins générés et publiés automatiquement.'],
                ['02','🗓️','rose','Présences élèves &amp; enseignants','Appel numérique quotidien des élèves et des enseignants, en temps réel.'],
                ['03','🔔','emerald','Alerte absence aux parents','Toute absence notifie instantanément le parent concerné par Email + WhatsApp.'],
            ];
            $acadRight = [
                ['04','⏱️','teal','Emplois du temps','Planning clair par classe, enseignant et salle — sans conflit d\'horaire.'],
                ['05','🧪','sky','Évaluations &amp; notes','Devoirs, quiz, examens et moyennes centralisés par matière.'],
                ['06','🎓','violet','Inscriptions &amp; dossiers','Inscriptions, réinscriptions et dossiers élèves reliés aux parents et aux classes.'],
            ];
        @endphp

        <div class="grid lg:grid-cols-[1fr_auto_1fr] gap-x-6 gap-y-8 items-center">

            {{-- Left branch --}}
            <div class="space-y-8 lg:space-y-12 order-2 lg:order-1">
                @foreach($acadLeft as $i => $f)
                <div class="reveal-left flex items-center gap-4 lg:justify-end" style="transition-delay: {{ $i*80 }}ms">
                    <div class="order-2 lg:order-1 lg:text-right">
                        <h3 class="text-white font-bold text-sm sm:text-base flex items-center gap-2 lg:justify-end">
                            <span class="text-lg lg:hidden">{!! $f[1] !!}</span>{!! $f[3] !!}<span class="hidden lg:inline text-lg">{!! $f[1] !!}</span>
                        </h3>
                        <p class="text-xs sm:text-sm text-slate-400 mt-1 leading-relaxed">{!! $f[4] !!}</p>
                    </div>
                    <span class="hidden lg:block order-2 w-9 border-t border-dashed border-white/20"></span>
                    <div class="order-1 lg:order-3 w-14 h-14 rounded-full bg-white/5 ring-2 ring-{{ $f[2] }}-400/50 text-{{ $f[2] }}-300 flex items-center justify-center font-black shrink-0 card-lift">{{ $f[0] }}</div>
                </div>
                @endforeach
            </div>

            {{-- Central hub --}}
            <div class="flex justify-center order-1 lg:order-2 lg:px-4">
                <div class="relative">
                    <span class="absolute inset-0 rounded-full ring-2 ring-indigo-400/40 animate-pulse-ring"></span>
                    <div class="relative w-28 h-28 lg:w-36 lg:h-36 rounded-full glass flex flex-col items-center justify-center shadow-2xl">
                        <span class="text-4xl lg:text-5xl">🎓</span>
                        <span class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mt-1">Académique</span>
                    </div>
                </div>
            </div>

            {{-- Right branch --}}
            <div class="space-y-8 lg:space-y-12 order-3">
                @foreach($acadRight as $i => $f)
                <div class="reveal-right flex items-center gap-4" style="transition-delay: {{ $i*80 }}ms">
                    <div class="order-1 w-14 h-14 rounded-full bg-white/5 ring-2 ring-{{ $f[2] }}-400/50 text-{{ $f[2] }}-300 flex items-center justify-center font-black shrink-0 card-lift">{{ $f[0] }}</div>
                    <span class="hidden lg:block order-2 w-9 border-t border-dashed border-white/20"></span>
                    <div class="order-2 lg:order-3">
                        <h3 class="text-white font-bold text-sm sm:text-base flex items-center gap-2">
                            <span class="text-lg">{!! $f[1] !!}</span>{!! $f[3] !!}
                        </h3>
                        <p class="text-xs sm:text-sm text-slate-400 mt-1 leading-relaxed">{!! $f[4] !!}</p>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</section>

{{-- ══════════════════ BLOC 2 · FINANCE & RECOUVREMENT ══════════════════ --}}
<section id="finance" class="py-28 relative overflow-hidden" style="background:#070b18;">
    <div class="blob blob-2" style="opacity:.22;"></div>
    <div class="max-w-7xl mx-auto px-5 relative">
        <div class="flex items-end gap-5 mb-14 reveal">
            <span class="chapter-num">02</span>
            <div>
                <p class="text-sm font-semibold uppercase tracking-widest grad-text mb-2">Bloc finance</p>
                <h2 class="text-3xl sm:text-4xl font-extrabold text-white">Encaissez plus vite, réconciliez sans effort</h2>
                <p class="mt-3 text-slate-400 max-w-2xl">Paiement en ligne, suivi des encaissements, réconciliation et recouvrement — automatisés de bout en bout.</p>
            </div>
        </div>

        <div id="paiement" class="grid lg:grid-cols-2 gap-16 items-center">
            <div class="reveal-left">
                <ul class="space-y-4">
                    @foreach([
                        ['Paiement en ligne des factures','D-Money · Waafi · CAC Pay · Exim Pay, intégrés nativement.'],
                        ['Suivi des encaissements','Vue temps réel par jour, par mode et par caissier.'],
                        ['Réconciliation automatique','Chaque versement rapproché de la bonne facture, sans saisie.'],
                        ['Recouvrement intelligent','Impayés détectés, parents relancés automatiquement.'],
                    ] as $li)
                    <li class="flex gap-4">
                        <span class="w-8 h-8 rounded-lg bg-emerald-500/15 text-emerald-400 flex items-center justify-center shrink-0 mt-0.5">✓</span>
                        <div><p class="text-white font-semibold text-sm">{!! $li[0] !!}</p><p class="text-slate-400 text-sm">{!! $li[1] !!}</p></div>
                    </li>
                    @endforeach
                </ul>
            </div>

            <div class="reveal-right grid grid-cols-2 gap-4">
                @php $gw=[
                    ['D-Money','Mobile money','from-indigo-500 to-blue-500'],
                    ['Waafi','Portefeuille mobile','from-emerald-500 to-teal-500'],
                    ['CAC Pay','Paiement bancaire','from-cyan-500 to-sky-500'],
                    ['Exim Pay','Passerelle Exim','from-violet-500 to-fuchsia-500'],
                ]; @endphp
                @foreach($gw as $g)
                <div class="glass card-lift rounded-2xl p-6 text-center">
                    <div class="w-14 h-14 mx-auto rounded-2xl bg-gradient-to-br {{ $g[2] }} flex items-center justify-center text-white text-2xl mb-4 shadow-lg">💳</div>
                    <p class="text-white font-bold">{{ $g[0] }}</p>
                    <p class="text-xs text-slate-400 mt-1">{{ $g[1] }}</p>
                    <span class="inline-block mt-3 text-[11px] px-2.5 py-1 rounded-full bg-emerald-500/15 text-emerald-400 font-semibold">Intégré</span>
                </div>
                @endforeach
            </div>
        </div>

        {{-- modes de paiement acceptés --}}
        <div class="mt-14 reveal rounded-3xl glass p-8">
            <p class="text-center text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 mb-6">
                Tous les modes de paiement acceptés — enregistrés &amp; réconciliés automatiquement
            </p>
            <div class="flex flex-wrap justify-center gap-3">
                @php $methods=[
                    ['💵','Espèces','Encaissement au guichet'],
                    ['🌐','Paiement en ligne','D-Money · Waafi · CAC Pay · Exim Pay'],
                    ['🏦','Virement bancaire','Enregistré avec référence'],
                    ['🧾','Chèque','Suivi jusqu\'à l\'encaissement'],
                    ['📱','Mobile Money','Portefeuilles mobiles'],
                ]; @endphp
                @foreach($methods as $m)
                <div class="flex items-center gap-3 rounded-2xl bg-white/5 border border-white/8 px-4 py-3 card-lift">
                    <span class="text-2xl">{!! $m[0] !!}</span>
                    <div>
                        <p class="text-white font-semibold text-sm">{!! $m[1] !!}</p>
                        <p class="text-slate-400 text-xs">{!! $m[2] !!}</p>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        {{-- finance feature cards --}}
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5 mt-14">
            @php $finance=[
                ['🧾','Encaissement &amp; caisse','Guichet caissier dédié, reçus instantanés, rapport de caisse journalier par mode.'],
                ['📄','Factures &amp; reçus en PDF','Documents générés automatiquement, prêts à imprimer ou à envoyer.'],
                ['📊','Rapports financiers dynamiques','Revenus, impayés et dépenses en temps réel — exportables PDF &amp; Excel.'],
            ]; @endphp
            @foreach($finance as $i => $f)
            <div class="reveal glass card-lift rounded-2xl p-6" style="transition-delay: {{ $i*60 }}ms">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-emerald-500/25 to-cyan-500/20 flex items-center justify-center text-2xl mb-4">{!! $f[0] !!}</div>
                <h3 class="text-white font-bold mb-2">{!! $f[1] !!}</h3>
                <p class="text-sm text-slate-400 leading-relaxed">{!! $f[2] !!}</p>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ══════════════════ BLOC 3 · COMMUNICATION ══════════════════ --}}
<section id="communication" class="py-28" style="background: linear-gradient(180deg,#070b18,#0b1024);">
    <div class="max-w-7xl mx-auto px-5">
        <div class="flex items-end gap-5 mb-14 reveal">
            <span class="chapter-num">03</span>
            <div>
                <p class="text-sm font-semibold uppercase tracking-widest grad-text mb-2">Bloc communication</p>
                <h2 class="text-3xl sm:text-4xl font-extrabold text-white">Vos messages arrivent vraiment</h2>
                <p class="mt-3 text-slate-400 max-w-2xl">Email <strong class="text-slate-200">et</strong> WhatsApp, avec documents joints — et relances automatiques des retards.</p>
            </div>
        </div>

        <div id="notifications" class="grid lg:grid-cols-2 gap-16 items-center">
            <div class="reveal-left flex justify-center order-2 lg:order-1">
                <div class="w-72 rounded-[2.2rem] p-3 glass shadow-2xl">
                    <div class="rounded-[1.7rem] bg-[#0b141a] p-4 space-y-3 min-h-[420px]">
                        <div class="flex items-center gap-2 pb-3 border-b border-white/5">
                            <span class="w-8 h-8 rounded-full bg-emerald-500 flex items-center justify-center text-white text-xs font-bold">SA</span>
                            <div><p class="text-white text-sm font-semibold">ScolApp</p><p class="text-[10px] text-emerald-400">en ligne</p></div>
                        </div>
                        <div class="bg-emerald-600/90 text-white text-xs rounded-2xl rounded-tl-sm p-3 max-w-[85%]">✅ <b>Paiement confirmé</b><br>Reçu n° R-2048 — 25 000 DJF.<br>📎 recu-R-2048.pdf</div>
                        <div class="bg-white/10 text-slate-200 text-xs rounded-2xl rounded-tl-sm p-3 max-w-[85%]">📄 <b>Nouvelle facture</b><br>Scolarité — versement 2. Échéance : 15/07.<br>📎 facture-F-1187.pdf</div>
                        <div class="bg-indigo-500/20 text-indigo-100 text-xs rounded-2xl rounded-tl-sm p-3 max-w-[85%]">🔔 <b>Absence signalée</b><br>Votre enfant a été noté absent aujourd'hui (08:15).</div>
                        <div class="bg-amber-500/20 text-amber-100 text-xs rounded-2xl rounded-tl-sm p-3 max-w-[85%]">⏰ <b>Rappel</b> — échéance dans 3 jours. Payez en ligne en 1 clic.</div>
                        <div class="bg-red-500/20 text-red-100 text-xs rounded-2xl rounded-tl-sm p-3 max-w-[85%]">⚠️ <b>Facture en retard</b> — merci de régulariser.</div>
                    </div>
                </div>
            </div>

            <div class="reveal-right order-1 lg:order-2">
                <div class="grid sm:grid-cols-2 gap-4">
                    @foreach([
                        ['💬','Email + WhatsApp','Chaque notification part sur les deux canaux, automatiquement.'],
                        ['📎','Reçus &amp; factures en PDF','Envoyés directement sur le WhatsApp du parent.'],
                        ['⏰','Rappel du prochain paiement','Alerte avant chaque échéance, sans oubli.'],
                        ['⚠️','Alerte de retard','Les parents concernés sont prévenus automatiquement.'],
                        ['🔔','Alerte d\'absence','Le parent est notifié dès que l\'élève est marqué absent.'],
                        ['📢','Annonces &amp; messagerie','Communication école ↔ familles centralisée.'],
                    ] as $n)
                    <div class="glass card-lift rounded-2xl p-5">
                        <div class="text-2xl mb-2">{!! $n[0] !!}</div>
                        <p class="text-white font-semibold text-sm mb-1">{!! $n[1] !!}</p>
                        <p class="text-slate-400 text-sm">{!! $n[2] !!}</p>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ══════════════════ PORTALS ══════════════════ --}}
<section id="portals" class="py-28" style="background:#070b18;">
    <div class="max-w-7xl mx-auto px-5">
        <div class="text-center max-w-2xl mx-auto mb-16 reveal">
            <p class="text-sm font-semibold uppercase tracking-widest grad-text mb-3">Chacun son espace</p>
            <h2 class="text-3xl sm:text-4xl font-extrabold text-white">7 portails, une seule vérité</h2>
            <p class="mt-4 text-slate-400">Chaque utilisateur voit exactement ce dont il a besoin — ni plus, ni moins.</p>
        </div>

        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
            @php $portals=[
                ['⚙️','Plateforme','Gestion multi-écoles, abonnements &amp; facturation SaaS.','from-slate-500/20 to-indigo-500/20'],
                ['🏫','Direction / Admin','Contrôle total de l\'école : académique, finance, paramètres.','from-indigo-500/20 to-violet-500/20'],
                ['💰','Comptable','Facturation, paiements, barèmes, comptabilité complète.','from-emerald-500/20 to-teal-500/20'],
                ['🧾','Caissier','Encaissement au guichet, reçus PDF, rapport de caisse.','from-cyan-500/20 to-sky-500/20'],
                ['👨‍🏫','Enseignant','Emploi du temps, présences, évaluations et notes.','from-blue-500/20 to-indigo-500/20'],
                ['🧑‍✈️','Surveillant','Suivi des présences et de la vie scolaire en direct.','from-amber-500/20 to-orange-500/20'],
                ['👪','Parent','Notes, présences, factures &amp; paiement en ligne.','from-pink-500/20 to-rose-500/20'],
                ['🎒','Élève','Emploi du temps, notes, présences et annonces.','from-fuchsia-500/20 to-purple-500/20'],
            ]; @endphp
            @foreach($portals as $i => $p)
            <div class="reveal glass card-lift rounded-2xl p-6 relative overflow-hidden" style="transition-delay: {{ $i*50 }}ms">
                <div class="absolute -top-10 -right-10 w-28 h-28 rounded-full bg-gradient-to-br {{ $p[3] }} blur-2xl"></div>
                <div class="relative">
                    <div class="text-3xl mb-3">{!! $p[0] !!}</div>
                    <h3 class="text-white font-bold mb-1.5">{!! $p[1] !!}</h3>
                    <p class="text-sm text-slate-400 leading-relaxed">{!! $p[2] !!}</p>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ══════════════════ MANAGEMENT MODULES ══════════════════ --}}
<section class="py-28 relative overflow-hidden" style="background: linear-gradient(180deg,#070b18,#0b1024);">
    <div class="blob blob-3" style="opacity:.18;"></div>
    <div class="max-w-6xl mx-auto px-5 relative">
        <div class="text-center max-w-2xl mx-auto mb-14 reveal">
            <p class="text-sm font-semibold uppercase tracking-widest grad-text mb-3">Une suite complète</p>
            <h2 class="text-3xl sm:text-4xl font-extrabold text-white">Des dizaines de modules de gestion</h2>
            <p class="mt-4 text-slate-400">Un menu pour chaque besoin de l'école — de la maternelle au lycée, de l'inscription à la comptabilité.</p>
        </div>

        <div class="reveal flex flex-wrap justify-center gap-2.5">
            @php $modules=[
                '🎓 Académique','🗂️ Cycles','📚 Niveaux','🏛️ Classes','📘 Matières','🚪 Salles',
                '👧 Élèves','👪 Responsables','👨‍🏫 Enseignants','📝 Inscriptions',
                '🗓️ Présences','⏱️ Emploi du temps','🧪 Évaluations','📄 Bulletins',
                '🧾 Factures','💳 Paiements','📐 Barèmes de frais','💸 Dépenses','📒 Comptabilité',
                '📢 Annonces','💬 Messagerie','📊 Rapports','⏰ Tâches planifiées',
                '🏫 Paramètres école','🔐 Utilisateurs & rôles','📱 Facturation D-Money',
            ]; @endphp
            @foreach($modules as $m)
            <span class="glass card-lift rounded-full px-4 py-2 text-sm text-slate-200 whitespace-nowrap">{!! $m !!}</span>
            @endforeach
        </div>

        <p class="text-center text-slate-500 text-sm mt-8 reveal">…et bien plus encore, dans une interface unique.</p>
    </div>
</section>

{{-- ══════════════════ STATS ══════════════════ --}}
<section class="py-20 border-y border-white/5" style="background:#070b18;">
    <div class="max-w-6xl mx-auto px-5 grid grid-cols-2 lg:grid-cols-4 gap-8 text-center">
        @php $stats=[['c'=>'99','s'=>'%','l'=>'Moins de paperasse'],['c'=>'2','s'=>' canaux','l'=>'Email + WhatsApp'],['c'=>'4','s'=>'','l'=>'Solutions de paiement'],['c'=>'7','s'=>'','l'=>'Portails dédiés']]; @endphp
        @foreach($stats as $s)
        <div class="reveal">
            <p class="text-4xl sm:text-5xl font-extrabold grad-text counter" data-count="{{ $s['c'] }}" data-suffix="{{ $s['s'] }}">0</p>
            <p class="mt-2 text-sm text-slate-400">{{ $s['l'] }}</p>
        </div>
        @endforeach
    </div>
</section>

{{-- ══════════════════ INTÉGRATIONS & API ══════════════════ --}}
<section id="integrations" class="py-28 relative overflow-hidden" style="background: linear-gradient(180deg,#070b18,#0b1024);">
    <div class="blob blob-2" style="opacity:.18;"></div>
    <div class="max-w-7xl mx-auto px-5 relative">
        <div class="text-center max-w-2xl mx-auto mb-16 reveal">
            <p class="text-sm font-semibold uppercase tracking-widest grad-text mb-3">Ouvert &amp; connecté</p>
            <h2 class="text-3xl sm:text-4xl font-extrabold text-white">Des intégrations tierces, quand vous en avez besoin</h2>
            <p class="mt-4 text-slate-400">ScolApp expose une API sécurisée et se connecte à vos services externes — paiement, messagerie, comptabilité et plus.</p>
        </div>

        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
            @php $integrations=[
                ['🔌','API REST sécurisée','Accédez à vos données depuis vos propres outils via des jetons d\'accès (tokens).'],
                ['💳','Passerelles de paiement','D-Money, Waafi, CAC Pay et Exim Pay intégrés — prêts à encaisser en ligne.'],
                ['💬','Passerelle WhatsApp','Notifications automatiques (reçus, factures, alertes) via l\'API WhatsApp.'],
                ['✉️','Email / SMTP','Envoi transactionnel fiable : reçus, bulletins, relances et confirmations.'],
                ['🔔','Webhooks','Recevez les événements (paiement, inscription, absence) en temps réel dans vos systèmes.'],
                ['🧩','Intégrations sur-mesure','Comptabilité, SMS, ERP ou tout autre service tiers — connectés à la demande.'],
            ]; @endphp
            @foreach($integrations as $i => $f)
            <div class="reveal glass card-lift rounded-2xl p-6" style="transition-delay: {{ $i*60 }}ms">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-violet-500/25 to-cyan-500/20 flex items-center justify-center text-2xl mb-4">{!! $f[0] !!}</div>
                <h3 class="text-white font-bold mb-2">{!! $f[1] !!}</h3>
                <p class="text-sm text-slate-400 leading-relaxed">{!! $f[2] !!}</p>
            </div>
            @endforeach
        </div>

        <div class="mt-10 text-center reveal">
            <span class="inline-flex items-center gap-2 text-sm text-slate-400 glass rounded-full px-5 py-2.5">
                <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                API &amp; intégrations disponibles sur demande — adaptées à votre établissement.
            </span>
        </div>
    </div>
</section>

{{-- ══════════════════ PRICING ══════════════════ --}}
<section id="tarifs" class="py-28" style="background: linear-gradient(180deg,#070b18,#0b1024);">
    <div class="max-w-6xl mx-auto px-5">
        <div class="text-center max-w-2xl mx-auto mb-16 reveal">
            <p class="text-sm font-semibold uppercase tracking-widest grad-text mb-3">Tarifs</p>
            <h2 class="text-3xl sm:text-4xl font-extrabold text-white">Un plan pour chaque établissement</h2>
            <p class="mt-4 text-slate-400">Payez selon votre taille. Sans engagement, sans frais cachés.</p>
        </div>
        <div class="grid md:grid-cols-3 gap-6 items-stretch">
            @php $plans=[
                ['Starter','Petites écoles','Gestion académique complète','Présences &amp; bulletins','Notifications Email','Support standard', false],
                ['Pro','Le plus populaire','Tout Starter, plus :','Paiement en ligne (4 solutions)','WhatsApp + réconciliation','Rapports dynamiques &amp; recouvrement', true],
                ['Réseau','Groupes d\'écoles','Tout Pro, plus :','Multi-écoles &amp; multi-devises','Console SaaS centralisée','Accompagnement dédié', false],
            ]; @endphp
            @foreach($plans as $pl)
            <div class="reveal rounded-3xl p-8 relative {{ $pl[6] ? 'border-2 border-indigo-500' : 'border border-white/8' }}"
                 style="background: {{ $pl[6] ? 'linear-gradient(180deg, rgba(79,70,229,0.14), rgba(6,182,212,0.05))' : 'rgba(255,255,255,0.03)' }};">
                @if($pl[6])<span class="absolute -top-3 left-1/2 -translate-x-1/2 btn-glow text-white text-[11px] font-bold px-3 py-1 rounded-full">POPULAIRE</span>@endif
                <h3 class="text-white font-extrabold text-xl">{!! $pl[0] !!}</h3>
                <p class="text-sm text-slate-400 mt-1">{!! $pl[1] !!}</p>
                <div class="my-6"><span class="text-slate-400 text-sm">à partir de</span><div class="text-3xl font-extrabold text-white">Sur devis</div></div>
                <ul class="space-y-3 text-sm text-slate-300 mb-8">
                    @foreach(array_slice($pl,2,4) as $feat)
                    <li class="flex gap-2.5"><span class="text-emerald-400">✓</span><span>{!! $feat !!}</span></li>
                    @endforeach
                </ul>
                <a href="#contact" class="block text-center py-3 rounded-xl font-semibold {{ $pl[6] ? 'btn-glow text-white' : 'border border-white/12 text-white hover:bg-white/5' }} transition">Demander un devis</a>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ══════════════════ FAQ ══════════════════ --}}
<section id="faq" class="py-28 relative overflow-hidden" style="background:#070b18;">
    <div class="blob blob-3" style="opacity:.15;"></div>
    <div class="max-w-3xl mx-auto px-5 relative">
        <div class="text-center mb-14 reveal">
            <p class="text-sm font-semibold uppercase tracking-widest grad-text mb-3">Questions fréquentes</p>
            <h2 class="text-3xl sm:text-4xl font-extrabold text-white">Tout ce que vous devez savoir</h2>
            <p class="mt-4 text-slate-400">Les réponses aux questions que se posent les directeurs d'école.</p>
        </div>

        <div class="space-y-3">
            @php $faqs=[
                ['Faut-il installer un logiciel ou du matériel ?','Non. ScolApp est 100% en ligne — accessible depuis n\'importe quel navigateur, ordinateur ou téléphone. Aucune installation, aucune maintenance de votre côté.'],
                ['Comment les parents paient-ils la scolarité ?','En ligne en un clic (D-Money, Waafi, CAC Pay, Exim Pay) ou au guichet (espèces, virement, chèque, mobile money). Chaque paiement génère un reçu PDF envoyé par Email et WhatsApp.'],
                ['Les notifications WhatsApp sont-elles vraiment automatiques ?','Oui. Absences, factures, reçus, rappels d\'échéance et relances d\'impayés partent automatiquement — par Email et WhatsApp — sans aucune action manuelle.'],
                ['L\'application est-elle disponible en arabe ?','Oui : interface complète en français, arabe (affichage de droite à gauche) et anglais. Chaque utilisateur choisit sa langue.'],
                ['Mes données scolaires et financières sont-elles sécurisées ?','Absolument. Accès par rôles (chacun ne voit que ce qui le concerne), connexions chiffrées (HTTPS) et journal d\'audit de chaque action sensible.'],
                ['Combien de temps pour démarrer ?','Quelques jours. Nous configurons vos cycles, classes, frais et utilisateurs, puis vos équipes et parents reçoivent leurs accès.'],
                ['Peut-on gérer plusieurs écoles ?','Oui. La console multi-écoles pilote plusieurs établissements, avec abonnements et facturation centralisés — idéal pour les groupes scolaires.'],
                ['Peut-on connecter nos outils existants ?','Oui. ScolApp expose une API REST sécurisée et des webhooks, et s\'intègre aux passerelles de paiement, WhatsApp, email et services tiers à la demande.'],
            ]; @endphp
            @foreach($faqs as $i => $q)
            <div x-data="{ open: {{ $i === 0 ? 'true' : 'false' }} }"
                 class="reveal glass rounded-2xl overflow-hidden transition-colors"
                 :class="open ? 'border-indigo-500/40' : ''">
                <button type="button" @click="open = !open"
                        class="w-full flex items-center justify-between gap-4 p-5 sm:p-6 text-left">
                    <span class="text-white font-semibold text-sm sm:text-base">{!! $q[0] !!}</span>
                    <span class="w-8 h-8 rounded-full flex items-center justify-center shrink-0 transition-all duration-300"
                          :class="open ? 'btn-glow rotate-45' : 'bg-white/8'">
                        <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-width="2.5" d="M12 5v14M5 12h14"/>
                        </svg>
                    </span>
                </button>
                <div x-show="open" x-collapse.duration.300ms
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="opacity-0 -translate-y-2"
                     x-transition:enter-end="opacity-100 translate-y-0">
                    <p class="px-5 sm:px-6 pb-6 -mt-1 text-slate-400 text-sm leading-relaxed">{!! $q[1] !!}</p>
                </div>
            </div>
            @endforeach
        </div>

        <div class="text-center mt-12 reveal">
            <p class="text-slate-400 text-sm mb-4">Une autre question ? Nous y répondons en direct.</p>
            <a href="#contact" class="btn-glow inline-block text-white font-semibold px-7 py-3.5 rounded-xl">Poser ma question</a>
        </div>
    </div>
</section>

{{-- ══════════════════ CONTACT ══════════════════ --}}
<section id="contact" class="py-28 relative overflow-hidden" style="background:#070b18;">
    <div class="blob blob-1" style="opacity:.2;"></div>
    <div class="max-w-6xl mx-auto px-5 relative grid lg:grid-cols-2 gap-16 items-center">
        <div class="reveal-left">
            <p class="text-sm font-semibold uppercase tracking-widest grad-text mb-3">Parlons-en</p>
            <h2 class="text-3xl sm:text-4xl font-extrabold text-white leading-tight">Prêt à digitaliser votre école ?</h2>
            <p class="mt-5 text-slate-400 leading-relaxed">Demandez une démonstration gratuite. Nous vous montrons ScolApp en action, adapté à votre établissement.</p>
            <div class="mt-8 space-y-5">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center text-indigo-400">✉️</div>
                    <div><p class="text-xs text-slate-500 uppercase tracking-widest">Email</p><p class="text-white font-semibold">contact@scolapp.com</p></div>
                </div>
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400">💬</div>
                    <div><p class="text-xs text-slate-500 uppercase tracking-widest">WhatsApp</p><p class="text-white font-semibold">Réponse rapide, 7j/7</p></div>
                </div>
            </div>
        </div>

        <div class="reveal-right">
            <div class="rounded-3xl p-8 lg:p-10 glass">
                <h3 class="text-xl font-bold text-white mb-8">Envoyer un message</h3>
                <form @submit.prevent="submitContact()" class="space-y-5">
                    <div class="grid sm:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 uppercase tracking-widest mb-2">Nom complet</label>
                            <input type="text" x-model="contact.name" required class="form-input w-full rounded-xl px-4 py-3.5 text-sm" placeholder="Votre nom">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 uppercase tracking-widest mb-2">Email</label>
                            <input type="email" x-model="contact.email" required class="form-input w-full rounded-xl px-4 py-3.5 text-sm" placeholder="email@ecole.dj">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 uppercase tracking-widest mb-2">Établissement</label>
                        <input type="text" x-model="contact.school" class="form-input w-full rounded-xl px-4 py-3.5 text-sm" placeholder="Nom de votre école">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 uppercase tracking-widest mb-2">Téléphone</label>
                        <input type="tel" x-model="contact.phone" class="form-input w-full rounded-xl px-4 py-3.5 text-sm" placeholder="+253 77...">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 uppercase tracking-widest mb-2">Message</label>
                        <textarea x-model="contact.message" required rows="4" class="form-input w-full rounded-xl px-4 py-3.5 text-sm resize-none" placeholder="Décrivez votre projet..."></textarea>
                    </div>
                    <button type="submit" :disabled="contactSent || contactLoading"
                            class="w-full py-4 rounded-xl font-semibold text-white btn-glow flex items-center justify-center gap-2">
                        <template x-if="contactLoading"><svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z"/></svg></template>
                        <template x-if="!contactLoading && !contactSent"><span>Envoyer le message</span></template>
                        <template x-if="contactSent && !contactLoading"><span>✅ Message envoyé !</span></template>
                    </button>
                    <p x-show="contactSent" x-cloak class="text-center text-emerald-400 text-sm">Merci ! Un email de confirmation vous a été envoyé.</p>
                    <p x-show="contactError" x-cloak x-text="contactError" class="text-center text-red-400 text-sm"></p>
                </form>
            </div>
        </div>
    </div>
</section>

{{-- ══════════════════ CTA BANNER ══════════════════ --}}
<section class="py-20 relative overflow-hidden" style="background: linear-gradient(120deg,#312e81,#4c1d95 55%,#0e7490);">
    <div class="absolute inset-0 grid-overlay opacity-40"></div>
    <div class="max-w-4xl mx-auto px-5 text-center relative reveal">
        <h2 class="text-3xl sm:text-4xl font-extrabold text-white">Fini le papier. Bienvenue à l'école connectée.</h2>
        <p class="mt-4 text-indigo-100/80 max-w-2xl mx-auto">Rejoignez les établissements qui gèrent tout — académique, finance et communication — depuis une seule plateforme.</p>
        <div class="mt-8 flex flex-col sm:flex-row gap-4 justify-center">
            <a href="#contact" class="bg-white text-indigo-700 font-bold px-8 py-4 rounded-xl hover:bg-indigo-50 transition">Demander une démo</a>
            <a href="{{ route('login') }}" class="border border-white/40 text-white font-bold px-8 py-4 rounded-xl hover:bg-white/10 transition">Se connecter</a>
        </div>
    </div>
</section>

{{-- ══════════════════ FOOTER ══════════════════ --}}
<footer class="pt-16 pb-10 border-t border-white/5" style="background:#070b18;">
    <div class="max-w-7xl mx-auto px-5">
        <div class="grid md:grid-cols-4 gap-10 mb-12">
            <div class="md:col-span-1">
                <div class="flex items-center gap-2.5 mb-4">
                    <img src="{{ asset('images/logo_ScolApp.png') }}" alt="ScolApp" class="h-8 w-8 object-contain">
                    <span class="text-white font-extrabold text-lg">Scol<span class="grad-text">App</span></span>
                </div>
                <p class="text-sm text-slate-500 leading-relaxed">La plateforme de gestion scolaire nouvelle génération, pensée pour Djibouti et l'Afrique.</p>
            </div>
            <div>
                <p class="text-white font-semibold text-sm mb-4">Blocs</p>
                <ul class="space-y-2.5 text-sm text-slate-400">
                    <li><a href="#academique" class="hover:text-white transition">Académique</a></li>
                    <li><a href="#finance" class="hover:text-white transition">Finance</a></li>
                    <li><a href="#communication" class="hover:text-white transition">Communication</a></li>
                    <li><a href="#integrations" class="hover:text-white transition">Intégrations &amp; API</a></li>
                    <li><a href="#portals" class="hover:text-white transition">Portails</a></li>
                </ul>
            </div>
            <div>
                <p class="text-white font-semibold text-sm mb-4">Solutions de paiement</p>
                <ul class="space-y-2.5 text-sm text-slate-400">
                    <li>D-Money</li><li>Waafi</li><li>CAC Pay</li><li>Exim Pay</li>
                </ul>
            </div>
            <div>
                <p class="text-white font-semibold text-sm mb-4">Compte</p>
                <ul class="space-y-2.5 text-sm text-slate-400">
                    <li><a href="{{ route('login') }}" class="hover:text-white transition">Se connecter</a></li>
                    <li><a href="#contact" class="hover:text-white transition">Demander une démo</a></li>
                </ul>
            </div>
        </div>
        <div class="pt-8 border-t border-white/5 flex flex-col sm:flex-row justify-between items-center gap-4 text-sm text-slate-500">
            <p>© {{ date('Y') }} ScolApp. Tous droits réservés.</p>
            <p class="flex items-center gap-2">Fait avec <span class="text-red-400">♥</span> pour les écoles.</p>
        </div>
    </div>
</footer>

<script>
    function site() {
        return {
            scrolled: false,
            mobileOpen: false,
            rotw: 'digitale',
            _words: ['digitale','en temps réel','automatisée','sans effort','connectée'],
            _wi: 0,
            contact: { name: '', email: '', school: '', phone: '', message: '' },
            contactLoading: false,
            contactSent: false,
            contactError: '',

            init() {
                const onScroll = () => { this.scrolled = window.scrollY > 30; };
                window.addEventListener('scroll', onScroll, { passive: true });
                onScroll();

                setInterval(() => {
                    this._wi = (this._wi + 1) % this._words.length;
                    this.rotw = this._words[this._wi];
                }, 2400);

                const io = new IntersectionObserver((entries) => {
                    entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); } });
                }, { threshold: 0.12, rootMargin: '0px 0px -60px 0px' });
                document.querySelectorAll('.reveal, .reveal-left, .reveal-right').forEach(el => io.observe(el));

                const cio = new IntersectionObserver((entries) => {
                    entries.forEach(e => {
                        if (!e.isIntersecting) return;
                        const el = e.target;
                        const target = parseInt(el.dataset.count, 10) || 0;
                        const suffix = el.dataset.suffix || '';
                        let cur = 0;
                        const step = Math.max(1, Math.ceil(target / 40));
                        const t = setInterval(() => {
                            cur += step;
                            if (cur >= target) { cur = target; clearInterval(t); }
                            el.textContent = cur + suffix;
                        }, 28);
                        cio.unobserve(el);
                    });
                }, { threshold: 0.5 });
                document.querySelectorAll('.counter').forEach(el => cio.observe(el));
            },

            async submitContact() {
                this.contactLoading = true;
                this.contactError = '';
                const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                try {
                    const res = await fetch('{{ route("contact.store") }}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                        body: JSON.stringify(this.contact),
                    });
                    const data = await res.json();
                    if (res.ok) {
                        this.contactSent = true;
                        this.contact = { name: '', email: '', school: '', phone: '', message: '' };
                    } else {
                        const errors = data.errors ? Object.values(data.errors).flat() : [];
                        this.contactError = errors[0] || data.message || 'Une erreur est survenue.';
                    }
                } catch (e) {
                    this.contactError = "Impossible d'envoyer le message. Veuillez réessayer.";
                } finally {
                    this.contactLoading = false;
                }
            }
        };
    }
</script>
</body>
</html>
