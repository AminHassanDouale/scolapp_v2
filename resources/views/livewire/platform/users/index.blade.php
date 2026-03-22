<?php
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use App\Models\User;
use App\Models\School;
use Mary\Traits\Toast;

new #[Layout('layouts.platform')] class extends Component {
    use Toast, WithPagination;

    public string  $search    = '';
    public string  $role      = '';
    public ?int    $schoolId  = null;
    public bool    $showDeleteModal = false;
    public ?int    $selectedUserId  = null;

    public function updatedSearch(): void  { $this->resetPage(); }
    public function updatedRole(): void    { $this->resetPage(); }
    public function updatedSchoolId(): void { $this->resetPage(); }

    public function confirmDelete(int $id): void
    {
        $this->selectedUserId  = $id;
        $this->showDeleteModal = true;
    }

    public function deleteUser(): void
    {
        $user = User::findOrFail($this->selectedUserId);
        $name = $user->name;
        $user->delete();
        $this->showDeleteModal = false;
        $this->success("Utilisateur \"{$name}\" supprimé.", position: 'toast-top toast-end');
    }

    public function impersonate(int $id): void
    {
        // Placeholder — implement with Laravel Impersonate package if needed
        $this->error('Fonctionnalité impersonate non encore configurée.', position: 'toast-top toast-end');
    }

    public function with(): array
    {
        $users = User::with(['roles', 'school'])
            ->when($this->search, fn($q) => $q->where(fn($q2) =>
                $q2->where('name', 'like', "%{$this->search}%")
                   ->orWhere('email', 'like', "%{$this->search}%")
            ))
            ->when($this->role, fn($q) => $q->role($this->role))
            ->when($this->schoolId, fn($q) => $q->where('school_id', $this->schoolId))
            ->orderByDesc('created_at')
            ->paginate(25);

        $schools = School::orderBy('name')->get(['id', 'name']);
        $schoolOptions = collect([['id' => null, 'name' => 'Toutes les écoles']])->concat(
            $schools->map(fn($s) => ['id' => $s->id, 'name' => $s->name])
        );

        return [
            'users'         => $users,
            'schoolOptions' => $schoolOptions,
            'roleOptions'   => [
                ['id' => '',            'name' => 'Tous les rôles'],
                ['id' => 'super-admin', 'name' => 'Super Admin'],
                ['id' => 'admin',       'name' => 'Admin'],
                ['id' => 'director',    'name' => 'Directeur'],
                ['id' => 'accountant',  'name' => 'Comptable'],
                ['id' => 'teacher',     'name' => 'Enseignant'],
                ['id' => 'monitor',     'name' => 'Surveillant'],
                ['id' => 'guardian',    'name' => 'Responsable'],
                ['id' => 'student',     'name' => 'Élève'],
                ['id' => 'caissier',    'name' => 'Caissier'],
            ],
        ];
    }
};
?>

<div class="p-4 lg:p-8 space-y-6">
    <x-header title="Utilisateurs plateforme" subtitle="Tous les comptes utilisateurs" separator />

    {{-- Filters --}}
    <div class="flex flex-wrap gap-3">
        <x-input wire:model.live.debounce="search" placeholder="Rechercher..." icon="o-magnifying-glass" class="flex-1 min-w-48" />
        <x-select wire:model.live="role" :options="$roleOptions" class="w-44" />
        <x-select wire:model.live="schoolId" :options="$schoolOptions" class="w-52" />
    </div>

    {{-- Table --}}
    <x-card shadow class="border-0 overflow-x-auto">
        <table class="table table-sm w-full">
            <thead>
                <tr class="text-xs text-base-content/60 uppercase">
                    <th>Utilisateur</th>
                    <th class="hidden md:table-cell">Rôle</th>
                    <th class="hidden lg:table-cell">École</th>
                    <th class="hidden xl:table-cell">Inscription</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                <tr class="hover">
                    <td>
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center shrink-0 font-bold text-primary text-sm">
                                {{ substr($user->name, 0, 1) }}
                            </div>
                            <div class="min-w-0">
                                <p class="font-semibold text-sm truncate max-w-36">{{ $user->name }}</p>
                                <p class="text-xs text-base-content/50 truncate max-w-36">{{ $user->email }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="hidden md:table-cell">
                        @foreach($user->roles as $r)
                            <x-badge :value="$r->name" class="badge-ghost badge-sm" />
                        @endforeach
                    </td>
                    <td class="hidden lg:table-cell text-sm">
                        {{ $user->school?->name ?? '— Plateforme —' }}
                    </td>
                    <td class="hidden xl:table-cell text-sm text-base-content/60">
                        {{ $user->created_at->format('d/m/Y') }}
                    </td>
                    <td class="text-right">
                        <div class="flex justify-end gap-1">
                            <x-button icon="o-arrow-right-end-on-rectangle" wire:click="impersonate({{ $user->id }})" class="btn-ghost btn-xs" tooltip="Impersonnifier" />
                            <x-button icon="o-trash" wire:click="confirmDelete({{ $user->id }})" class="btn-ghost btn-xs text-error" tooltip="Supprimer" />
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="text-center py-8 text-base-content/40">Aucun utilisateur trouvé.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="mt-4">{{ $users->links() }}</div>
    </x-card>

    {{-- Delete modal --}}
    <x-modal wire:model="showDeleteModal" title="Supprimer l'utilisateur" class="backdrop-blur">
        <p class="text-base-content/70">Cette action est irréversible. L'utilisateur ne pourra plus se connecter.</p>
        <x-slot:actions>
            <x-button label="Annuler" wire:click="$set('showDeleteModal', false)" class="btn-ghost" />
            <x-button label="Supprimer" wire:click="deleteUser" icon="o-trash" class="btn-error" spinner="deleteUser" />
        </x-slot:actions>
    </x-modal>
</div>
