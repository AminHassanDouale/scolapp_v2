<?php
use App\Models\Subject;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Mary\Traits\Toast;

new #[Layout('layouts.app')] class extends Component {
    use Toast;

    public string $search    = '';
    public bool   $showCreate = false;
    public bool   $showEdit   = false;
    public int    $editId     = 0;

    // Form
    public string $cf_name               = '';
    public string $cf_code               = '';
    public string $cf_color              = '#6366f1';
    public float  $cf_default_coefficient = 1;
    public bool   $cf_is_active          = true;

    public function createSubject(): void
    {
        $this->validate([
            'cf_name' => 'required|string|max:100',
            'cf_code' => 'required|string|max:20',
            'cf_default_coefficient' => 'required|numeric|min:0.1|max:10',
        ]);

        Subject::create([
            'school_id'           => auth()->user()->school_id,
            'name'                => $this->cf_name,
            'code'                => $this->cf_code,
            'color'               => $this->cf_color,
            'default_coefficient' => $this->cf_default_coefficient,
            'is_active'           => $this->cf_is_active,
        ]);

        $this->resetForm();
        $this->showCreate = false;
        $this->success('Matière créée.', position: 'toast-top toast-end', icon: 'o-plus-circle', css: 'alert-success', timeout: 3000);
    }

    public function editSubject(int $id): void
    {
        $subject                     = Subject::findOrFail($id);
        $this->editId                = $id;
        $this->cf_name               = $subject->name;
        $this->cf_code               = $subject->code;
        $this->cf_color              = $subject->color ?? '#6366f1';
        $this->cf_default_coefficient = $subject->default_coefficient ?? 1;
        $this->cf_is_active          = $subject->is_active ?? true;
        $this->showEdit              = true;
    }

    public function updateSubject(): void
    {
        $this->validate([
            'cf_name' => 'required|string|max:100',
            'cf_code' => 'required|string|max:20',
        ]);

        Subject::findOrFail($this->editId)->update([
            'name'                => $this->cf_name,
            'code'                => $this->cf_code,
            'color'               => $this->cf_color,
            'default_coefficient' => $this->cf_default_coefficient,
            'is_active'           => $this->cf_is_active,
        ]);

        $this->showEdit = false;
        $this->resetForm();
        $this->success('Matière mise à jour.', position: 'toast-top toast-end', icon: 'o-pencil-square', css: 'alert-success', timeout: 3000);
    }

    public function toggleActive(int $id): void
    {
        $subject = Subject::findOrFail($id);
        $subject->update(['is_active' => !$subject->is_active]);
        $this->success($subject->is_active ? 'Matière désactivée.' : 'Matière activée.', position: 'toast-top toast-end', icon: 'o-bolt', css: 'alert-success', timeout: 3000);
    }

    public function deleteSubject(int $id): void
    {
        Subject::findOrFail($id)->delete();
        $this->success('Matière supprimée.', position: 'toast-top toast-end', icon: 'o-trash', css: 'alert-success', timeout: 3000);
    }

    private function resetForm(): void
    {
        $this->cf_name = $this->cf_code = '';
        $this->cf_color = '#6366f1';
        $this->cf_default_coefficient = 1;
        $this->cf_is_active = true;
        $this->editId = 0;
    }

    public function with(): array
    {
        return [
            'subjects' => Subject::where('school_id', auth()->user()->school_id)
                ->withCount(['teachers', 'assessments'])
                ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('code', 'like', "%{$this->search}%"))
                ->orderBy('name')
                ->get(),
        ];
    }
};
?>

<div>
    <x-header title="Matières" separator progress-indicator>
        <x-slot:actions>
            <x-button label="Nouvelle matière" icon="o-plus"
                      wire:click="$set('showCreate', true)"
                      class="btn-primary" />
        </x-slot:actions>
    </x-header>

    <div class="flex gap-3 mb-4">
        <x-input wire:model.live.debounce="search" placeholder="Rechercher une matière..."
                 icon="o-magnifying-glass" clearable class="flex-1 max-w-sm" />
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
        @forelse($subjects as $subject)
        <x-card class="{{ !$subject->is_active ? 'opacity-60' : '' }} hover:shadow-md transition-shadow">
            <div class="flex items-start justify-between mb-3">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg"
                         style="background-color: {{ $subject->color ?? '#6366f1' }}30; border: 2px solid {{ $subject->color ?? '#6366f1' }}">
                    </div>
                    <span class="text-xs font-mono font-bold text-base-content/60">{{ $subject->code }}</span>
                </div>
                <div class="flex gap-0.5">
                    <x-button icon="o-pencil" wire:click="editSubject({{ $subject->id }})"
                              class="btn-ghost btn-xs" />
                    <x-button icon="o-trash" wire:click="deleteSubject({{ $subject->id }})"
                              wire:confirm="Supprimer cette matière ?"
                              class="btn-ghost btn-xs text-error" />
                </div>
            </div>

            <h3 class="font-bold mb-1" style="color: {{ $subject->color ?? '#6366f1' }}">{{ $subject->name }}</h3>

            <div class="flex items-center gap-3 text-xs text-base-content/60 mb-2">
                <span>×{{ $subject->default_coefficient }}</span>
                <span>{{ $subject->teachers_count }} prof(s)</span>
                <span>{{ $subject->assessments_count }} éval(s)</span>
            </div>

            <div class="flex items-center justify-between">
                @if($subject->is_active)
                <x-badge value="Active" class="badge-success badge-xs" />
                @else
                <x-badge value="Inactive" class="badge-ghost badge-xs" />
                @endif
                <input type="checkbox" class="toggle toggle-success toggle-xs"
                       :checked="{{ $subject->is_active ? 'true' : 'false' }}"
                       wire:click="toggleActive({{ $subject->id }})" />
            </div>
        </x-card>
        @empty
        <div class="col-span-full text-center py-16 text-base-content/40">
            <x-icon name="o-book-open" class="w-16 h-16 mx-auto mb-3 opacity-20" />
            <p class="font-semibold">Aucune matière</p>
            <p class="text-sm mt-1 mb-4">Ajoutez les matières enseignées dans votre école.</p>
            <x-button label="Ajouter une matière" icon="o-plus" wire:click="$set('showCreate', true)" class="btn-primary" />
        </div>
        @endforelse
    </div>

    {{-- Create modal --}}
    <x-modal wire:model="showCreate" title="Nouvelle matière" separator>
        <x-form wire:submit="createSubject" class="space-y-4">
            <div class="grid grid-cols-3 gap-4">
                <div class="col-span-2">
                    <x-input label="Nom *" wire:model="cf_name" placeholder="Mathématiques" required />
                </div>
                <x-input label="Code *" wire:model="cf_code" placeholder="MATH" maxlength="20" required />
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="label"><span class="label-text">Couleur</span></label>
                    <div class="flex items-center gap-2">
                        <input type="color" wire:model.live="cf_color" class="w-10 h-10 rounded-lg cursor-pointer border border-base-300" />
                        <span class="text-sm font-mono">{{ $cf_color }}</span>
                    </div>
                </div>
                <x-input label="Coefficient par défaut" wire:model="cf_default_coefficient"
                         type="number" step="0.1" min="0.1" max="10" />
            </div>
            <x-checkbox label="Matière active" wire:model="cf_is_active" />
            <x-slot:actions>
                <x-button label="Annuler" @click="$wire.showCreate = false" class="btn-ghost" />
                <x-button label="Créer" type="submit" icon="o-plus" class="btn-primary" spinner />
            </x-slot:actions>
        </x-form>
    </x-modal>

    {{-- Edit modal --}}
    <x-modal wire:model="showEdit" title="Modifier la matière" separator>
        <x-form wire:submit="updateSubject" class="space-y-4">
            <div class="grid grid-cols-3 gap-4">
                <div class="col-span-2">
                    <x-input label="Nom *" wire:model="cf_name" required />
                </div>
                <x-input label="Code *" wire:model="cf_code" maxlength="20" required />
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="label"><span class="label-text">Couleur</span></label>
                    <div class="flex items-center gap-2">
                        <input type="color" wire:model.live="cf_color" class="w-10 h-10 rounded-lg cursor-pointer border border-base-300" />
                        <span class="text-sm font-mono">{{ $cf_color }}</span>
                    </div>
                </div>
                <x-input label="Coefficient" wire:model="cf_default_coefficient"
                         type="number" step="0.1" min="0.1" max="10" />
            </div>
            <x-checkbox label="Matière active" wire:model="cf_is_active" />
            <x-slot:actions>
                <x-button label="Annuler" @click="$wire.showEdit = false" class="btn-ghost" />
                <x-button label="Enregistrer" type="submit" icon="o-check" class="btn-primary" spinner />
            </x-slot:actions>
        </x-form>
    </x-modal>
</div>
