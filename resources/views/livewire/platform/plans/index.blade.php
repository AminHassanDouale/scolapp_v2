<?php
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\School;
use Mary\Traits\Toast;

new #[Layout('layouts.platform')] class extends Component {
    use Toast;

    // Bulk upgrade modal
    public bool   $showUpgradeModal = false;
    public string $upgradePlan      = 'pro';
    public int    $upgradeMonths    = 12;
    public ?int   $upgradeSchoolId  = null;
    public string $upgradeSchoolName = '';

    public function openUpgrade(int $id, string $name): void
    {
        $this->upgradeSchoolId   = $id;
        $this->upgradeSchoolName = $name;
        $this->upgradePlan       = 'pro';
        $this->upgradeMonths     = 12;
        $this->showUpgradeModal  = true;
    }

    public function saveUpgrade(): void
    {
        $this->validate([
            'upgradePlan'   => 'required|in:trial,basic,pro,enterprise',
            'upgradeMonths' => 'required|integer|min:1|max:120',
        ]);

        $school = School::findOrFail($this->upgradeSchoolId);
        $school->upgradePlan($this->upgradePlan, $this->upgradeMonths);
        $this->showUpgradeModal = false;
        $this->success("Plan de \"{$school->name}\" mis à jour.", position: 'toast-top toast-end');
    }

    public function with(): array
    {
        $plans = [
            'trial'      => ['label' => 'Essai',      'color' => 'warning', 'icon' => 'o-clock',          'desc' => '30 élèves · 5 enseignants · 14 jours'],
            'basic'      => ['label' => 'Basic',       'color' => 'success', 'icon' => 'o-star',           'desc' => '200 élèves · 20 enseignants · annuel'],
            'pro'        => ['label' => 'Pro',         'color' => 'info',    'icon' => 'o-rocket-launch',  'desc' => '1 000 élèves · 100 enseignants · annuel'],
            'enterprise' => ['label' => 'Enterprise',  'color' => 'primary', 'icon' => 'o-building-office','desc' => 'Illimité · multi-campus · support dédié'],
        ];

        $stats = [];
        foreach (array_keys($plans) as $p) {
            $q = School::where('plan', $p);
            $stats[$p] = [
                'total'     => $q->count(),
                'active'    => $q->where('is_active', true)->count(),
                'suspended' => $q->where('is_active', false)->count(),
            ];
        }

        $expiringSoon = School::whereNotNull('subscription_ends_at')
            ->where('subscription_ends_at', '>=', now())
            ->where('subscription_ends_at', '<=', now()->addDays(30))
            ->where('is_active', true)
            ->orderBy('subscription_ends_at')
            ->get();

        $trialExpiring = School::where('plan', 'trial')
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '>=', now())
            ->where('trial_ends_at', '<=', now()->addDays(7))
            ->where('is_active', true)
            ->orderBy('trial_ends_at')
            ->get();

        return compact('plans', 'stats', 'expiringSoon', 'trialExpiring');
    }
};
?>

<div class="p-4 lg:p-8 space-y-6">
    <x-header title="Gestion des plans" subtitle="Abonnements et renouvellements" separator />

    {{-- Plan overview cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        @foreach($plans as $key => $plan)
            <x-card shadow class="border-0">
                <div class="flex items-start justify-between">
                    <div>
                        <x-badge :value="$plan['label']" class="badge-{{ $plan['color'] }} badge-sm mb-2" />
                        <p class="text-3xl font-black">{{ $stats[$key]['total'] }}</p>
                        <p class="text-xs text-base-content/60 mt-1">{{ $plan['desc'] }}</p>
                    </div>
                    <x-icon :name="$plan['icon']" class="w-8 h-8 text-{{ $plan['color'] }}/40" />
                </div>
                <div class="mt-3 flex gap-3 text-xs text-base-content/60">
                    <span class="text-success">{{ $stats[$key]['active'] }} actives</span>
                    @if($stats[$key]['suspended'] > 0)
                        <span class="text-error">{{ $stats[$key]['suspended'] }} suspendues</span>
                    @endif
                </div>
            </x-card>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Expiring subscriptions (≤ 30 days) --}}
        <x-card title="Abonnements expirant bientôt" subtitle="Dans les 30 prochains jours" shadow class="border-0">
            @if($expiringSoon->isEmpty())
                <p class="text-center text-base-content/40 py-6 text-sm">Aucun abonnement n'expire dans 30 jours.</p>
            @else
                <div class="divide-y divide-base-200">
                    @foreach($expiringSoon as $school)
                        <div class="flex items-center justify-between py-3">
                            <div class="min-w-0">
                                <p class="font-medium text-sm truncate">{{ $school->name }}</p>
                                <p class="text-xs text-base-content/50">
                                    {{ $school->plan_label }} · expire le {{ $school->subscription_ends_at->format('d/m/Y') }}
                                </p>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <span class="text-xs font-bold {{ $school->daysUntilExpiry() < 7 ? 'text-error' : 'text-warning' }}">
                                    J-{{ $school->daysUntilExpiry() }}
                                </span>
                                <x-button icon="o-credit-card" wire:click="openUpgrade({{ $school->id }}, '{{ addslashes($school->name) }}')" class="btn-ghost btn-xs" tooltip="Renouveler" />
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-card>

        {{-- Trial expiring (≤ 7 days) --}}
        <x-card title="Essais se terminant" subtitle="Dans les 7 prochains jours" shadow class="border-0">
            @if($trialExpiring->isEmpty())
                <p class="text-center text-base-content/40 py-6 text-sm">Aucun essai n'expire dans 7 jours.</p>
            @else
                <div class="divide-y divide-base-200">
                    @foreach($trialExpiring as $school)
                        <div class="flex items-center justify-between py-3">
                            <div class="min-w-0">
                                <p class="font-medium text-sm truncate">{{ $school->name }}</p>
                                <p class="text-xs text-base-content/50">
                                    Essai se termine le {{ $school->trial_ends_at->format('d/m/Y') }}
                                </p>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <span class="text-xs font-bold text-warning">
                                    J-{{ now()->diffInDays($school->trial_ends_at) }}
                                </span>
                                <x-button icon="o-arrow-up-circle" wire:click="openUpgrade({{ $school->id }}, '{{ addslashes($school->name) }}')" class="btn-ghost btn-xs text-success" tooltip="Convertir" />
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-card>
    </div>

    {{-- Upgrade modal --}}
    <x-modal wire:model="showUpgradeModal" title="Modifier / Renouveler le plan" class="backdrop-blur">
        <p class="text-base-content/60 text-sm mb-4">École : <strong>{{ $upgradeSchoolName }}</strong></p>
        <div class="space-y-3">
            <x-select wire:model="upgradePlan" label="Nouveau plan" :options="[
                ['id' => 'trial',      'name' => 'Essai'],
                ['id' => 'basic',      'name' => 'Basic'],
                ['id' => 'pro',        'name' => 'Pro'],
                ['id' => 'enterprise', 'name' => 'Enterprise'],
            ]" />
            <x-input wire:model="upgradeMonths" label="Durée (mois)" type="number" min="1" max="120" />
        </div>
        <x-slot:actions>
            <x-button label="Annuler" wire:click="$set('showUpgradeModal', false)" class="btn-ghost" />
            <x-button label="Appliquer" wire:click="saveUpgrade" icon="o-check" class="btn-primary" spinner="saveUpgrade" />
        </x-slot:actions>
    </x-modal>
</div>
