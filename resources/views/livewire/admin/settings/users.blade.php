<?php
use App\Models\User;
use Spatie\Permission\Models\Role;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Mary\Traits\Toast;
use Illuminate\Support\Facades\Hash;

new #[Layout('layouts.app')] class extends Component {
    use Toast, WithPagination;

    public string $search      = '';
    public string $roleFilter  = '';
    public bool   $showCreate  = false;
    public bool   $showEdit    = false;
    public int    $editId      = 0;

    // Form
    public string $cf_name       = '';
    public string $cf_email      = '';
    public string $cf_password   = '';
    public string $cf_role       = 'admin';
    public string $cf_phone      = '';
    public string $cf_whatsapp   = '';
    public bool   $cf_is_blocked = false;

    public function updatingSearch(): void { $this->resetPage(); }

    public function createUser(): void
    {
        $this->validate([
            'cf_name'     => 'required|string|max:200',
            'cf_email'    => 'required|email|unique:users,email',
            'cf_password' => 'required|string|min:8',
            'cf_role'     => 'required|string',
        ]);

        $user = User::create([
            'name'             => $this->cf_name,
            'email'            => $this->cf_email,
            'password'         => Hash::make($this->cf_password),
            'phone'            => $this->cf_phone ?: null,
            'whatsapp_number'  => $this->cf_whatsapp ?: null,
            'school_id'        => auth()->user()->school_id,
        ]);

        $user->assignRole($this->cf_role);

        $this->resetForm();
        $this->showCreate = false;
        $this->success('Utilisateur créé.', position: 'toast-top toast-end', icon: 'o-plus-circle', css: 'alert-success', timeout: 3000);
    }

    public function editUser(int $id): void
    {
        $user = User::with('roles')->findOrFail($id);
        $this->editId        = $id;
        $this->cf_name       = $user->name ?? '';
        $this->cf_email      = $user->email;
        $this->cf_password   = '';
        $this->cf_role       = $user->roles->first()?->name ?? 'admin';
        $this->cf_phone      = $user->phone ?? '';
        $this->cf_whatsapp   = $user->whatsapp_number ?? '';
        $this->cf_is_blocked = $user->is_blocked ?? false;
        $this->showEdit      = true;
    }

    public function updateUser(): void
    {
        $this->validate([
            'cf_name'  => 'required|string|max:200',
            'cf_email' => "required|email|unique:users,email,{$this->editId}",
            'cf_role'  => 'required|string',
        ]);

        $user = User::findOrFail($this->editId);
        $update = [
            'name'            => $this->cf_name,
            'email'           => $this->cf_email,
            'phone'           => $this->cf_phone ?: null,
            'whatsapp_number' => $this->cf_whatsapp ?: null,
        ];

        if ($this->cf_password) {
            $this->validate(['cf_password' => 'min:8']);
            $update['password'] = Hash::make($this->cf_password);
        }

        if ($this->cf_is_blocked !== $user->is_blocked) {
            $update['is_blocked']  = $this->cf_is_blocked;
            $update['blocked_at']  = $this->cf_is_blocked ? now() : null;
        }

        $user->update($update);
        $user->syncRoles([$this->cf_role]);

        $this->showEdit = false;
        $this->resetForm();
        $this->success('Utilisateur mis à jour.', position: 'toast-top toast-end', icon: 'o-pencil-square', css: 'alert-success', timeout: 3000);
    }

    public function toggleBlock(int $id): void
    {
        $user = User::findOrFail($id);
        if ($user->id === auth()->id()) {
            $this->error('Vous ne pouvez pas bloquer votre propre compte.', position: 'toast-top toast-center', icon: 'o-lock-closed', css: 'alert-error', timeout: 4000);
            return;
        }
        $user->update([
            'is_blocked' => !$user->is_blocked,
            'blocked_at' => !$user->is_blocked ? now() : null,
        ]);
        $this->success($user->is_blocked ? 'Compte bloqué.' : 'Compte débloqué.', position: 'toast-top toast-end', icon: 'o-bolt', css: 'alert-success', timeout: 3000);
    }

    public function deleteUser(int $id): void
    {
        if ($id === auth()->id()) {
            $this->error('Vous ne pouvez pas supprimer votre propre compte.', position: 'toast-top toast-center', icon: 'o-no-symbol', css: 'alert-error', timeout: 4000);
            return;
        }
        User::findOrFail($id)->delete();
        $this->success('Utilisateur supprimé.', position: 'toast-top toast-end', icon: 'o-trash', css: 'alert-success', timeout: 3000);
    }

    private function resetForm(): void
    {
        $this->cf_name = $this->cf_email = $this->cf_password = '';
        $this->cf_phone = $this->cf_whatsapp = '';
        $this->cf_role = 'admin';
        $this->cf_is_blocked = false;
        $this->editId = 0;
    }

    public function with(): array
    {
        $schoolId = auth()->user()->school_id;

        $users = User::where('school_id', $schoolId)
            ->with('roles')
            ->when($this->search, fn($q) =>
                $q->where('name', 'like', "%{$this->search}%")
                  ->orWhere('email', 'like', "%{$this->search}%")
            )
            ->when($this->roleFilter, fn($q) => $q->whereHas('roles', fn($r) => $r->where('name', $this->roleFilter)))
            ->orderBy('name')
            ->paginate(20);

        $roles = Role::orderBy('name')->get();
        $roleOptions = $roles->map(fn($r) => ['id' => $r->name, 'name' => $r->name])->all();

        return [
            'users'       => $users,
            'roles'       => $roles,
            'roleOptions' => $roleOptions,
        ];
    }
};
?>

<div>
    <x-header title="Utilisateurs" separator progress-indicator>
        <x-slot:actions>
            <x-button label="Nouvel utilisateur" icon="o-plus"
                      wire:click="$set('showCreate', true)"
                      class="btn-primary" />
        </x-slot:actions>
    </x-header>

    {{-- Filters --}}
    <div class="flex items-center gap-3 mb-4">
        <x-input wire:model.live.debounce="search" placeholder="Rechercher (nom, email)..."
                 icon="o-magnifying-glass" clearable class="flex-1" />
        <x-select wire:model.live="roleFilter" :options="$roleOptions" option-value="id" option-label="name"
                  placeholder="Tous les rôles" placeholder-value="" class="select-sm w-44" />
    </div>

    {{-- Table --}}
    <x-card>
        <div class="overflow-x-auto"><table class="table w-full">
            <thead><tr>
                <th>Utilisateur</th>
                <th>Email</th>
                <th>Rôle</th>
                <th>Statut</th>
                <th>Dernière connexion</th>
                <th class="w-28">Actions</th>
            </tr></thead><tbody>

            @forelse($users as $user)
            @php
                $roleName  = $user->roles->first()?->name ?? '—';
                $roleClass = match($roleName) {
                    'super-admin' => 'badge-error',
                    'admin'       => 'badge-primary',
                    'director'    => 'badge-secondary',
                    'teacher'     => 'badge-accent',
                    'accountant'  => 'badge-warning',
                    'guardian'    => 'badge-ghost',
                    default       => 'badge-ghost',
                };
            @endphp
            <tr class="hover">
                <td>
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-full bg-primary/10 flex items-center justify-center font-bold text-primary text-sm">
                            {{ substr($user->name ?? $user->email ?? '?', 0, 1) }}
                        </div>
                        <p class="font-semibold text-sm">{{ $user->name ?? '—' }}</p>
                    </div>
                </td>
                <td class="text-sm">{{ $user->email }}</td>
                <td><x-badge :value="$roleName" class="{{ $roleClass }} badge-sm" /></td>
                <td>
                    @if($user->is_blocked)
                    <x-badge value="Bloqué" class="badge-error badge-sm" />
                    @else
                    <x-badge value="Actif" class="badge-success badge-sm" />
                    @endif
                </td>
                <td class="text-sm text-base-content/60">
                    {{ $user->last_login_at?->diffForHumans() ?? 'Jamais' }}
                </td>
                <td>
                    <div class="flex gap-1">
                        <x-button icon="o-pencil" wire:click="editUser({{ $user->id }})"
                                  class="btn-ghost btn-xs" tooltip="Modifier" />
                        <x-button :icon="$user->is_blocked ? 'o-lock-open' : 'o-lock-closed'"
                                  wire:click="toggleBlock({{ $user->id }})"
                                  wire:confirm="{{ $user->is_blocked ? 'Débloquer cet utilisateur ?' : 'Bloquer cet utilisateur ?' }}"
                                  class="btn-ghost btn-xs {{ $user->is_blocked ? 'text-success' : 'text-warning' }}"
                                  :tooltip="$user->is_blocked ? 'Débloquer' : 'Bloquer'" />
                        @if($user->id !== auth()->id())
                        <x-button icon="o-trash" wire:click="deleteUser({{ $user->id }})"
                                  wire:confirm="Supprimer cet utilisateur ?"
                                  class="btn-ghost btn-xs text-error" tooltip="Supprimer" />
                        @endif
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="text-center py-10 text-base-content/40">Aucun utilisateur trouvé</td>
            </tr>
            @endforelse
        </tbody></table></div>
        <div class="mt-4">{{ $users->links() }}</div>
    </x-card>

    {{-- Create modal --}}
    <x-modal wire:model="showCreate" title="Nouvel utilisateur" separator>
        <x-form wire:submit="createUser" class="space-y-4">
            <x-input label="Nom *" wire:model="cf_name" required />
            <x-input label="Email *" wire:model="cf_email" type="email" required />
            <x-input label="Mot de passe *" wire:model="cf_password" type="password" required />
            <x-select label="Rôle *" wire:model="cf_role"
                      :options="$roleOptions" option-value="id" option-label="name" />
            <div class="grid grid-cols-2 gap-4">
                <x-input label="Téléphone" wire:model="cf_phone"
                         placeholder="+253 77 00 00 00" icon="o-phone" />
                <x-input label="WhatsApp" wire:model="cf_whatsapp"
                         placeholder="+253 77 00 00 00" icon="o-chat-bubble-left-ellipsis" />
            </div>
            <x-slot:actions>
                <x-button label="Annuler" @click="$wire.showCreate = false" class="btn-ghost" />
                <x-button label="Créer" type="submit" icon="o-check" class="btn-primary" spinner />
            </x-slot:actions>
        </x-form>
    </x-modal>

    {{-- Edit modal --}}
    <x-modal wire:model="showEdit" title="Modifier l'utilisateur" separator>
        <x-form wire:submit="updateUser" class="space-y-4">
            <x-input label="Nom *" wire:model="cf_name" required />
            <x-input label="Email *" wire:model="cf_email" type="email" required />
            <x-input label="Nouveau mot de passe (laisser vide pour ne pas changer)"
                     wire:model="cf_password" type="password" />
            <x-select label="Rôle *" wire:model="cf_role"
                      :options="$roleOptions" option-value="id" option-label="name" />
            <div class="grid grid-cols-2 gap-4">
                <x-input label="Téléphone" wire:model="cf_phone"
                         placeholder="+253 77 00 00 00" icon="o-phone" />
                <x-input label="WhatsApp" wire:model="cf_whatsapp"
                         placeholder="+253 77 00 00 00" icon="o-chat-bubble-left-ellipsis" />
            </div>
            <x-checkbox label="Compte bloqué" wire:model="cf_is_blocked" />
            <x-slot:actions>
                <x-button label="Annuler" @click="$wire.showEdit = false" class="btn-ghost" />
                <x-button label="Enregistrer" type="submit" icon="o-check" class="btn-primary" spinner />
            </x-slot:actions>
        </x-form>
    </x-modal>
</div>
