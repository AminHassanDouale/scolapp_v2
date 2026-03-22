<?php
use App\Models\Guardian;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new #[Layout('layouts.app')] class extends Component {
    use Toast, WithPagination;

    public string $search       = '';
    public string $statusFilter = '';
    public bool   $showFilters  = false;
    public string $sortBy       = 'name';
    public string $sortDir      = 'asc';

    public function updatingSearch(): void { $this->resetPage(); }

    public function sortBy(string $col): void
    {
        $this->sortDir = $this->sortBy === $col && $this->sortDir === 'asc' ? 'desc' : 'asc';
        $this->sortBy  = $col;
    }

    public function toggleActive(int $id): void
    {
        $guardian = Guardian::findOrFail($id);
        $guardian->update(['is_active' => !$guardian->is_active]);
        $this->success(
            $guardian->is_active ? 'Responsable activé.' : 'Responsable désactivé.',
            position: 'toast-top toast-end', icon: 'o-bolt', css: 'alert-success', timeout: 3000
        );
    }

    public function deleteGuardian(int $id): void
    {
        $guardian = Guardian::findOrFail($id);
        $guardian->students()->detach();
        $guardian->delete();
        $this->success('Responsable supprimé.', position: 'toast-top toast-end', icon: 'o-trash', css: 'alert-success', timeout: 3000);
    }

    public function with(): array
    {
        $schoolId = auth()->user()->school_id;

        $guardians = Guardian::where('school_id', $schoolId)
            ->with(['students', 'user'])
            ->when($this->search, fn($q) =>
                $q->where(fn($s) =>
                    $s->where('name',        'like', "%{$this->search}%")
                      ->orWhere('email',     'like', "%{$this->search}%")
                      ->orWhere('phone',     'like', "%{$this->search}%")
                      ->orWhere('national_id','like', "%{$this->search}%")
                ))
            ->when($this->statusFilter !== '', fn($q) =>
                $q->where('is_active', $this->statusFilter === 'active')
            )
            ->orderBy($this->sortBy, $this->sortDir)
            ->paginate(20);

        $base     = Guardian::where('school_id', $schoolId);
        $total    = (clone $base)->count();
        $active   = (clone $base)->where('is_active', true)->count();
        $withAcct = (clone $base)->whereNotNull('user_id')->count();
        $male     = (clone $base)->where('gender', 'male')->count();

        return [
            'guardians' => $guardians,
            'stats'     => compact('total', 'active', 'withAcct', 'male'),
        ];
    }
};
?>

<div>
    <x-header title="Responsables légaux" separator progress-indicator>
        <x-slot:actions>
            <x-button label="Nouveau responsable" icon="o-plus"
                      :link="route('admin.guardians.create')"
                      class="btn-primary" wire:navigate />
        </x-slot:actions>
    </x-header>

    {{-- Stats --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <div class="rounded-2xl bg-linear-to-br from-primary to-primary/70 text-primary-content p-4">
            <p class="text-sm opacity-80">Total</p>
            <p class="text-3xl font-black mt-1">{{ $stats['total'] }}</p>
        </div>
        <button wire:click="$set('statusFilter', '{{ $statusFilter === 'active' ? '' : 'active' }}')"
                class="rounded-2xl bg-linear-to-br from-success to-success/70 text-success-content p-4 text-left transition-all hover:shadow-md
                       {{ $statusFilter === 'active' ? 'ring-2 ring-offset-2 ring-primary' : '' }}">
            <p class="text-sm opacity-80">Actifs</p>
            <p class="text-3xl font-black mt-1">{{ $stats['active'] }}</p>
        </button>
        <div class="rounded-2xl bg-linear-to-br from-info to-info/70 text-info-content p-4">
            <p class="text-sm opacity-80">Avec compte</p>
            <p class="text-3xl font-black mt-1">{{ $stats['withAcct'] }}</p>
        </div>
        <div class="rounded-2xl bg-linear-to-br from-secondary to-secondary/70 text-secondary-content p-4">
            <p class="text-sm opacity-80">Hommes</p>
            <p class="text-3xl font-black mt-1">{{ $stats['male'] }}</p>
        </div>
    </div>

    {{-- Toolbar --}}
    <div class="flex items-center gap-3 mb-4">
        <x-input wire:model.live.debounce="search" placeholder="Rechercher (nom, email, téléphone…)"
                 icon="o-magnifying-glass" clearable class="flex-1" />
        <x-button icon="o-adjustments-horizontal" wire:click="$set('showFilters', true)"
                  class="btn-outline" tooltip="Filtres"
                  :badge="$statusFilter !== '' ? '●' : null" badge-classes="badge-primary badge-xs" />
    </div>

    {{-- Table --}}
    <x-card>
        <div class="overflow-x-auto"><table class="table w-full">
            <thead><tr>
                <th>
                    <button wire:click="sortBy('name')" class="flex items-center gap-1 hover:text-primary font-semibold">
                        Responsable
                        @if($sortBy === 'name')
                        <x-icon name="{{ $sortDir === 'asc' ? 'o-chevron-up' : 'o-chevron-down' }}" class="w-3 h-3"/>
                        @endif
                    </button>
                </th>
                <th>Contact</th>
                <th>Élèves liés</th>
                <th>Compte</th>
                <th>Statut</th>
                <th class="w-28">Actions</th>
            </tr></thead><tbody>

            @forelse($guardians as $guardian)
            <tr wire:key="guardian-{{ $guardian->id }}" class="hover">
                <td>
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full overflow-hidden bg-primary/10 flex items-center justify-center font-bold text-primary text-sm shrink-0">
                            @if($guardian->photo_url)
                            <img src="{{ $guardian->photo_url }}" alt="{{ $guardian->full_name }}" class="w-full h-full object-cover" />
                            @else
                            {{ strtoupper(substr($guardian->name, 0, 1)) }}
                            @endif
                        </div>
                        <div>
                            <a href="{{ route('admin.guardians.show', $guardian->uuid) }}"
                               wire:navigate class="font-semibold hover:text-primary text-sm">
                                {{ $guardian->full_name }}
                            </a>
                            @if($guardian->profession)
                            <p class="text-xs text-base-content/40">{{ $guardian->profession }}</p>
                            @endif
                        </div>
                    </div>
                </td>
                <td>
                    <div class="text-sm space-y-0.5">
                        @if($guardian->email)
                        <p class="text-base-content/70 flex items-center gap-1">
                            <x-icon name="o-envelope" class="w-3 h-3 shrink-0"/>{{ $guardian->email }}
                        </p>
                        @endif
                        @if($guardian->phone)
                        <p class="text-base-content/50 flex items-center gap-1">
                            <x-icon name="o-phone" class="w-3 h-3 shrink-0"/>{{ $guardian->phone }}
                        </p>
                        @endif
                    </div>
                </td>
                <td>
                    @if($guardian->students->count())
                    <div class="flex flex-wrap gap-1">
                        @foreach($guardian->students->take(2) as $student)
                        <x-badge value="{{ $student->full_name }}" class="badge-outline badge-sm" />
                        @endforeach
                        @if($guardian->students->count() > 2)
                        <x-badge value="+{{ $guardian->students->count() - 2 }}" class="badge-ghost badge-xs" />
                        @endif
                    </div>
                    @else
                    <span class="text-base-content/30 text-sm">—</span>
                    @endif
                </td>
                <td>
                    @if($guardian->user_id)
                    <x-badge value="Actif" class="badge-success badge-sm" />
                    @else
                    <x-badge value="Sans compte" class="badge-ghost badge-sm" />
                    @endif
                </td>
                <td>
                    <button wire:click="toggleActive({{ $guardian->id }})"
                            class="badge {{ $guardian->is_active ? 'badge-success' : 'badge-ghost' }} badge-sm cursor-pointer hover:opacity-70 transition-opacity">
                        {{ $guardian->is_active ? 'Actif' : 'Inactif' }}
                    </button>
                </td>
                <td>
                    <div class="flex gap-1">
                        <x-button icon="o-eye"
                                  :link="route('admin.guardians.show', $guardian->uuid)"
                                  class="btn-ghost btn-xs" tooltip="Voir" wire:navigate />
                        <x-button icon="o-pencil"
                                  :link="route('admin.guardians.edit', $guardian->uuid)"
                                  class="btn-ghost btn-xs" tooltip="Modifier" wire:navigate />
                        <x-button icon="o-trash"
                                  wire:click="deleteGuardian({{ $guardian->id }})"
                                  wire:confirm="Supprimer ce responsable définitivement ?"
                                  class="btn-ghost btn-xs text-error" tooltip="Supprimer" />
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="text-center py-12 text-base-content/40">
                    <x-icon name="o-user-group" class="w-12 h-12 mx-auto mb-2 opacity-20" />
                    <p>Aucun responsable trouvé</p>
                    @if($search || $statusFilter !== '')
                    <x-button label="Effacer les filtres"
                              wire:click="$set('search',''); $set('statusFilter','')"
                              class="btn-ghost btn-sm mt-2" />
                    @endif
                </td>
            </tr>
            @endforelse
        </tbody></table></div>
        <div class="mt-4">{{ $guardians->links() }}</div>
    </x-card>

    {{-- Filters drawer --}}
    <x-drawer wire:model="showFilters" title="Filtres" position="right" class="w-72">
        <div class="p-4 space-y-4">
            <x-select
                label="Statut"
                wire:model.live="statusFilter"
                :options="[['id'=>'active','name'=>'Actifs'],['id'=>'inactive','name'=>'Inactifs']]"
                option-value="id"
                option-label="name"
                placeholder="Tous"
                placeholder-value=""
            />
        </div>
        <x-slot:actions>
            <x-button label="Réinitialiser"
                      wire:click="$set('statusFilter', ''); $set('showFilters', false)"
                      class="btn-ghost" />
            <x-button label="Fermer" wire:click="$set('showFilters', false)" class="btn-primary" />
        </x-slot:actions>
    </x-drawer>
</div>
