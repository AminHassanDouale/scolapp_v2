<?php
use App\Models\AcademicCycle;
use App\Models\Grade;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Mary\Traits\Toast;

new #[Layout('layouts.app')] class extends Component {
    use Toast;

    public bool   $showCreate   = false;
    public bool   $showEdit     = false;
    public int    $editId       = 0;
    public bool   $showGrades   = false;
    public int    $activeCycleId = 0;

    // Cycle form
    public string $cf_name      = '';
    public string $cf_code      = '';
    public int    $cf_order     = 1;
    public bool   $cf_is_active = true;

    // Grade form (within cycle)
    public bool   $showCreateGrade = false;
    public string $gf_name         = '';
    public string $gf_code         = '';
    public int    $gf_order        = 1;

    public function createCycle(): void
    {
        $this->validate([
            'cf_name'  => 'required|string|max:100',
            'cf_code'  => 'required|string|max:20',
            'cf_order' => 'required|integer|min:1',
        ]);

        AcademicCycle::create([
            'school_id' => auth()->user()->school_id,
            'name'      => $this->cf_name,
            'code'      => $this->cf_code,
            'order'     => $this->cf_order,
            'is_active' => $this->cf_is_active,
        ]);

        $this->reset(['cf_name', 'cf_code', 'cf_order', 'cf_is_active', 'showCreate']);
        $this->success('Cycle créé.', position: 'toast-top toast-end', icon: 'o-plus-circle', css: 'alert-success', timeout: 3000);
    }

    public function editCycle(int $id): void
    {
        $cycle = AcademicCycle::findOrFail($id);
        $this->editId    = $id;
        $this->cf_name   = $cycle->name;
        $this->cf_code   = $cycle->code;
        $this->cf_order  = $cycle->order;
        $this->cf_is_active = $cycle->is_active;
        $this->showEdit  = true;
    }

    public function updateCycle(): void
    {
        $this->validate([
            'cf_name'  => 'required|string|max:100',
            'cf_code'  => 'required|string|max:20',
            'cf_order' => 'required|integer|min:1',
        ]);

        AcademicCycle::findOrFail($this->editId)->update([
            'name'      => $this->cf_name,
            'code'      => $this->cf_code,
            'order'     => $this->cf_order,
            'is_active' => $this->cf_is_active,
        ]);

        $this->reset(['cf_name', 'cf_code', 'cf_order', 'cf_is_active', 'showEdit', 'editId']);
        $this->success('Cycle mis à jour.', position: 'toast-top toast-end', icon: 'o-pencil-square', css: 'alert-success', timeout: 3000);
    }

    public function deleteCycle(int $id): void
    {
        $cycle = AcademicCycle::withCount('grades')->findOrFail($id);
        if ($cycle->grades_count > 0) {
            $this->error('Impossible de supprimer un cycle contenant des niveaux.', position: 'toast-top toast-center', icon: 'o-no-symbol', css: 'alert-error', timeout: 4000);
            return;
        }
        $cycle->delete();
        $this->success('Cycle supprimé.', position: 'toast-top toast-end', icon: 'o-trash', css: 'alert-success', timeout: 3000);
    }

    public function openGrades(int $cycleId): void
    {
        $this->activeCycleId  = $cycleId;
        $this->showGrades     = true;
        $this->showCreateGrade = false;
        $this->reset(['gf_name', 'gf_code', 'gf_order']);
    }

    public function createGrade(): void
    {
        $this->validate([
            'gf_name'  => 'required|string|max:100',
            'gf_code'  => 'required|string|max:20',
            'gf_order' => 'required|integer|min:1',
        ]);

        Grade::create([
            'school_id'        => auth()->user()->school_id,
            'academic_cycle_id' => $this->activeCycleId,
            'name'             => $this->gf_name,
            'code'             => $this->gf_code,
            'order'            => $this->gf_order,
        ]);

        $this->reset(['gf_name', 'gf_code', 'gf_order', 'showCreateGrade']);
        $this->success('Niveau ajouté.', position: 'toast-top toast-end', icon: 'o-plus-circle', css: 'alert-success', timeout: 3000);
    }

    public function deleteGrade(int $id): void
    {
        Grade::findOrFail($id)->delete();
        $this->success('Niveau supprimé.', position: 'toast-top toast-end', icon: 'o-trash', css: 'alert-success', timeout: 3000);
    }

    public function with(): array
    {
        $schoolId = auth()->user()->school_id;

        $cycles = AcademicCycle::where('school_id', $schoolId)
            ->withCount('grades')
            ->with(['grades' => fn($q) => $q->withCount('schoolClasses')->orderBy('order')])
            ->orderBy('order')
            ->get();

        $activeGrades = $this->activeCycleId
            ? Grade::where('academic_cycle_id', $this->activeCycleId)
                ->withCount('schoolClasses')
                ->orderBy('order')
                ->get()
            : collect();

        return [
            'cycles'      => $cycles,
            'activeGrades' => $activeGrades,
            'activeCycle' => $this->activeCycleId ? AcademicCycle::find($this->activeCycleId) : null,
        ];
    }
};
?>

<div>
    <x-header title="Cycles académiques" separator progress-indicator>
        <x-slot:actions>
            <x-button label="Nouveau cycle" icon="o-plus"
                      wire:click="$set('showCreate', true)"
                      class="btn-primary" />
        </x-slot:actions>
    </x-header>

    {{-- Cycles grid --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse($cycles as $cycle)
        <x-card wire:key="cycle-{{ $cycle->id }}">
            <div class="flex items-start justify-between mb-3">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <span class="badge badge-outline badge-sm font-mono">{{ $cycle->code }}</span>
                        @if($cycle->is_active)
                        <x-badge value="Actif" class="badge-success badge-xs" />
                        @else
                        <x-badge value="Inactif" class="badge-ghost badge-xs" />
                        @endif
                    </div>
                    <h3 class="font-bold text-lg">{{ $cycle->name }}</h3>
                    <p class="text-sm text-base-content/60">{{ $cycle->grades_count }} niveau(x)</p>
                </div>
                <div class="flex gap-1">
                    <x-button icon="o-pencil" wire:click="editCycle({{ $cycle->id }})"
                              class="btn-ghost btn-xs" tooltip="Modifier" />
                    <x-button icon="o-trash" wire:click="deleteCycle({{ $cycle->id }})"
                              wire:confirm="Supprimer ce cycle ?"
                              class="btn-ghost btn-xs text-error" tooltip="Supprimer" />
                </div>
            </div>

            {{-- Grades list --}}
            <div class="space-y-1 mb-3">
                @foreach($cycle->grades as $grade)
                <div class="flex items-center justify-between py-1 px-2 rounded-lg bg-base-200/50">
                    <span class="text-sm">
                        <span class="font-mono text-xs text-base-content/50 mr-2">{{ $grade->code }}</span>
                        {{ $grade->name }}
                    </span>
                    <span class="text-xs text-base-content/50">{{ $grade->school_classes_count }} classe(s)</span>
                </div>
                @endforeach
            </div>

            <x-button label="Gérer les niveaux" icon="o-academic-cap"
                      wire:click="openGrades({{ $cycle->id }})"
                      class="btn-outline btn-sm w-full" />
        </x-card>
        @empty
        <div class="col-span-3 text-center py-16 text-base-content/40">
            <x-icon name="o-academic-cap" class="w-16 h-16 mx-auto mb-3 opacity-20" />
            <p class="font-semibold">Aucun cycle académique</p>
            <p class="text-sm mt-1">Créez vos cycles (Primaire, Collège, Lycée…)</p>
        </div>
        @endforelse
    </div>

    {{-- Create cycle modal --}}
    <x-modal wire:model="showCreate" title="Nouveau cycle" separator>
        <x-form wire:submit="createCycle" class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <x-input label="Nom *" wire:model="cf_name" placeholder="Primaire" required />
                <x-input label="Code *" wire:model="cf_code" placeholder="PRI" maxlength="20" required />
            </div>
            <x-input label="Ordre d'affichage" wire:model="cf_order" type="number" min="1" />
            <x-checkbox label="Cycle actif" wire:model="cf_is_active" />
            <x-slot:actions>
                <x-button label="Annuler" @click="$wire.showCreate = false" class="btn-ghost" />
                <x-button label="Créer" type="submit" icon="o-plus" class="btn-primary" spinner />
            </x-slot:actions>
        </x-form>
    </x-modal>

    {{-- Edit cycle modal --}}
    <x-modal wire:model="showEdit" title="Modifier le cycle" separator>
        <x-form wire:submit="updateCycle" class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <x-input label="Nom *" wire:model="cf_name" required />
                <x-input label="Code *" wire:model="cf_code" maxlength="20" required />
            </div>
            <x-input label="Ordre d'affichage" wire:model="cf_order" type="number" min="1" />
            <x-checkbox label="Cycle actif" wire:model="cf_is_active" />
            <x-slot:actions>
                <x-button label="Annuler" @click="$wire.showEdit = false" class="btn-ghost" />
                <x-button label="Enregistrer" type="submit" icon="o-check" class="btn-primary" spinner />
            </x-slot:actions>
        </x-form>
    </x-modal>

    {{-- Grades drawer --}}
    <x-drawer wire:model="showGrades" :title="'Niveaux — ' . ($activeCycle?->name ?? '')" position="right" class="w-96">
        <div class="p-4 space-y-3">
            @forelse($activeGrades as $grade)
            <div class="flex items-center justify-between p-3 bg-base-200 rounded-xl">
                <div>
                    <p class="font-semibold">{{ $grade->name }}</p>
                    <p class="text-xs text-base-content/60">{{ $grade->code }} · {{ $grade->school_classes_count }} classe(s)</p>
                </div>
                <x-button icon="o-trash" wire:click="deleteGrade({{ $grade->id }})"
                          wire:confirm="Supprimer ce niveau ?"
                          class="btn-ghost btn-xs text-error" />
            </div>
            @empty
            <p class="text-center text-base-content/40 py-8">Aucun niveau dans ce cycle.</p>
            @endforelse

            {{-- Add grade form --}}
            @if($showCreateGrade)
            <x-form wire:submit="createGrade" class="space-y-3 border-t pt-4 mt-4">
                <p class="font-semibold text-sm">Nouveau niveau</p>
                <x-input label="Nom" wire:model="gf_name" placeholder="6ème" required />
                <x-input label="Code" wire:model="gf_code" placeholder="6EME" maxlength="20" required />
                <x-input label="Ordre" wire:model="gf_order" type="number" min="1" />
                <div class="flex gap-2">
                    <x-button label="Annuler" wire:click="$set('showCreateGrade', false)" class="btn-ghost btn-sm" />
                    <x-button label="Ajouter" type="submit" class="btn-primary btn-sm" spinner />
                </div>
            </x-form>
            @else
            <x-button label="Ajouter un niveau" icon="o-plus"
                      wire:click="$set('showCreateGrade', true)"
                      class="btn-outline btn-sm w-full mt-2" />
            @endif
        </div>
    </x-drawer>
</div>
