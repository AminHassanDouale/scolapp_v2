<?php
use App\Models\SchoolClass;
use App\Models\Grade;
use App\Models\AcademicYear;
use App\Models\Teacher;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Mary\Traits\Toast;

new #[Layout('layouts.app')] class extends Component {
    use Toast;

    public int    $yearFilter  = 0;
    public int    $gradeFilter = 0;
    public bool   $showCreate  = false;
    public bool   $showEdit    = false;
    public int    $editId      = 0;

    // Form
    public string $cf_name            = '';
    public int    $cf_grade_id        = 0;
    public int    $cf_academic_year_id = 0;
    public int    $cf_main_teacher_id  = 0;
    public string $cf_room            = '';
    public int    $cf_capacity        = 30;

    public function mount(): void
    {
        $currentYear = AcademicYear::where('school_id', auth()->user()->school_id)
            ->where('is_current', true)->first();
        if ($currentYear) {
            $this->yearFilter              = $currentYear->id;
            $this->cf_academic_year_id     = $currentYear->id;
        }
    }

    public function createClass(): void
    {
        $this->validate([
            'cf_name'             => 'required|string|max:100',
            'cf_grade_id'         => 'required|integer|min:1',
            'cf_academic_year_id' => 'required|integer|min:1',
            'cf_capacity'         => 'required|integer|min:1',
        ]);

        SchoolClass::create([
            'school_id'       => auth()->user()->school_id,
            'name'            => $this->cf_name,
            'grade_id'        => $this->cf_grade_id,
            'academic_year_id'=> $this->cf_academic_year_id,
            'main_teacher_id' => $this->cf_main_teacher_id ?: null,
            'room'            => $this->cf_room ?: null,
            'capacity'        => $this->cf_capacity,
        ]);

        $this->resetForm();
        $this->showCreate = false;
        $this->success('Classe créée.', position: 'toast-top toast-end', icon: 'o-plus-circle', css: 'alert-success', timeout: 3000);
    }

    public function editClass(int $id): void
    {
        $class = SchoolClass::findOrFail($id);
        $this->editId               = $id;
        $this->cf_name              = $class->name;
        $this->cf_grade_id          = $class->grade_id;
        $this->cf_academic_year_id  = $class->academic_year_id;
        $this->cf_main_teacher_id   = $class->main_teacher_id ?? 0;
        $this->cf_room              = $class->room ?? '';
        $this->cf_capacity          = $class->capacity ?? 30;
        $this->showEdit             = true;
    }

    public function updateClass(): void
    {
        $this->validate([
            'cf_name'     => 'required|string|max:100',
            'cf_grade_id' => 'required|integer|min:1',
            'cf_capacity' => 'required|integer|min:1',
        ]);

        SchoolClass::findOrFail($this->editId)->update([
            'name'            => $this->cf_name,
            'grade_id'        => $this->cf_grade_id,
            'academic_year_id'=> $this->cf_academic_year_id,
            'main_teacher_id' => $this->cf_main_teacher_id ?: null,
            'room'            => $this->cf_room ?: null,
            'capacity'        => $this->cf_capacity,
        ]);

        $this->showEdit = false;
        $this->resetForm();
        $this->success('Classe mise à jour.', position: 'toast-top toast-end', icon: 'o-pencil-square', css: 'alert-success', timeout: 3000);
    }

    public function deleteClass(int $id): void
    {
        $class = SchoolClass::withCount('enrollments')->findOrFail($id);
        if ($class->enrollments_count > 0) {
            $this->error('Impossible de supprimer une classe avec des inscriptions.', position: 'toast-top toast-center', icon: 'o-no-symbol', css: 'alert-error', timeout: 4000);
            return;
        }
        $class->delete();
        $this->success('Classe supprimée.', position: 'toast-top toast-end', icon: 'o-trash', css: 'alert-success', timeout: 3000);
    }

    private function resetForm(): void
    {
        $this->cf_name = $this->cf_room = '';
        $this->cf_grade_id = $this->cf_main_teacher_id = $this->editId = 0;
        $this->cf_capacity = 30;
    }

    public function with(): array
    {
        $schoolId = auth()->user()->school_id;

        $classes = SchoolClass::where('school_id', $schoolId)
            ->with(['grade.academicCycle', 'academicYear', 'mainTeacher'])
            ->withCount('enrollments')
            ->when($this->yearFilter,  fn($q) => $q->where('academic_year_id', $this->yearFilter))
            ->when($this->gradeFilter, fn($q) => $q->where('grade_id', $this->gradeFilter))
            ->orderBy('name')
            ->get();

        $grades   = Grade::where('school_id', $schoolId)->with('academicCycle')->orderBy('name')->get();
        $teachers = Teacher::where('school_id', $schoolId)->orderBy('name')->get()
            ->map(fn($t) => ['id' => $t->id, 'name' => $t->full_name])->all();

        return [
            'classes'       => $classes,
            'grades'        => $grades,
            'teachers'      => $teachers,
            'academicYears' => AcademicYear::where('school_id', $schoolId)->orderByDesc('start_date')->get(),
        ];
    }
};
?>

<div>
    <x-header title="Classes" separator progress-indicator>
        <x-slot:actions>
            <div class="flex items-center gap-2">
                <x-select wire:model.live="yearFilter"
                          :options="$academicYears" option-value="id" option-label="name"
                          placeholder="Toutes les années" placeholder-value="0"
                          class="select-sm w-44" />
                <x-button label="Nouvelle classe" icon="o-plus"
                          wire:click="$set('showCreate', true)"
                          class="btn-primary" />
            </div>
        </x-slot:actions>
    </x-header>

    {{-- Grade filter pills --}}
    <div class="flex gap-2 flex-wrap mb-4">
        <button wire:click="$set('gradeFilter', 0)"
                class="btn btn-sm {{ $gradeFilter === 0 ? 'btn-primary' : 'btn-outline' }}">Tous</button>
        @foreach($grades as $grade)
        <button wire:click="$set('gradeFilter', {{ $grade->id }})"
                class="btn btn-sm {{ $gradeFilter === $grade->id ? 'btn-primary' : 'btn-outline' }}">
            {{ $grade->name }}
        </button>
        @endforeach
    </div>

    {{-- Classes grid --}}
    @if($classes->isEmpty())
    <div class="text-center py-16 text-base-content/40">
        <x-icon name="o-building-library" class="w-16 h-16 mx-auto mb-3 opacity-20" />
        <p class="font-semibold">Aucune classe</p>
        <p class="text-sm mt-1 mb-4">Créez des classes pour l'année scolaire sélectionnée.</p>
        <x-button label="Créer une classe" icon="o-plus" wire:click="$set('showCreate', true)" class="btn-primary" />
    </div>
    @else
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        @foreach($classes as $class)
        @php $fillRate = $class->capacity > 0 ? min(100, round(($class->enrollments_count / $class->capacity) * 100)) : 0; @endphp
        <x-card wire:key="class-{{ $class->id }}" class="hover:shadow-md transition-shadow">
            <div class="flex items-start justify-between mb-2">
                <div>
                    <h3 class="font-black text-lg">{{ $class->name }}</h3>
                    <div class="flex items-center gap-1 mt-0.5">
                        <x-badge :value="$class->grade?->name" class="badge-ghost badge-xs" />
                        @if($class->academicYear?->is_current)
                        <x-badge value="En cours" class="badge-primary badge-xs" />
                        @endif
                    </div>
                </div>
                <div class="flex gap-1">
                    <x-button icon="o-pencil" wire:click="editClass({{ $class->id }})"
                              class="btn-ghost btn-xs" />
                    <x-button icon="o-trash" wire:click="deleteClass({{ $class->id }})"
                              wire:confirm="Supprimer cette classe ?"
                              class="btn-ghost btn-xs text-error" />
                </div>
            </div>

            <div class="space-y-1.5 text-sm">
                @if($class->mainTeacher)
                <p class="text-base-content/60 text-xs">
                    <x-icon name="o-user" class="w-3 h-3 inline mr-1"/>{{ $class->mainTeacher->full_name }}
                </p>
                @endif
                @if($class->room)
                <p class="text-base-content/60 text-xs">
                    <x-icon name="o-map-pin" class="w-3 h-3 inline mr-1"/>Salle {{ $class->room }}
                </p>
                @endif
            </div>

            <div class="mt-3">
                <div class="flex justify-between text-xs text-base-content/60 mb-1">
                    <span>{{ $class->enrollments_count }} / {{ $class->capacity }} élèves</span>
                    <span>{{ $fillRate }}%</span>
                </div>
                <progress class="progress {{ $fillRate >= 90 ? 'progress-error' : ($fillRate >= 70 ? 'progress-warning' : 'progress-primary') }} w-full h-1.5"
                          value="{{ $class->enrollments_count }}" max="{{ $class->capacity }}"></progress>
            </div>
        </x-card>
        @endforeach
    </div>
    @endif

    {{-- Create modal --}}
    <x-modal wire:model="showCreate" title="Nouvelle classe" separator>
        <x-form wire:submit="createClass" class="space-y-4">
            <x-input label="Nom de la classe *" wire:model="cf_name" placeholder="6ème A" required />
            <div class="grid grid-cols-2 gap-4">
                <x-select label="Niveau *" wire:model="cf_grade_id"
                          :options="$grades" option-value="id" option-label="name"
                          placeholder="Choisir..." placeholder-value="0" required />
                <x-select label="Année scolaire *" wire:model="cf_academic_year_id"
                          :options="$academicYears" option-value="id" option-label="name"
                          placeholder="Choisir..." placeholder-value="0" required />
            </div>
            <div class="grid grid-cols-2 gap-4">
                <x-select label="Enseignant principal" wire:model="cf_main_teacher_id"
                          :options="$teachers" option-value="id" option-label="name"
                          placeholder="Aucun" placeholder-value="0" />
                <x-input label="Salle" wire:model="cf_room" placeholder="A101" />
            </div>
            <x-input label="Capacité (élèves)" wire:model="cf_capacity" type="number" min="1" />
            <x-slot:actions>
                <x-button label="Annuler" @click="$wire.showCreate = false" class="btn-ghost" />
                <x-button label="Créer" type="submit" icon="o-plus" class="btn-primary" spinner />
            </x-slot:actions>
        </x-form>
    </x-modal>

    {{-- Edit modal --}}
    <x-modal wire:model="showEdit" title="Modifier la classe" separator>
        <x-form wire:submit="updateClass" class="space-y-4">
            <x-input label="Nom *" wire:model="cf_name" required />
            <div class="grid grid-cols-2 gap-4">
                <x-select label="Niveau *" wire:model="cf_grade_id"
                          :options="$grades" option-value="id" option-label="name" required />
                <x-select label="Année scolaire" wire:model="cf_academic_year_id"
                          :options="$academicYears" option-value="id" option-label="name" />
            </div>
            <div class="grid grid-cols-2 gap-4">
                <x-select label="Enseignant principal" wire:model="cf_main_teacher_id"
                          :options="$teachers" option-value="id" option-label="name"
                          placeholder="Aucun" placeholder-value="0" />
                <x-input label="Salle" wire:model="cf_room" />
            </div>
            <x-input label="Capacité" wire:model="cf_capacity" type="number" min="1" />
            <x-slot:actions>
                <x-button label="Annuler" @click="$wire.showEdit = false" class="btn-ghost" />
                <x-button label="Enregistrer" type="submit" icon="o-check" class="btn-primary" spinner />
            </x-slot:actions>
        </x-form>
    </x-modal>
</div>
