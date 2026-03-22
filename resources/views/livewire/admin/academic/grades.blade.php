<?php
use App\Models\Grade;
use App\Models\AcademicCycle;
use App\Models\SchoolClass;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Mary\Traits\Toast;

new #[Layout('layouts.app')] class extends Component {
    use Toast;

    public int    $cycleFilter  = 0;
    public bool   $showCreate   = false;
    public bool   $showEdit     = false;
    public int    $editId       = 0;

    public string $cf_name             = '';
    public string $cf_code             = '';
    public int    $cf_academic_cycle_id = 0;
    public int    $cf_order            = 1;

    public function createGrade(): void
    {
        $this->validate([
            'cf_name'             => 'required|string|max:100',
            'cf_code'             => 'required|string|max:20',
            'cf_academic_cycle_id' => 'required|integer|min:1',
            'cf_order'            => 'required|integer|min:1',
        ]);

        Grade::create([
            'school_id'        => auth()->user()->school_id,
            'academic_cycle_id' => $this->cf_academic_cycle_id,
            'name'             => $this->cf_name,
            'code'             => $this->cf_code,
            'order'            => $this->cf_order,
        ]);

        $this->reset(['cf_name', 'cf_code', 'cf_academic_cycle_id', 'cf_order', 'showCreate']);
        $this->success('Niveau créé.', position: 'toast-top toast-end', icon: 'o-plus-circle', css: 'alert-success', timeout: 3000);
    }

    public function editGrade(int $id): void
    {
        $grade = Grade::findOrFail($id);
        $this->editId               = $id;
        $this->cf_name              = $grade->name;
        $this->cf_code              = $grade->code;
        $this->cf_academic_cycle_id = $grade->academic_cycle_id;
        $this->cf_order             = $grade->order;
        $this->showEdit             = true;
    }

    public function updateGrade(): void
    {
        $this->validate([
            'cf_name'             => 'required|string|max:100',
            'cf_code'             => 'required|string|max:20',
            'cf_academic_cycle_id' => 'required|integer|min:1',
        ]);

        Grade::findOrFail($this->editId)->update([
            'name'             => $this->cf_name,
            'code'             => $this->cf_code,
            'academic_cycle_id' => $this->cf_academic_cycle_id,
            'order'            => $this->cf_order,
        ]);

        $this->reset(['cf_name', 'cf_code', 'cf_academic_cycle_id', 'cf_order', 'showEdit', 'editId']);
        $this->success('Niveau mis à jour.', position: 'toast-top toast-end', icon: 'o-pencil-square', css: 'alert-success', timeout: 3000);
    }

    public function deleteGrade(int $id): void
    {
        $grade = Grade::withCount('schoolClasses')->findOrFail($id);
        if ($grade->school_classes_count > 0) {
            $this->error('Impossible de supprimer un niveau contenant des classes.', position: 'toast-top toast-center', icon: 'o-no-symbol', css: 'alert-error', timeout: 4000);
            return;
        }
        $grade->delete();
        $this->success('Niveau supprimé.', position: 'toast-top toast-end', icon: 'o-trash', css: 'alert-success', timeout: 3000);
    }

    public function with(): array
    {
        $schoolId = auth()->user()->school_id;

        $grades = Grade::where('school_id', $schoolId)
            ->with(['academicCycle'])
            ->withCount('schoolClasses')
            ->when($this->cycleFilter, fn($q) => $q->where('academic_cycle_id', $this->cycleFilter))
            ->orderBy('academic_cycle_id')
            ->orderBy('order')
            ->get();

        $cycles = AcademicCycle::where('school_id', $schoolId)->orderBy('order')->get();

        return [
            'grades' => $grades,
            'cycles' => $cycles,
        ];
    }
};
?>

<div>
    <x-header title="Niveaux scolaires" separator progress-indicator>
        <x-slot:actions>
            <x-button label="Nouveau niveau" icon="o-plus"
                      wire:click="$set('showCreate', true)"
                      class="btn-primary" />
        </x-slot:actions>
    </x-header>

    {{-- Cycle filter --}}
    <div class="flex gap-2 flex-wrap mb-4">
        <button wire:click="$set('cycleFilter', 0)"
                class="btn btn-sm {{ $cycleFilter === 0 ? 'btn-primary' : 'btn-outline' }}">
            Tous
        </button>
        @foreach($cycles as $cycle)
        <button wire:click="$set('cycleFilter', {{ $cycle->id }})"
                class="btn btn-sm {{ $cycleFilter === $cycle->id ? 'btn-primary' : 'btn-outline' }}">
            {{ $cycle->name }}
        </button>
        @endforeach
    </div>

    {{-- Grades grouped by cycle --}}
    @php $grouped = $grades->groupBy('academic_cycle_id'); @endphp
    @forelse($grouped as $cycleId => $cycleGrades)
    @php $cycleName = $cycleGrades->first()->academicCycle?->name ?? 'Sans cycle'; @endphp
    <div wire:key="cycle-group-{{ $cycleId }}" class="mb-6">
        <h3 class="font-bold text-base-content/60 text-sm uppercase tracking-wider mb-3">{{ $cycleName }}</h3>
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
            @foreach($cycleGrades as $grade)
            <x-card wire:key="grade-{{ $grade->id }}" class="hover:shadow-md transition-shadow">
                <div class="flex items-start justify-between">
                    <div>
                        <span class="badge badge-outline badge-xs font-mono mb-1">{{ $grade->code }}</span>
                        <h4 class="font-bold">{{ $grade->name }}</h4>
                        <p class="text-sm text-base-content/60 mt-1">
                            <x-icon name="o-building-library" class="w-3 h-3 inline" />
                            {{ $grade->school_classes_count }} classe(s)
                        </p>
                    </div>
                    <div class="flex flex-col gap-1">
                        <x-button icon="o-pencil" wire:click="editGrade({{ $grade->id }})"
                                  class="btn-ghost btn-xs" tooltip="Modifier" />
                        <x-button icon="o-trash" wire:click="deleteGrade({{ $grade->id }})"
                                  wire:confirm="Supprimer ce niveau ?"
                                  class="btn-ghost btn-xs text-error" tooltip="Supprimer" />
                    </div>
                </div>
            </x-card>
            @endforeach
        </div>
    </div>
    @empty
    <div class="text-center py-16 text-base-content/40">
        <x-icon name="o-academic-cap" class="w-16 h-16 mx-auto mb-3 opacity-20" />
        <p class="font-semibold">Aucun niveau trouvé</p>
        <p class="text-sm mt-1">Créez des niveaux et associez-les à des cycles.</p>
    </div>
    @endforelse

    {{-- Create modal --}}
    <x-modal wire:model="showCreate" title="Nouveau niveau" separator>
        <x-form wire:submit="createGrade" class="space-y-4">
            <x-select
                label="Cycle *"
                wire:model="cf_academic_cycle_id"
                :options="$cycles"
                option-value="id"
                option-label="name"
                placeholder="Choisir un cycle..."
                placeholder-value="0"
                required
            />
            <div class="grid grid-cols-2 gap-4">
                <x-input label="Nom *" wire:model="cf_name" placeholder="6ème" required />
                <x-input label="Code *" wire:model="cf_code" placeholder="6EME" maxlength="20" required />
            </div>
            <x-input label="Ordre" wire:model="cf_order" type="number" min="1" />
            <x-slot:actions>
                <x-button label="Annuler" @click="$wire.showCreate = false" class="btn-ghost" />
                <x-button label="Créer" type="submit" icon="o-plus" class="btn-primary" spinner />
            </x-slot:actions>
        </x-form>
    </x-modal>

    {{-- Edit modal --}}
    <x-modal wire:model="showEdit" title="Modifier le niveau" separator>
        <x-form wire:submit="updateGrade" class="space-y-4">
            <x-select
                label="Cycle *"
                wire:model="cf_academic_cycle_id"
                :options="$cycles"
                option-value="id"
                option-label="name"
                required
            />
            <div class="grid grid-cols-2 gap-4">
                <x-input label="Nom *" wire:model="cf_name" required />
                <x-input label="Code *" wire:model="cf_code" maxlength="20" required />
            </div>
            <x-input label="Ordre" wire:model="cf_order" type="number" min="1" />
            <x-slot:actions>
                <x-button label="Annuler" @click="$wire.showEdit = false" class="btn-ghost" />
                <x-button label="Enregistrer" type="submit" icon="o-check" class="btn-primary" spinner />
            </x-slot:actions>
        </x-form>
    </x-modal>
</div>
