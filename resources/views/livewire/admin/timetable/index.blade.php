<?php

use App\Models\TimetableTemplate;
use App\Models\SchoolClass;
use App\Models\AcademicYear;
use App\Models\Grade;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Mary\Traits\Toast;

new #[Layout('layouts.app')] class extends Component {
    use Toast;

    public int $yearFilter  = 0;
    public int $gradeFilter = 0;  // filter by grade (level)

    public function deleteTemplate(int $id): void
    {
        $template = TimetableTemplate::findOrFail($id);
        $template->delete();
        $this->success('Emploi du temps supprimé.', position: 'toast-top toast-end', icon: 'o-trash', css: 'alert-success', timeout: 3000);
    }

    public function toggleActive(int $id): void
    {
        $template = TimetableTemplate::findOrFail($id);
        $template->update(['is_active' => !$template->is_active]);
        $this->success($template->is_active ? 'Activé.' : 'Désactivé.', position: 'toast-top toast-end', icon: 'o-bolt', css: 'alert-success', timeout: 3000);
    }

    public function resetFilters(): void
    {
        $this->yearFilter  = 0;
        $this->gradeFilter = 0;
    }

    public function with(): array
    {
        $schoolId    = auth()->user()->school_id;
        $currentYear = AcademicYear::where('school_id', $schoolId)->where('is_current', true)->first();

        $query = TimetableTemplate::where('school_id', $schoolId)
            ->with(['schoolClass.grade.academicCycle', 'academicYear', 'entries'])
            ->when($this->yearFilter, fn($q) => $q->where('academic_year_id', $this->yearFilter))
            ->unless($this->yearFilter, fn($q) => $q->when($currentYear, fn($q2) => $q2->where('academic_year_id', $currentYear->id)))
            ->when($this->gradeFilter, fn($q) => $q->whereHas('schoolClass', fn($q2) => $q2->where('grade_id', $this->gradeFilter)))
            ->orderBy('is_active', 'desc')
            ->orderBy('name');

        $templates = $query->get();

        // Nested grouping: grade name → class_id → templates
        // Pre-computed to avoid arrow functions inside Blade @foreach directives
        $byGrade = $templates
            ->groupBy(fn($t) => $t->schoolClass?->grade?->name ?? 'Sans niveau')
            ->map(fn($grp) => $grp->groupBy('school_class_id'));

        $stats = [
            'total'   => $templates->count(),
            'active'  => $templates->where('is_active', true)->count(),
            'classes' => $templates->pluck('school_class_id')->unique()->count(),
            'entries' => $templates->sum(fn($t) => $t->entries->count()),
        ];

        // Grades for filter dropdown — grouped by cycle
        $grades = Grade::where('school_id', $schoolId)
            ->with('academicCycle')
            ->orderBy('order')
            ->get()
            ->map(fn($g) => (object)[
                'id'   => $g->id,
                'name' => ($g->academicCycle?->name ?? '') . ' — ' . $g->name,
            ]);

        return [
            'templates'   => $templates,
            'byGrade'     => $byGrade,
            'stats'       => $stats,
            'years'       => AcademicYear::where('school_id', $schoolId)->orderByDesc('start_date')->get(),
            'grades'      => $grades,
            'currentYear' => $currentYear,
        ];
    }
};
?>

<div>
    <x-header title="Emplois du temps" subtitle="Gérez les grilles horaires par classe" separator progress-indicator>
        <x-slot:actions>
            <x-button label="Nouveau" icon="o-plus" :link="route('admin.timetable.create')"
                      class="btn-primary" wire:navigate />
        </x-slot:actions>
    </x-header>

    {{-- Stats --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="rounded-2xl bg-linear-to-br from-primary to-primary/70 p-5 text-primary-content shadow">
            <div class="flex items-center gap-3">
                <x-icon name="o-calendar-days" class="w-8 h-8 opacity-80" />
                <div>
                    <p class="text-xs opacity-75 uppercase tracking-wider">Total</p>
                    <p class="text-3xl font-black">{{ $stats['total'] }}</p>
                </div>
            </div>
        </div>
        <div class="rounded-2xl bg-linear-to-br from-success to-success/70 p-5 text-success-content shadow">
            <div class="flex items-center gap-3">
                <x-icon name="o-check-circle" class="w-8 h-8 opacity-80" />
                <div>
                    <p class="text-xs opacity-75 uppercase tracking-wider">Actifs</p>
                    <p class="text-3xl font-black">{{ $stats['active'] }}</p>
                </div>
            </div>
        </div>
        <div class="rounded-2xl bg-linear-to-br from-info to-info/70 p-5 text-info-content shadow">
            <div class="flex items-center gap-3">
                <x-icon name="o-academic-cap" class="w-8 h-8 opacity-80" />
                <div>
                    <p class="text-xs opacity-75 uppercase tracking-wider">Classes</p>
                    <p class="text-3xl font-black">{{ $stats['classes'] }}</p>
                </div>
            </div>
        </div>
        <div class="rounded-2xl bg-linear-to-br from-warning to-warning/70 p-5 text-warning-content shadow">
            <div class="flex items-center gap-3">
                <x-icon name="o-clock" class="w-8 h-8 opacity-80" />
                <div>
                    <p class="text-xs opacity-75 uppercase tracking-wider">Créneaux</p>
                    <p class="text-3xl font-black">{{ $stats['entries'] }}</p>
                </div>
            </div>
        </div>
    </div>

    @if($templates->isEmpty())
        {{-- Empty state --}}
        <div class="flex flex-col items-center justify-center py-24 text-center">
            <div class="w-24 h-24 rounded-full bg-primary/10 flex items-center justify-center mb-6">
                <x-icon name="o-calendar-days" class="w-12 h-12 text-primary/50" />
            </div>
            <h3 class="text-xl font-bold text-base-content/70 mb-2">Aucun emploi du temps</h3>
            <p class="text-base-content/40 mb-6 max-w-sm">Créez votre premier emploi du temps pour organiser les cours par classe et enseignant.</p>
            <x-button label="Créer un emploi du temps" icon="o-plus"
                      :link="route('admin.timetable.create')" class="btn-primary" wire:navigate />
        </div>
    @else
        {{-- Filters bar --}}
        <div class="flex items-center gap-3 mb-6 flex-wrap">
            <x-select wire:model.live="yearFilter"
                      :options="$years" option-value="id" option-label="name"
                      placeholder="Toutes les années" placeholder-value="0"
                      class="select-sm w-52" />
            <x-select wire:model.live="gradeFilter"
                      :options="$grades" option-value="id" option-label="name"
                      placeholder="Tous les niveaux" placeholder-value="0"
                      class="select-sm w-60" />
            @if($yearFilter || $gradeFilter)
                <x-button wire:click="resetFilters" icon="o-x-mark"
                          class="btn-ghost btn-sm" label="Effacer" />
            @endif
            @if($currentYear && !$yearFilter)
                <x-badge :value="'Année : ' . $currentYear->name" class="badge-primary badge-outline badge-sm" />
            @endif
            @if($gradeFilter)
                <x-badge :value="$grades->firstWhere('id', $gradeFilter)?->name ?? ''" class="badge-info badge-outline badge-sm" />
            @endif
        </div>

        {{-- Grid grouped by grade → class --}}
        @foreach($byGrade as $gradeName => $byClass)
        <div class="mb-8">
            {{-- Grade section header --}}
            <div class="flex items-center gap-3 mb-4">
                <div class="h-px flex-1 bg-base-200"></div>
                <span class="text-xs font-bold uppercase tracking-widest text-base-content/40 px-3">{{ $gradeName }}</span>
                <div class="h-px flex-1 bg-base-200"></div>
            </div>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
            @foreach($byClass as $classId => $classTemplates)
                @php $first = $classTemplates->first(); @endphp
                <div class="card bg-base-100 shadow-md border border-base-200 hover:shadow-lg transition-shadow">
                    <div class="card-body p-0">
                        {{-- Header band --}}
                        <div class="px-5 py-4 bg-linear-to-r from-primary/10 to-transparent border-b border-base-200 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-primary/15 flex items-center justify-center">
                                    <x-icon name="o-academic-cap" class="w-5 h-5 text-primary" />
                                </div>
                                <div>
                                    <h3 class="font-bold text-base-content">{{ $first->schoolClass?->name ?? '—' }}</h3>
                                    @if($first->schoolClass?->grade)
                                        <p class="text-xs text-base-content/50">{{ $first->schoolClass->grade->name }}</p>
                                    @endif
                                </div>
                            </div>
                            <span class="text-xs text-base-content/40">{{ $classTemplates->count() }} grille(s)</span>
                        </div>

                        {{-- Template list --}}
                        <div class="divide-y divide-base-200">
                            @foreach($classTemplates as $tpl)
                            <div wire:key="tpl-{{ $tpl->id }}" class="px-5 py-3 flex items-center justify-between group hover:bg-base-50">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="w-2 h-2 rounded-full shrink-0 {{ $tpl->is_active ? 'bg-success' : 'bg-base-300' }}"></div>
                                    <div class="min-w-0">
                                        <a href="{{ route('admin.timetable.show', $tpl->uuid) }}"
                                           wire:navigate class="font-medium text-sm hover:text-primary truncate block">
                                            {{ $tpl->name }}
                                        </a>
                                        <p class="text-xs text-base-content/40">
                                            {{ $tpl->academicYear?->name ?? '—' }} ·
                                            {{ $tpl->entries->count() }} créneaux
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-1 shrink-0">
                                    <x-button icon="{{ $tpl->is_active ? 'o-pause-circle' : 'o-play-circle' }}"
                                              wire:click="toggleActive({{ $tpl->id }})"
                                              class="btn-ghost btn-xs"
                                              tooltip="{{ $tpl->is_active ? 'Désactiver' : 'Activer' }}" />
                                    <a href="{{ route('admin.timetable.show', $tpl->uuid) }}" wire:navigate>
                                        <x-button icon="o-eye" class="btn-ghost btn-xs" tooltip="Voir" />
                                    </a>
                                    <a href="{{ route('admin.timetable.edit', $tpl->uuid) }}" wire:navigate>
                                        <x-button icon="o-pencil" class="btn-ghost btn-xs" tooltip="Modifier" />
                                    </a>
                                    <x-button icon="o-trash"
                                              wire:click="deleteTemplate({{ $tpl->id }})"
                                              wire:confirm="Supprimer cet emploi du temps ?"
                                              class="btn-ghost btn-xs text-error" tooltip="Supprimer" />
                                </div>
                            </div>
                            @endforeach
                        </div>

                        {{-- Footer --}}
                        <div class="px-5 py-3 border-t border-base-200 bg-base-50">
                            <a href="{{ route('admin.timetable.create') }}?class={{ $first->school_class_id }}" wire:navigate>
                                <x-button label="Ajouter une grille" icon="o-plus" class="btn-ghost btn-xs w-full" />
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>{{-- end grid --}}
        </div>{{-- end grade section --}}
        @endforeach{{-- end byGrade --}}
    @endif
</div>
