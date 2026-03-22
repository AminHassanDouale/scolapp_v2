<?php
use App\Models\Assessment;
use App\Models\Subject;
use App\Models\SchoolClass;
use App\Models\AcademicYear;
use App\Enums\AssessmentType;
use Illuminate\Support\Str;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new #[Layout('layouts.app')] class extends Component {
    use Toast, WithPagination;

    public string $search        = '';
    public int    $subjectFilter = 0;
    public int    $classFilter   = 0;
    public string $typeFilter    = '';
    public bool   $showFilters   = false;

    public function updatingSearch(): void { $this->resetPage(); }

    public function deleteAssessment(int $id): void
    {
        Assessment::findOrFail($id)->delete();
        $this->success('Évaluation supprimée.', position: 'toast-top toast-end', icon: 'o-trash', css: 'alert-success', timeout: 3000);
    }

    public function with(): array
    {
        $schoolId    = auth()->user()->school_id;
        $currentYear = AcademicYear::where('school_id', $schoolId)->where('is_current', true)->first();

        $assessments = Assessment::where('school_id', $schoolId)
            ->with(['subject', 'schoolClass.grade'])
            ->when($this->search, fn($q) => $q->where('title', 'like', "%{$this->search}%"))
            ->when($this->subjectFilter, fn($q) => $q->where('subject_id', $this->subjectFilter))
            ->when($this->classFilter,   fn($q) => $q->where('school_class_id', $this->classFilter))
            ->when($this->typeFilter,    fn($q) => $q->where('type', $this->typeFilter))
            ->orderByDesc('assessment_date')
            ->paginate(20);

        $typeCounts = Assessment::where('school_id', $schoolId)
            ->selectRaw('type, COUNT(*) as cnt')
            ->groupBy('type')
            ->pluck('cnt', 'type');

        return [
            'assessments'  => $assessments,
            'typeCounts'   => $typeCounts,
            'subjects'     => Subject::where('school_id', $schoolId)->orderBy('name')->get(),
            'classes'      => SchoolClass::where('school_id', $schoolId)
                ->when($currentYear, fn($q) => $q->where('academic_year_id', $currentYear->id))
                ->with('grade')->orderBy('name')->get(),
            'assessmentTypes' => collect(AssessmentType::cases())->map(fn($t) => ['id' => $t->value, 'name' => $t->label()])->all(),
        ];
    }
};
?>

<div>
    <x-header title="Évaluations" separator progress-indicator>
        <x-slot:actions>
            <x-button icon="o-adjustments-horizontal" wire:click="$set('showFilters', true)"
                      class="btn-ghost" tooltip="Filtres" />
            <x-button label="Nouvelle évaluation" icon="o-plus"
                      :link="route('admin.assessments.create')" wire:navigate
                      class="btn-primary" />
        </x-slot:actions>
    </x-header>

    {{-- Type stats --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-3 mb-6">
        @foreach(AssessmentType::cases() as $type)
        @php $cnt = $typeCounts[$type->value] ?? 0; @endphp
        <button wire:click="$set('typeFilter', '{{ $typeFilter === $type->value ? '' : $type->value }}')"
                class="rounded-xl bg-linear-to-br {{ $type->gradient() }} p-3 text-left transition-all hover:shadow-md
                       {{ $typeFilter === $type->value ? 'ring-2 ring-offset-2 ring-base-content/30 scale-95' : '' }}">
            <x-icon name="{{ $type->icon() }}" class="w-4 h-4 opacity-80 mb-1" />
            <p class="text-[11px] opacity-80 leading-tight">{{ $type->label() }}</p>
            <p class="text-xl font-black mt-0.5">{{ $cnt }}</p>
        </button>
        @endforeach
    </div>

    {{-- Search --}}
    <div class="flex gap-3 mb-4">
        <x-input wire:model.live.debounce="search" placeholder="Rechercher une évaluation..."
                 icon="o-magnifying-glass" clearable class="flex-1" />
    </div>

    {{-- Table --}}
    <x-card>
        <div class="overflow-x-auto"><table class="table w-full">
            <thead><tr>
                <th>Titre</th>
                <th>Type</th>
                <th>Matière</th>
                <th>Classe</th>
                <th>Date</th>
                <th class="text-center">Note max</th>
                <th class="text-center">Coeff.</th>
                <th class="w-20">Actions</th>
            </tr></thead><tbody>

            @forelse($assessments as $assessment)
            @php $typeObj = $assessment->type; @endphp
            <tr wire:key="assessment-{{ $assessment->id }}" class="hover">
                <td>
                    <a href="{{ route('admin.assessments.show', $assessment->uuid) }}"
                       wire:navigate class="font-semibold hover:text-primary flex items-center gap-1.5">
                        @if($typeObj)
                        <x-icon name="{{ $typeObj->icon() }}" class="w-3.5 h-3.5 shrink-0 text-base-content/40" />
                        @endif
                        {{ $assessment->title }}
                        @if($assessment->file_path)
                        <x-icon name="o-paper-clip" class="w-3 h-3 text-primary/60 shrink-0" title="Fichier joint" />
                        @endif
                    </a>
                    @if($assessment->instructions)
                    <p class="text-xs text-base-content/50 truncate max-w-xs mt-0.5">{{ Str::limit($assessment->instructions, 60) }}</p>
                    @endif
                </td>
                <td>
                    <x-badge :value="$assessment->type?->label()"
                             class="{{ $assessment->type?->color() ?? 'badge-outline' }} badge-sm" />
                </td>
                <td>
                    <span class="px-2 py-0.5 rounded-lg text-xs font-semibold"
                          style="background-color: {{ $assessment->subject?->color ?? '#6366f1' }}20; color: {{ $assessment->subject?->color ?? '#6366f1' }}">
                        {{ $assessment->subject?->name }}
                    </span>
                </td>
                <td class="text-sm">
                    <div>{{ $assessment->schoolClass?->name }}</div>
                    <div class="text-xs text-base-content/40">{{ $assessment->schoolClass?->grade?->name }}</div>
                </td>
                <td class="text-sm">{{ $assessment->assessment_date?->format('d/m/Y') }}</td>
                <td class="text-center font-bold text-primary">{{ $assessment->max_score }}</td>
                <td class="text-center text-base-content/70">×{{ $assessment->coefficient }}</td>
                <td>
                    <div class="flex gap-1">
                        <x-button icon="o-eye" :link="route('admin.assessments.show', $assessment->uuid)"
                                  class="btn-ghost btn-xs" wire:navigate tooltip="Voir" />
                        <x-button icon="o-trash" wire:click="deleteAssessment({{ $assessment->id }})"
                                  wire:confirm="Supprimer cette évaluation et toutes ses notes ?"
                                  class="btn-ghost btn-xs text-error" tooltip="Supprimer" />
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="8" class="text-center py-12 text-base-content/40">
                    <x-icon name="o-clipboard-document-list" class="w-12 h-12 mx-auto mb-2 opacity-20" />
                    <p>Aucune évaluation</p>
                </td>
            </tr>
            @endforelse
        </tbody></table></div>
        <div class="mt-4">{{ $assessments->links() }}</div>
    </x-card>

    {{-- Filters drawer --}}
    <x-drawer wire:model="showFilters" title="Filtres" position="right" class="w-72">
        <div class="p-4 space-y-4">
            <x-select label="Type" wire:model.live="typeFilter"
                      :options="$assessmentTypes" option-value="id" option-label="name"
                      placeholder="Tous" placeholder-value="" />
            <x-select label="Matière" wire:model.live="subjectFilter"
                      :options="$subjects" option-value="id" option-label="name"
                      placeholder="Toutes" placeholder-value="0" />
            <x-select label="Classe" wire:model.live="classFilter"
                      :options="$classes" option-value="id" option-label="name"
                      placeholder="Toutes" placeholder-value="0" />
        </div>
        <x-slot:actions>
            <x-button label="Réinitialiser"
                      wire:click="$set('typeFilter',''); $set('subjectFilter',0); $set('classFilter',0); $set('showFilters',false)"
                      class="btn-ghost" />
            <x-button label="Fermer" wire:click="$set('showFilters', false)" class="btn-primary" />
        </x-slot:actions>
    </x-drawer>

</div>
