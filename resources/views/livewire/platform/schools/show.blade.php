<?php
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\School;
use Mary\Traits\Toast;

new #[Layout('layouts.platform')] class extends Component {
    use Toast;

    public School $school;

    // Edit plan modal
    public bool   $showPlanModal    = false;
    public string $newPlan          = '';
    public int    $planMonths       = 12;

    // Suspend/Activate
    public bool   $showSuspendModal  = false;
    public bool   $showActivateModal = false;
    public string $suspendReason     = '';

    // Delete
    public bool   $showDeleteModal   = false;

    public function mount(string $uuid): void
    {
        $this->school  = School::where('uuid', $uuid)->withCount(['students', 'teachers', 'users'])->firstOrFail();
        $this->newPlan = $this->school->plan;
    }

    public function openPlanModal(): void
    {
        $this->newPlan    = $this->school->plan;
        $this->planMonths = 12;
        $this->showPlanModal = true;
    }

    public function savePlan(): void
    {
        $this->validate([
            'newPlan'    => 'required|in:trial,basic,pro,enterprise',
            'planMonths' => 'required|integer|min:1|max:120',
        ]);

        $this->school->upgradePlan($this->newPlan, $this->planMonths);
        $this->school->refresh();
        $this->showPlanModal = false;
        $this->success("Plan mis à jour → {$this->school->plan_label}", position: 'toast-top toast-end');
    }

    public function confirmSuspend(): void
    {
        $this->suspendReason  = '';
        $this->showSuspendModal = true;
    }

    public function suspend(): void
    {
        $this->school->suspend($this->suspendReason ?: 'Suspendu par l\'administrateur plateforme.');
        $this->school->refresh();
        $this->showSuspendModal = false;
        $this->success("École \"{$this->school->name}\" suspendue.", position: 'toast-top toast-end');
    }

    public function activate(): void
    {
        $this->school->reactivate();
        $this->school->refresh();
        $this->showActivateModal = false;
        $this->success("École \"{$this->school->name}\" réactivée.", position: 'toast-top toast-end');
    }

    public function delete(): void
    {
        $name = $this->school->name;
        $this->school->delete();
        $this->success("École \"{$name}\" supprimée.", position: 'toast-top toast-end', redirectTo: route('platform.schools.index'));
    }

    public function with(): array
    {
        return [
            'plans' => [
                ['id' => 'trial',      'name' => 'Essai'],
                ['id' => 'basic',      'name' => 'Basic'],
                ['id' => 'pro',        'name' => 'Pro'],
                ['id' => 'enterprise', 'name' => 'Enterprise'],
            ],
            'recentUsers' => $this->school->users()->latest()->take(10)->get(),
        ];
    }
};
?>

<div class="p-4 lg:p-8 space-y-6">
    <x-header title="{{ $school->name }}" subtitle="Fiche école — Plateforme" separator>
        <x-slot:actions>
            <x-button label="Retour" icon="o-arrow-left" link="{{ route('platform.schools.index') }}" class="btn-ghost btn-sm" />
            @if($school->is_active)
                <x-button label="Suspendre" icon="o-no-symbol" wire:click="confirmSuspend" class="btn-error btn-sm" />
            @else
                <x-button label="Réactiver" icon="o-check-circle" wire:click="$set('showActivateModal', true)" class="btn-success btn-sm" />
            @endif
        </x-slot:actions>
    </x-header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Left: Identity + Stats --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Identity card --}}
            <x-card shadow class="border-0">
                <div class="flex items-start gap-4">
                    @if($school->logo)
                        <img src="{{ $school->logo_url }}" class="w-16 h-16 rounded-xl object-cover shrink-0" alt="">
                    @else
                        <div class="w-16 h-16 rounded-xl bg-slate-200 flex items-center justify-center shrink-0 text-2xl font-bold text-slate-600">
                            {{ substr($school->name, 0, 1) }}
                        </div>
                    @endif
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2 mb-1">
                            <h2 class="font-bold text-lg">{{ $school->name }}</h2>
                            <x-badge :value="$school->plan_label" :class="match($school->plan) { 'enterprise' => 'badge-primary badge-sm', 'pro' => 'badge-info badge-sm', 'basic' => 'badge-success badge-sm', default => 'badge-warning badge-sm' }" />
                            @if($school->is_active)
                                <x-badge value="Active" class="badge-success badge-sm" />
                            @else
                                <x-badge value="Suspendue" class="badge-error badge-sm" />
                            @endif
                        </div>
                        <p class="text-base-content/60 text-sm">{{ $school->code }} · {{ $school->city }}, {{ $school->country }}</p>
                        @if($school->email)
                            <p class="text-sm mt-1">{{ $school->email }}</p>
                        @endif
                        @if($school->phone)
                            <p class="text-sm text-base-content/60">{{ $school->phone }}</p>
                        @endif
                    </div>
                </div>

                @if($school->suspension_reason)
                    <div class="mt-4 p-3 bg-error/10 rounded-lg border border-error/20 text-sm text-error">
                        <strong>Raison de suspension :</strong> {{ $school->suspension_reason }}
                        @if($school->suspended_at)
                            · {{ $school->suspended_at->format('d/m/Y H:i') }}
                        @endif
                    </div>
                @endif
            </x-card>

            {{-- Stats --}}
            <div class="grid grid-cols-3 gap-4">
                <x-card shadow class="border-0 text-center">
                    <p class="text-3xl font-black text-primary">{{ $school->students_count }}</p>
                    <p class="text-xs text-base-content/60 mt-1">Élèves</p>
                </x-card>
                <x-card shadow class="border-0 text-center">
                    <p class="text-3xl font-black text-info">{{ $school->teachers_count }}</p>
                    <p class="text-xs text-base-content/60 mt-1">Enseignants</p>
                </x-card>
                <x-card shadow class="border-0 text-center">
                    <p class="text-3xl font-black text-success">{{ $school->users_count }}</p>
                    <p class="text-xs text-base-content/60 mt-1">Utilisateurs</p>
                </x-card>
            </div>

            {{-- Recent users --}}
            <x-card title="Utilisateurs récents" shadow class="border-0">
                @if($recentUsers->isEmpty())
                    <p class="text-center text-base-content/40 py-4">Aucun utilisateur.</p>
                @else
                    <div class="divide-y divide-base-200">
                        @foreach($recentUsers as $u)
                            <div class="flex items-center justify-between py-2">
                                <div>
                                    <p class="font-medium text-sm">{{ $u->name }}</p>
                                    <p class="text-xs text-base-content/50">{{ $u->email }}</p>
                                </div>
                                <x-badge :value="$u->getRoleNames()->first() ?? '—'" class="badge-ghost badge-sm" />
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-card>
        </div>

        {{-- Right: Plan + Subscription + Actions --}}
        <div class="space-y-6">
            {{-- Subscription --}}
            <x-card title="Abonnement" shadow class="border-0">
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-base-content/60">Plan</span>
                        <span class="font-semibold">{{ $school->plan_label }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-base-content/60">Statut</span>
                        @if($school->hasValidSubscription())
                            <span class="text-success font-medium">Actif</span>
                        @else
                            <span class="text-error font-medium">Expiré</span>
                        @endif
                    </div>
                    @if($school->trial_ends_at)
                        <div class="flex justify-between">
                            <span class="text-base-content/60">Fin d'essai</span>
                            <span>{{ $school->trial_ends_at->format('d/m/Y') }}</span>
                        </div>
                    @endif
                    @if($school->subscription_ends_at)
                        <div class="flex justify-between">
                            <span class="text-base-content/60">Expiration</span>
                            <span class="{{ $school->subscription_ends_at->isPast() ? 'text-error' : '' }}">
                                {{ $school->subscription_ends_at->format('d/m/Y') }}
                            </span>
                        </div>
                    @endif
                    @if($school->daysUntilExpiry() !== null)
                        <div class="flex justify-between">
                            <span class="text-base-content/60">Jours restants</span>
                            <span class="{{ ($school->daysUntilExpiry() < 30) ? 'text-warning font-bold' : '' }}">
                                {{ $school->daysUntilExpiry() }} j
                            </span>
                        </div>
                    @endif
                    <div class="flex justify-between">
                        <span class="text-base-content/60">Devise</span>
                        <span>{{ $school->currency }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-base-content/60">Inscrite le</span>
                        <span>{{ $school->created_at->format('d/m/Y') }}</span>
                    </div>
                </div>
                <div class="mt-4">
                    <x-button label="Modifier le plan" icon="o-credit-card" wire:click="openPlanModal" class="btn-primary btn-sm w-full" />
                </div>
            </x-card>

            {{-- Plan limits --}}
            <x-card title="Limites du plan" shadow class="border-0">
                @php
                    $limits = \App\Models\School::PLAN_LIMITS[$school->plan] ?? ['students' => 30, 'teachers' => 5];
                    $maxStudents = $limits['students'] === 0 ? '∞' : $limits['students'];
                    $maxTeachers = $limits['teachers'] === 0 ? '∞' : $limits['teachers'];
                @endphp
                <div class="space-y-2 text-sm">
                    <div>
                        <div class="flex justify-between mb-1">
                            <span>Élèves</span>
                            <span>{{ $school->students_count }} / {{ $maxStudents }}</span>
                        </div>
                        @if(is_numeric($maxStudents) && $maxStudents > 0)
                            <progress class="progress progress-primary w-full" value="{{ $school->students_count }}" max="{{ $maxStudents }}"></progress>
                        @endif
                    </div>
                    <div>
                        <div class="flex justify-between mb-1">
                            <span>Enseignants</span>
                            <span>{{ $school->teachers_count }} / {{ $maxTeachers }}</span>
                        </div>
                        @if(is_numeric($maxTeachers) && $maxTeachers > 0)
                            <progress class="progress progress-info w-full" value="{{ $school->teachers_count }}" max="{{ $maxTeachers }}"></progress>
                        @endif
                    </div>
                </div>
            </x-card>

            {{-- Danger zone --}}
            <x-card title="Zone sensible" shadow class="border-0 border-error/30">
                <div class="space-y-2">
                    <x-button label="Supprimer l'école" icon="o-trash" wire:click="$set('showDeleteModal', true)" class="btn-outline btn-error btn-sm w-full" />
                </div>
            </x-card>
        </div>
    </div>

    {{-- Plan modal --}}
    <x-modal wire:model="showPlanModal" title="Modifier le plan" class="backdrop-blur">
        <div class="space-y-4">
            <x-select wire:model="newPlan" label="Nouveau plan" :options="$plans" />
            <x-input wire:model="planMonths" label="Durée (mois)" type="number" min="1" max="120" />
        </div>
        <x-slot:actions>
            <x-button label="Annuler" wire:click="$set('showPlanModal', false)" class="btn-ghost" />
            <x-button label="Enregistrer" wire:click="savePlan" icon="o-check" class="btn-primary" spinner="savePlan" />
        </x-slot:actions>
    </x-modal>

    {{-- Suspend modal --}}
    <x-modal wire:model="showSuspendModal" title="Suspendre l'école" class="backdrop-blur">
        <x-textarea wire:model="suspendReason" label="Raison" placeholder="Expliquez la raison..." rows="3" />
        <x-slot:actions>
            <x-button label="Annuler" wire:click="$set('showSuspendModal', false)" class="btn-ghost" />
            <x-button label="Suspendre" wire:click="suspend" icon="o-no-symbol" class="btn-error" spinner="suspend" />
        </x-slot:actions>
    </x-modal>

    {{-- Activate modal --}}
    <x-modal wire:model="showActivateModal" title="Réactiver l'école" class="backdrop-blur">
        <p class="text-base-content/70">Voulez-vous réactiver cette école et restaurer l'accès à tous ses utilisateurs ?</p>
        <x-slot:actions>
            <x-button label="Annuler" wire:click="$set('showActivateModal', false)" class="btn-ghost" />
            <x-button label="Réactiver" wire:click="activate" icon="o-check-circle" class="btn-success" spinner="activate" />
        </x-slot:actions>
    </x-modal>

    {{-- Delete modal --}}
    <x-modal wire:model="showDeleteModal" title="Supprimer l'école" class="backdrop-blur">
        <div class="text-center space-y-3">
            <x-icon name="o-exclamation-triangle" class="w-16 h-16 text-error mx-auto" />
            <p class="font-semibold">Êtes-vous sûr de vouloir supprimer <strong>{{ $school->name }}</strong> ?</p>
            <p class="text-base-content/60 text-sm">Cette action est irréversible. Toutes les données associées seront perdues.</p>
        </div>
        <x-slot:actions>
            <x-button label="Annuler" wire:click="$set('showDeleteModal', false)" class="btn-ghost" />
            <x-button label="Supprimer définitivement" wire:click="delete" icon="o-trash" class="btn-error" spinner="delete" />
        </x-slot:actions>
    </x-modal>
</div>
