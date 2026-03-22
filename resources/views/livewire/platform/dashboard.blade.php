<?php
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\School;
use App\Models\User;

new #[Layout('layouts.platform')] class extends Component {
    public function with(): array
    {
        $totalSchools    = School::count();
        $activeSchools   = School::where('is_active', true)->count();
        $suspendedSchools = School::where('is_active', false)->count();
        $totalUsers      = User::count();
        $trialSchools    = School::where('plan', 'trial')->count();
        $proSchools      = School::where('plan', 'pro')->count();
        $enterpriseSchools = School::where('plan', 'enterprise')->count();

        $expiringSoon = School::whereNotNull('subscription_ends_at')
            ->where('subscription_ends_at', '>=', now())
            ->where('subscription_ends_at', '<=', now()->addDays(30))
            ->where('is_active', true)
            ->count();

        $recentSchools = School::withCount(['students', 'teachers'])
            ->latest()->take(5)->get();

        $planDistribution = [
            'trial'      => School::where('plan', 'trial')->count(),
            'basic'      => School::where('plan', 'basic')->count(),
            'pro'        => $proSchools,
            'enterprise' => $enterpriseSchools,
        ];

        return compact(
            'totalSchools', 'activeSchools', 'suspendedSchools',
            'totalUsers', 'trialSchools', 'expiringSoon',
            'recentSchools', 'planDistribution'
        );
    }
};
?>

<div class="p-4 lg:p-8 space-y-6">
    <x-header title="Tableau de bord" subtitle="Vue globale de la plateforme ScolApp SMS" separator>
        <x-slot:actions>
            <x-button label="Nouvelle école" icon="o-plus" link="{{ route('platform.schools.create') }}" class="btn-primary btn-sm" />
        </x-slot:actions>
    </x-header>

    {{-- KPI Cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <x-card shadow class="border-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-blue-100 flex items-center justify-center shrink-0">
                    <x-icon name="o-building-library" class="w-5 h-5 text-blue-600" />
                </div>
                <div>
                    <p class="text-2xl font-black">{{ $totalSchools }}</p>
                    <p class="text-xs text-base-content/60">Écoles total</p>
                </div>
            </div>
        </x-card>
        <x-card shadow class="border-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-green-100 flex items-center justify-center shrink-0">
                    <x-icon name="o-check-circle" class="w-5 h-5 text-green-600" />
                </div>
                <div>
                    <p class="text-2xl font-black">{{ $activeSchools }}</p>
                    <p class="text-xs text-base-content/60">Écoles actives</p>
                </div>
            </div>
        </x-card>
        <x-card shadow class="border-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-amber-100 flex items-center justify-center shrink-0">
                    <x-icon name="o-clock" class="w-5 h-5 text-amber-600" />
                </div>
                <div>
                    <p class="text-2xl font-black">{{ $trialSchools }}</p>
                    <p class="text-xs text-base-content/60">En essai</p>
                </div>
            </div>
        </x-card>
        <x-card shadow class="border-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-violet-100 flex items-center justify-center shrink-0">
                    <x-icon name="o-users" class="w-5 h-5 text-violet-600" />
                </div>
                <div>
                    <p class="text-2xl font-black">{{ $totalUsers }}</p>
                    <p class="text-xs text-base-content/60">Utilisateurs</p>
                </div>
            </div>
        </x-card>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Plan distribution --}}
        <x-card title="Distribution des plans" shadow class="border-0">
            <div class="space-y-3">
                @foreach([
                    ['key' => 'enterprise', 'label' => 'Enterprise', 'color' => 'bg-primary',  'badge' => 'badge-primary'],
                    ['key' => 'pro',        'label' => 'Pro',         'color' => 'bg-info',     'badge' => 'badge-info'],
                    ['key' => 'basic',      'label' => 'Basic',       'color' => 'bg-success',  'badge' => 'badge-success'],
                    ['key' => 'trial',      'label' => 'Essai',       'color' => 'bg-warning',  'badge' => 'badge-warning'],
                ] as $p)
                    @php $count = $planDistribution[$p['key']]; $total = max(1, $totalSchools); @endphp
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span>{{ $p['label'] }}</span>
                            <span class="font-bold">{{ $count }}</span>
                        </div>
                        <div class="w-full bg-base-200 rounded-full h-2">
                            <div class="{{ $p['color'] }} h-2 rounded-full" style="width: {{ round(($count / $total) * 100) }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>

            @if($suspendedSchools > 0)
                <div class="mt-4 p-2 bg-error/10 rounded-lg text-sm flex items-center gap-2">
                    <x-icon name="o-no-symbol" class="w-4 h-4 text-error" />
                    <span class="text-error font-medium">{{ $suspendedSchools }} école(s) suspendue(s)</span>
                </div>
            @endif
            @if($expiringSoon > 0)
                <div class="mt-2 p-2 bg-warning/10 rounded-lg text-sm flex items-center gap-2">
                    <x-icon name="o-exclamation-triangle" class="w-4 h-4 text-warning" />
                    <span class="text-warning font-medium">{{ $expiringSoon }} abonnement(s) expirant dans 30j</span>
                </div>
            @endif
        </x-card>

        {{-- Recent schools --}}
        <x-card title="Écoles récentes" shadow class="border-0 lg:col-span-2">
            @if($recentSchools->isEmpty())
                <p class="text-center text-base-content/40 py-6 text-sm">Aucune école inscrite.</p>
            @else
                <div class="divide-y divide-base-200">
                    @foreach($recentSchools as $school)
                        <div class="flex items-center justify-between py-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-8 h-8 rounded-full bg-slate-200 flex items-center justify-center shrink-0 font-bold text-slate-600 text-sm">
                                    {{ substr($school->name, 0, 1) }}
                                </div>
                                <div class="min-w-0">
                                    <p class="font-medium text-sm truncate">{{ $school->name }}</p>
                                    <p class="text-xs text-base-content/50">
                                        {{ $school->students_count }} élèves · {{ $school->city }}
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <x-badge :value="$school->plan_label" :class="match($school->plan) { 'enterprise' => 'badge-primary badge-sm', 'pro' => 'badge-info badge-sm', 'basic' => 'badge-success badge-sm', default => 'badge-warning badge-sm' }" />
                                @if(!$school->is_active)
                                    <x-badge value="Susp." class="badge-error badge-sm" />
                                @endif
                                <x-button icon="o-eye" link="{{ route('platform.schools.show', $school->uuid) }}" class="btn-ghost btn-xs" />
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-3 text-right">
                    <x-button label="Toutes les écoles" icon="o-arrow-right" link="{{ route('platform.schools.index') }}" class="btn-ghost btn-sm" />
                </div>
            @endif
        </x-card>
    </div>

    {{-- Quick actions --}}
    <x-card title="Accès rapides" shadow class="border-0">
        <div class="flex flex-wrap gap-3">
            <x-button label="Nouvelle école"  icon="o-plus"          link="{{ route('platform.schools.create') }}" class="btn-primary btn-sm" />
            <x-button label="Toutes les écoles" icon="o-building-library" link="{{ route('platform.schools.index') }}" class="btn-ghost btn-sm" />
            <x-button label="Utilisateurs"    icon="o-users"         link="{{ route('platform.users.index') }}"   class="btn-ghost btn-sm" />
            <x-button label="Plans"           icon="o-credit-card"   link="{{ route('platform.plans.index') }}"   class="btn-ghost btn-sm" />
            <x-button label="Paramètres"      icon="o-cog-6-tooth"   link="{{ route('platform.settings') }}"      class="btn-ghost btn-sm" />
        </div>
    </x-card>
</div>
