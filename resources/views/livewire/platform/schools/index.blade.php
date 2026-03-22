<?php
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use App\Models\School;
use Mary\Traits\Toast;

new #[Layout('layouts.platform')] class extends Component {
    use Toast, WithPagination;

    public string $search  = '';
    public string $plan    = '';
    public string $status  = '';
    public bool   $showSuspendModal  = false;
    public bool   $showActivateModal = false;
    public ?int   $selectedSchoolId  = null;
    public string $suspendReason     = '';

    public function updatedSearch(): void  { $this->resetPage(); }
    public function updatedPlan(): void    { $this->resetPage(); }
    public function updatedStatus(): void  { $this->resetPage(); }

    public function confirmSuspend(int $id): void
    {
        $this->selectedSchoolId = $id;
        $this->suspendReason    = '';
        $this->showSuspendModal = true;
    }

    public function suspend(): void
    {
        $school = School::findOrFail($this->selectedSchoolId);
        $school->suspend($this->suspendReason ?: 'Suspendu par l\'administrateur plateforme.');
        $this->showSuspendModal = false;
        $this->success("École \"{$school->name}\" suspendue.", position: 'toast-top toast-end');
    }

    public function confirmActivate(int $id): void
    {
        $this->selectedSchoolId  = $id;
        $this->showActivateModal = true;
    }

    public function activate(): void
    {
        $school = School::findOrFail($this->selectedSchoolId);
        $school->reactivate();
        $this->showActivateModal = false;
        $this->success("École \"{$school->name}\" réactivée.", position: 'toast-top toast-end');
    }

    public function with(): array
    {
        $schools = School::withCount(['students', 'teachers', 'users'])
            ->when($this->search, fn($q) => $q->where(fn($q2) =>
                $q2->where('name', 'like', "%{$this->search}%")
                   ->orWhere('code', 'like', "%{$this->search}%")
                   ->orWhere('email', 'like', "%{$this->search}%")
            ))
            ->when($this->plan,   fn($q) => $q->where('plan', $this->plan))
            ->when($this->status === 'active',   fn($q) => $q->where('is_active', true))
            ->when($this->status === 'suspended', fn($q) => $q->where('is_active', false))
            ->orderByDesc('created_at')
            ->paginate(20);

        return ['schools' => $schools];
    }
};
?>

<div class="p-4 lg:p-8 space-y-6">
    <x-header title="Gestion des écoles" subtitle="Toutes les écoles inscrites sur la plateforme" separator>
        <x-slot:actions>
            <x-button label="Nouvelle école" icon="o-plus" link="{{ route('platform.schools.create') }}" class="btn-primary btn-sm" />
        </x-slot:actions>
    </x-header>

    {{-- Filters --}}
    <div class="flex flex-wrap gap-3">
        <x-input wire:model.live.debounce="search" placeholder="Rechercher..." icon="o-magnifying-glass" class="flex-1 min-w-48" />
        <x-select wire:model.live="plan" :options="[
            ['id' => '',           'name' => 'Tous les plans'],
            ['id' => 'trial',      'name' => 'Essai'],
            ['id' => 'basic',      'name' => 'Basic'],
            ['id' => 'pro',        'name' => 'Pro'],
            ['id' => 'enterprise', 'name' => 'Enterprise'],
        ]" class="w-40" />
        <x-select wire:model.live="status" :options="[
            ['id' => '',          'name' => 'Tous les statuts'],
            ['id' => 'active',    'name' => 'Actives'],
            ['id' => 'suspended', 'name' => 'Suspendues'],
        ]" class="w-44" />
    </div>

    {{-- Table --}}
    <x-card shadow class="border-0 overflow-x-auto">
        <table class="table table-sm w-full">
            <thead>
                <tr class="text-xs text-base-content/60 uppercase">
                    <th>École</th>
                    <th class="hidden md:table-cell">Plan</th>
                    <th class="hidden lg:table-cell">Élèves</th>
                    <th class="hidden lg:table-cell">Enseignants</th>
                    <th class="hidden xl:table-cell">Expiration</th>
                    <th>Statut</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($schools as $school)
                <tr class="hover">
                    <td>
                        <div class="flex items-center gap-3">
                            @if($school->logo)
                                <img src="{{ $school->logo_url }}" class="w-9 h-9 rounded-full object-cover shrink-0" alt="">
                            @else
                                <div class="w-9 h-9 rounded-full bg-slate-200 flex items-center justify-center shrink-0 font-bold text-slate-600">
                                    {{ substr($school->name, 0, 1) }}
                                </div>
                            @endif
                            <div class="min-w-0">
                                <p class="font-semibold text-sm truncate max-w-40">{{ $school->name }}</p>
                                <p class="text-xs text-base-content/50">{{ $school->code }} · {{ $school->city }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="hidden md:table-cell">
                        <x-badge :value="$school->plan_label" :class="match($school->plan) { 'enterprise' => 'badge-primary badge-sm', 'pro' => 'badge-info badge-sm', 'basic' => 'badge-success badge-sm', default => 'badge-warning badge-sm' }" />
                    </td>
                    <td class="hidden lg:table-cell text-sm">{{ $school->students_count }}</td>
                    <td class="hidden lg:table-cell text-sm">{{ $school->teachers_count }}</td>
                    <td class="hidden xl:table-cell text-sm text-base-content/60">
                        @if($school->subscription_ends_at)
                            {{ $school->subscription_ends_at->format('d/m/Y') }}
                            @if($school->subscription_ends_at->isPast())
                                <span class="text-error text-xs ml-1">Expiré</span>
                            @elseif($school->subscription_ends_at->diffInDays(now()) < 30)
                                <span class="text-warning text-xs ml-1">Bientôt</span>
                            @endif
                        @elseif($school->trial_ends_at)
                            Essai → {{ $school->trial_ends_at->format('d/m/Y') }}
                        @else
                            —
                        @endif
                    </td>
                    <td>
                        @if($school->is_active)
                            <x-badge value="Active" class="badge-success badge-sm" />
                        @else
                            <x-badge value="Suspendue" class="badge-error badge-sm" />
                        @endif
                    </td>
                    <td class="text-right">
                        <div class="flex justify-end gap-1">
                            <x-button icon="o-eye"      link="{{ route('platform.schools.show', $school->uuid) }}" class="btn-ghost btn-xs" tooltip="Voir" />
                            @if($school->is_active)
                                <x-button icon="o-no-symbol" wire:click="confirmSuspend({{ $school->id }})" class="btn-ghost btn-xs text-error" tooltip="Suspendre" />
                            @else
                                <x-button icon="o-check-circle" wire:click="confirmActivate({{ $school->id }})" class="btn-ghost btn-xs text-success" tooltip="Réactiver" />
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-center py-8 text-base-content/40">Aucune école trouvée.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="mt-4">{{ $schools->links() }}</div>
    </x-card>

    {{-- Suspend Modal --}}
    <x-modal wire:model="showSuspendModal" title="Suspendre l'école" class="backdrop-blur">
        <x-textarea wire:model="suspendReason" label="Raison de la suspension" placeholder="Expliquez la raison..." rows="3" />
        <x-slot:actions>
            <x-button label="Annuler" wire:click="$set('showSuspendModal', false)" class="btn-ghost" />
            <x-button label="Suspendre" wire:click="suspend" icon="o-no-symbol" class="btn-error" spinner="suspend" />
        </x-slot:actions>
    </x-modal>

    {{-- Activate Modal --}}
    <x-modal wire:model="showActivateModal" title="Réactiver l'école" class="backdrop-blur">
        <p class="text-base-content/70">Voulez-vous réactiver cette école et restaurer l'accès à tous ses utilisateurs ?</p>
        <x-slot:actions>
            <x-button label="Annuler" wire:click="$set('showActivateModal', false)" class="btn-ghost" />
            <x-button label="Réactiver" wire:click="activate" icon="o-check-circle" class="btn-success" spinner="activate" />
        </x-slot:actions>
    </x-modal>
</div>
