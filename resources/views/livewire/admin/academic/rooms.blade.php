<?php
use App\Models\Room;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Validation\Rule;
use Mary\Traits\Toast;

new #[Layout('layouts.app')] class extends Component {
    use Toast;

    public string $search      = '';
    public string $typeFilter  = '';
    public string $stateFilter = '';   // '', 'active', 'inactive'
    public bool   $showCreate  = false;
    public bool   $showEdit    = false;
    public int    $editId       = 0;

    // Form
    public string  $cf_name      = '';
    public string  $cf_code      = '';
    public string  $cf_type      = 'classroom';
    public ?int    $cf_capacity  = null;
    public bool    $cf_is_active = true;

    private function schoolId(): int
    {
        return (int) auth()->user()->school_id;
    }

    protected function formRules(?int $ignoreId = null): array
    {
        return [
            'cf_name'     => ['required', 'string', 'max:100',
                Rule::unique('rooms', 'name')
                    ->where('school_id', $this->schoolId())
                    ->ignore($ignoreId)
                    ->whereNull('deleted_at')],
            'cf_code'     => ['nullable', 'string', 'max:20'],
            'cf_type'     => ['required', Rule::in(array_keys(Room::$types))],
            'cf_capacity' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ];
    }

    public function createRoom(): void
    {
        abort_unless(auth()->user()->can('academic.manage'), 403);
        $this->validate($this->formRules());

        Room::create([
            'school_id' => $this->schoolId(),
            'name'      => $this->cf_name,
            'code'      => $this->cf_code ?: null,
            'type'      => $this->cf_type,
            'capacity'  => $this->cf_capacity,
            'is_active' => $this->cf_is_active,
        ]);

        $this->resetForm();
        $this->showCreate = false;
        $this->success('Salle créée.', position: 'toast-top toast-end', icon: 'o-plus-circle', css: 'alert-success', timeout: 3000);
    }

    public function editRoom(int $id): void
    {
        $room = Room::where('school_id', $this->schoolId())->findOrFail($id);
        $this->editId       = $id;
        $this->cf_name      = $room->name;
        $this->cf_code      = $room->code ?? '';
        $this->cf_type      = $room->type;
        $this->cf_capacity  = $room->capacity;
        $this->cf_is_active = (bool) $room->is_active;
        $this->showEdit     = true;
    }

    public function updateRoom(): void
    {
        abort_unless(auth()->user()->can('academic.manage'), 403);
        $this->validate($this->formRules($this->editId));

        Room::where('school_id', $this->schoolId())->findOrFail($this->editId)->update([
            'name'      => $this->cf_name,
            'code'      => $this->cf_code ?: null,
            'type'      => $this->cf_type,
            'capacity'  => $this->cf_capacity,
            'is_active' => $this->cf_is_active,
        ]);

        $this->showEdit = false;
        $this->resetForm();
        $this->success('Salle mise à jour.', position: 'toast-top toast-end', icon: 'o-pencil-square', css: 'alert-success', timeout: 3000);
    }

    public function toggleActive(int $id): void
    {
        abort_unless(auth()->user()->can('academic.manage'), 403);
        $room = Room::where('school_id', $this->schoolId())->findOrFail($id);
        $room->update(['is_active' => ! $room->is_active]);
        $this->success($room->is_active ? 'Salle activée.' : 'Salle désactivée.', position: 'toast-top toast-end', icon: 'o-bolt', css: 'alert-success', timeout: 2500);
    }

    public function deleteRoom(int $id): void
    {
        abort_unless(auth()->user()->can('academic.manage'), 403);
        Room::where('school_id', $this->schoolId())->findOrFail($id)->delete();
        $this->success('Salle supprimée.', position: 'toast-top toast-end', icon: 'o-trash', css: 'alert-success', timeout: 3000);
    }

    private function resetForm(): void
    {
        $this->cf_name = $this->cf_code = '';
        $this->cf_type = 'classroom';
        $this->cf_capacity = null;
        $this->cf_is_active = true;
        $this->editId = 0;
    }

    public function with(): array
    {
        return [
            'rooms' => Room::where('school_id', $this->schoolId())
                ->when($this->search, fn($q) => $q->where(fn($w) =>
                    $w->where('name', 'like', "%{$this->search}%")->orWhere('code', 'like', "%{$this->search}%")))
                ->when($this->typeFilter, fn($q) => $q->where('type', $this->typeFilter))
                ->when($this->stateFilter === 'active',   fn($q) => $q->where('is_active', true))
                ->when($this->stateFilter === 'inactive', fn($q) => $q->where('is_active', false))
                ->orderBy('name')
                ->get(),
            'typeOptions'  => collect(Room::$types)->map(fn($label, $id) => ['id' => $id, 'name' => $label])->values()->all(),
            'stateOptions' => [
                ['id' => '',         'name' => 'Toutes'],
                ['id' => 'active',   'name' => 'Actives'],
                ['id' => 'inactive', 'name' => 'Inactives'],
            ],
            'canManage' => auth()->user()->can('academic.manage'),
        ];
    }
};
?>

<div>
    <x-header title="Salles" subtitle="Salles de classe, laboratoires et espaces — activez/désactivez pour la sélection" separator progress-indicator>
        <x-slot:actions>
            @if($canManage)
            <x-button label="Nouvelle salle" icon="o-plus" wire:click="$set('showCreate', true)" class="btn-primary" />
            @endif
        </x-slot:actions>
    </x-header>

    <div class="flex flex-col sm:flex-row gap-3 mb-4">
        <x-input wire:model.live.debounce="search" placeholder="Rechercher une salle..." icon="o-magnifying-glass" clearable class="flex-1 max-w-sm" />
        <x-select wire:model.live="typeFilter" :options="$typeOptions" placeholder="Tous les types" class="max-w-xs" />
        <x-select wire:model.live="stateFilter" :options="$stateOptions" class="max-w-[10rem]" />
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
        @forelse($rooms as $room)
        <x-card class="{{ !$room->is_active ? 'opacity-60' : '' }} hover:shadow-md transition-shadow">
            <div class="flex items-start justify-between mb-3">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-primary/10 flex items-center justify-center">
                        <x-icon name="o-building-office-2" class="w-4 h-4 text-primary" />
                    </div>
                    @if($room->code)<span class="text-xs font-mono font-bold text-base-content/60">{{ $room->code }}</span>@endif
                </div>
                @if($canManage)
                <div class="flex gap-0.5">
                    <x-button icon="o-pencil" wire:click="editRoom({{ $room->id }})" class="btn-ghost btn-xs" />
                    <x-button icon="o-trash" wire:click="deleteRoom({{ $room->id }})" wire:confirm="Supprimer cette salle ?" class="btn-ghost btn-xs text-error" />
                </div>
                @endif
            </div>

            <h3 class="font-bold mb-1">{{ $room->name }}</h3>
            <div class="flex items-center gap-3 text-xs text-base-content/60 mb-3">
                <span>{{ $room->type_label }}</span>
                @if($room->capacity)<span>· {{ $room->capacity }} places</span>@endif
            </div>

            <div class="flex items-center justify-between">
                @if($room->is_active)
                <x-badge value="Active" class="badge-success badge-xs" />
                @else
                <x-badge value="Inactive" class="badge-ghost badge-xs" />
                @endif
                @if($canManage)
                <input type="checkbox" class="toggle toggle-success toggle-xs"
                       @checked($room->is_active)
                       wire:click="toggleActive({{ $room->id }})"
                       title="Activer/désactiver pour la sélection" />
                @endif
            </div>
        </x-card>
        @empty
        <div class="col-span-full text-center py-16 text-base-content/40">
            <x-icon name="o-building-office-2" class="w-16 h-16 mx-auto mb-3 opacity-20" />
            <p class="font-semibold">Aucune salle</p>
            <p class="text-sm mt-1 mb-4">Ajoutez les salles de votre école (2 salles A/B par classe sont créées automatiquement à l'inscription).</p>
            @if($canManage)
            <x-button label="Ajouter une salle" icon="o-plus" wire:click="$set('showCreate', true)" class="btn-primary" />
            @endif
        </div>
        @endforelse
    </div>

    {{-- Create modal --}}
    <x-modal wire:model="showCreate" title="Nouvelle salle" separator>
        <x-form wire:submit="createRoom" class="space-y-4">
            <div class="grid grid-cols-3 gap-4">
                <div class="col-span-2"><x-input label="Nom *" wire:model="cf_name" placeholder="6ème A" required /></div>
                <x-input label="Code" wire:model="cf_code" placeholder="6E-A" maxlength="20" />
            </div>
            <div class="grid grid-cols-2 gap-4">
                <x-select label="Type *" wire:model="cf_type" :options="$typeOptions" />
                <x-input label="Capacité" wire:model="cf_capacity" type="number" min="1" max="1000" placeholder="30" />
            </div>
            <x-checkbox label="Salle active (sélectionnable)" wire:model="cf_is_active" />
            <x-slot:actions>
                <x-button label="Annuler" @click="$wire.showCreate = false" class="btn-ghost" />
                <x-button label="Créer" type="submit" icon="o-plus" class="btn-primary" spinner />
            </x-slot:actions>
        </x-form>
    </x-modal>

    {{-- Edit modal --}}
    <x-modal wire:model="showEdit" title="Modifier la salle" separator>
        <x-form wire:submit="updateRoom" class="space-y-4">
            <div class="grid grid-cols-3 gap-4">
                <div class="col-span-2"><x-input label="Nom *" wire:model="cf_name" required /></div>
                <x-input label="Code" wire:model="cf_code" maxlength="20" />
            </div>
            <div class="grid grid-cols-2 gap-4">
                <x-select label="Type *" wire:model="cf_type" :options="$typeOptions" />
                <x-input label="Capacité" wire:model="cf_capacity" type="number" min="1" max="1000" />
            </div>
            <x-checkbox label="Salle active (sélectionnable)" wire:model="cf_is_active" />
            <x-slot:actions>
                <x-button label="Annuler" @click="$wire.showEdit = false" class="btn-ghost" />
                <x-button label="Enregistrer" type="submit" icon="o-check" class="btn-primary" spinner />
            </x-slot:actions>
        </x-form>
    </x-modal>
</div>
