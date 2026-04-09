<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light"
      dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'ScolApp SMS') }} - @yield('title', 'Dashboard')</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700&display=swap" rel="stylesheet"/>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles

    {{-- Flatpickr date picker --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://npmcdn.com/flatpickr/dist/l10n/fr.js"></script>
    @stack('head-styles')
</head>
<body class="min-h-screen font-sans antialiased bg-base-200 overflow-x-hidden">

{{-- ── Navbar (outside x-main so it always renders) ── --}}
<x-nav sticky full-width class="z-30">
    <x-slot:brand>
        <label for="main-drawer" class="lg:hidden cursor-pointer mr-2 p-1 rounded-lg hover:bg-base-200">
            <x-icon name="o-bars-3" class="w-6 h-6" />
        </label>
        <img src="{{ asset('images/logo_ScolApp.png') }}" alt="ScolApp" class="h-8 object-contain">
    </x-slot:brand>

    <x-slot:actions>
        {{-- Language switcher --}}
        <x-dropdown label="{{ strtoupper(app()->getLocale()) }}" icon="o-language" class="btn-ghost btn-sm" right>
            <x-menu-item title="Français" link="{{ route('locale.switch', 'fr') }}" no-wire-navigate />
            <x-menu-item title="English"  link="{{ route('locale.switch', 'en') }}" no-wire-navigate />
            <x-menu-item title="العربية"  link="{{ route('locale.switch', 'ar') }}" no-wire-navigate />
        </x-dropdown>

        {{-- Notifications bell --}}
        <x-dropdown class="btn-ghost btn-sm" icon="o-bell" right>
            <x-menu-item title="{{ __('navigation.profile') }}" link="{{ route('profile.show') }}" />
        </x-dropdown>

        {{-- Theme toggle --}}
        <x-theme-toggle class="btn-ghost btn-sm" />

        <div class="flex items-center gap-2 pl-2 border-l border-base-200">
            <div class="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center shrink-0">
                <span class="text-primary font-bold text-sm">{{ substr(auth()->user()?->name ?? 'A', 0, 1) }}</span>
            </div>
            <span class="text-sm font-medium hidden lg:block truncate max-w-28">{{ auth()->user()?->name }}</span>
        </div>
    </x-slot:actions>
</x-nav>

{{-- ── Main layout with sidebar ── --}}
<x-main full-width with-nav>
    {{-- ── Sidebar ── --}}
    <x-slot:sidebar drawer="main-drawer" collapsible class="bg-base-100 lg:bg-base-200">

        {{-- School Logo + Name --}}
        <div class="flex items-center gap-3 p-4 mb-2">
            @if(auth()->user()?->school?->logo)
                <img src="{{ auth()->user()->school->logo_url }}" alt="logo" class="w-10 h-10 rounded-full object-cover shrink-0">
            @else
                <img src="{{ asset('images/logo_ScolApp.png') }}" alt="ScolApp" class="w-10 h-10 rounded-full object-contain shrink-0">
            @endif
            <div class="min-w-0">
                <p class="font-bold text-sm truncate">{{ auth()->user()?->school?->name ?? config('app.name') }}</p>
                <p class="text-xs text-base-content/60 truncate">{{ auth()->user()?->full_name }}</p>
            </div>
        </div>

        <x-menu activate-by-route>
            {{-- Dashboard --}}
            <x-menu-item :title="__('navigation.dashboard')" icon="o-squares-2x2" link="{{ route('admin.dashboard') }}" />

            {{-- Academic --}}
            @canany(['students.view','guardians.view','teachers.view','academic.view','enrollments.view','attendance.view','timetable.view','assessments.view','report-cards.view'])
            <x-menu-sub :title="__('navigation.academic')" icon="o-academic-cap">
                @can('students.view')
                <x-menu-item :title="__('navigation.students')"     icon="o-user-group"                :link="route('admin.students.index')" />
                @endcan
                @can('guardians.view')
                <x-menu-item title="Responsables"                   icon="o-users"                     :link="route('admin.guardians.index')" />
                @endcan
                @can('teachers.view')
                <x-menu-item :title="__('navigation.teachers')"     icon="o-briefcase"                :link="route('admin.teachers.index')" />
                @endcan
                @can('academic.view')
                <x-menu-item :title="__('navigation.classes')"      icon="o-building-office"           :link="route('admin.academic.classes')" />
                @endcan
                @can('enrollments.view')
                <x-menu-item :title="__('navigation.enrollments')"  icon="o-clipboard-document-check"  :link="route('admin.enrollments.index')" />
                @endcan
                @can('attendance.view')
                <x-menu-item :title="__('navigation.attendance')"   icon="o-calendar-days"             :link="route('admin.attendance.index')" />
                @endcan
                @can('timetable.view')
                <x-menu-item title="Emplois du temps"               icon="o-table-cells"               :link="route('admin.timetable.index')" />
                @endcan
                @can('assessments.view')
                <x-menu-item :title="__('navigation.assessments')"  icon="o-pencil-square"             :link="route('admin.assessments.index')" />
                @endcan
                @can('report-cards.view')
                <x-menu-item :title="__('navigation.report_cards')" icon="o-document-text"             :link="route('admin.report-cards.index')" />
                @endcan
            </x-menu-sub>
            @endcanany

            {{-- Finance --}}
            @canany(['invoices.view','payments.view','fee-schedules.view','billing.view'])
            <x-menu-sub :title="__('navigation.finance')" icon="o-banknotes">
                @can('invoices.view')
                <x-menu-item :title="__('navigation.invoices')"      icon="o-document-currency-dollar" :link="route('admin.finance.invoices.index')" />
                @endcan
                @can('payments.view')
                <x-menu-item :title="__('navigation.payments')"      icon="o-credit-card"              :link="route('admin.finance.payments.index')" />
                <x-menu-item title="Suivi des encaissements"          icon="o-chart-bar-square"         :link="route('admin.finance.payments.suivi')" />
                @endcan
                @can('fee-schedules.view')
                <x-menu-item :title="__('navigation.fee_schedules')" icon="o-table-cells"              :link="route('admin.finance.fee-schedules.index')" />
                @endcan
                @can('payments.view')
                <x-menu-item title="Dépenses"                        icon="o-arrow-trending-down"       :link="route('admin.finance.expenses.index')" />
                <x-menu-item title="Comptabilité"                    icon="o-calculator"                :link="route('admin.finance.comptabilite.index')" />
                @endcan
                @can('billing.view')
                <x-menu-item title="Transactions D-Money"            icon="o-device-phone-mobile"       :link="route('admin.billing.index')" />
                @endcan
            </x-menu-sub>
            @endcanany

            {{-- Communication --}}
            @canany(['announcements.view','messages.view'])
            <x-menu-sub :title="__('navigation.communication')" icon="o-chat-bubble-left-right">
                @can('announcements.view')
                <x-menu-item :title="__('navigation.announcements')" icon="o-megaphone" :link="route('admin.announcements.index')" />
                @endcan
                @can('messages.view')
                <x-menu-item :title="__('navigation.messages')"      icon="o-envelope"  :link="route('admin.messages.index')" />
                @endcan
            </x-menu-sub>
            @endcanany

            {{-- Reports --}}
            @can('reports.view')
            <x-menu-item :title="__('navigation.reports')" icon="o-chart-bar" link="{{ route('admin.reports.index') }}" />
            @endcan

            {{-- Scheduled Tasks --}}
            @can('scheduled-tasks.view')
            <x-menu-item title="Tâches planifiées" icon="o-clock" link="{{ route('admin.scheduled-tasks.index') }}" />
            @endcan

            <x-menu-separator />

            {{-- Settings --}}
            @canany(['settings.school.view','settings.users.view','settings.roles.view','billing.manage'])
            <x-menu-sub :title="__('navigation.settings')" icon="o-cog-6-tooth">
                @can('settings.school.view')
                <x-menu-item :title="__('navigation.school')" icon="o-building-library"    :link="route('admin.settings.school')" />
                @endcan
                @can('settings.users.view')
                <x-menu-item :title="__('navigation.users')"  icon="o-users"               :link="route('admin.settings.users')" />
                <x-menu-item title="Appareils"                 icon="o-device-phone-mobile" :link="route('admin.settings.device-tokens')" />
                @endcan
                @can('settings.roles.view')
                <x-menu-item :title="__('navigation.roles')"  icon="o-shield-check"        :link="route('admin.settings.roles')" />
                @endcan
                @can('billing.manage')
                <x-menu-item title="API Facturation D-Money"   icon="o-credit-card"         :link="route('admin.settings.billing-api')" />
                @endcan
            </x-menu-sub>
            @endcanany

            <x-menu-separator />

            {{-- Profile + Logout --}}
            <x-menu-item :title="__('navigation.profile')" icon="o-user-circle"                    link="{{ route('profile.show') }}" />
            <x-menu-item :title="__('navigation.logout')"  icon="o-arrow-right-start-on-rectangle" link="{{ route('auth.logout') }}" no-wire-navigate />
        </x-menu>
    </x-slot:sidebar>

    {{-- ── Main content ── --}}
    <x-slot:content>
        {{ $slot }}
    </x-slot:content>
</x-main>

{{-- Toast notifications --}}
<x-toast />

@livewireScripts
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof flatpickr !== 'undefined' && flatpickr.l10ns && flatpickr.l10ns.fr) {
            flatpickr.localize(flatpickr.l10ns.fr);
        }
    });
</script>
</body>
</html>
