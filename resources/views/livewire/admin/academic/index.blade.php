<?php
use App\Models\AcademicYear;
use App\Models\AcademicCycle;
use App\Models\Grade;
use App\Models\SchoolClass;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Mary\Traits\Toast;

new #[Layout('layouts.app')] class extends Component {
    use Toast;

    public bool   $showCreateYear = false;
    public string $cy_name        = '';
    public string $cy_start       = '';
    public string $cy_end         = '';
    public bool   $cy_is_current  = false;

    public function createYear(): void
    {
        $this->validate([
            'cy_name'  => 'required|string|max:100',
            'cy_start' => 'required|date',
            'cy_end'   => 'required|date|after:cy_start',
        ]);

        $schoolId = auth()->user()->school_id;

        if ($this->cy_is_current) {
            AcademicYear::where('school_id', $schoolId)->update(['is_current' => false]);
        }

        AcademicYear::create([
            'school_id'  => $schoolId,
            'name'       => $this->cy_name,
            'start_date' => $this->cy_start,
            'end_date'   => $this->cy_end,
            'is_current' => $this->cy_is_current,
            'is_active'  => true,
        ]);

        $this->reset(['cy_name','cy_start','cy_end','cy_is_current','showCreateYear']);
        $this->success('Année scolaire créée.', position: 'toast-top toast-end', icon: 'o-plus-circle', css: 'alert-success', timeout: 3000);
    }

    public function setCurrentYear(int $id): void
    {
        $schoolId = auth()->user()->school_id;
        AcademicYear::where('school_id', $schoolId)->update(['is_current' => false]);
        AcademicYear::findOrFail($id)->update(['is_current' => true]);
        $this->success('Année scolaire définie comme actuelle.', position: 'toast-top toast-end', icon: 'o-check-circle', css: 'alert-success', timeout: 3000);
    }

    public function with(): array
    {
        $schoolId    = auth()->user()->school_id;
        $currentYear = AcademicYear::where('school_id', $schoolId)->where('is_current', true)->first();

        return [
            'academicYears' => AcademicYear::where('school_id', $schoolId)
                ->withCount('schoolClasses')
                ->orderByDesc('start_date')
                ->get(),
            'currentYear'   => $currentYear,
            'cycles'        => AcademicCycle::where('school_id', $schoolId)
                ->withCount('grades')
                ->with(['grades' => fn($q) => $q->withCount('schoolClasses')->orderBy('order')])
                ->orderBy('order')
                ->get(),
            'stats' => [
                'years'   => AcademicYear::where('school_id', $schoolId)->count(),
                'cycles'  => AcademicCycle::where('school_id', $schoolId)->count(),
                'grades'  => Grade::where('school_id', $schoolId)->count(),
                'classes' => SchoolClass::where('school_id', $schoolId)
                    ->when($currentYear, fn($q) => $q->where('academic_year_id', $currentYear->id))
                    ->count(),
            ],
        ];
    }
};
?>

<div>
    <x-header title="Structure académique" separator progress-indicator>
        <x-slot:actions>
            <x-button label="Nouvelle année" icon="o-plus"
                      wire:click="$set('showCreateYear', true)"
                      class="btn-primary" />
        </x-slot:actions>
    </x-header>

    {{-- Quick nav --}}
    <div class="flex gap-2 mb-6 flex-wrap">
        <a href="{{ route('admin.academic.cycles') }}" wire:navigate
           class="btn btn-outline btn-sm">
            <x-icon name="o-squares-2x2" class="w-4 h-4 mr-1" /> Cycles
        </a>
        <a href="{{ route('admin.academic.grades') }}" wire:navigate
           class="btn btn-outline btn-sm">
            <x-icon name="o-academic-cap" class="w-4 h-4 mr-1" /> Niveaux
        </a>
        <a href="{{ route('admin.academic.classes') }}" wire:navigate
           class="btn btn-outline btn-sm">
            <x-icon name="o-building-library" class="w-4 h-4 mr-1" /> Classes
        </a>
        <a href="{{ route('admin.academic.subjects') }}" wire:navigate
           class="btn btn-outline btn-sm">
            <x-icon name="o-book-open" class="w-4 h-4 mr-1" /> Matières
        </a>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <div class="rounded-2xl bg-gradient-to-br from-primary to-primary/70 p-4 text-primary-content">
            <p class="text-sm opacity-80">Années scolaires</p>
            <p class="text-3xl font-black mt-1">{{ $stats['years'] }}</p>
        </div>
        <div class="rounded-2xl bg-gradient-to-br from-secondary to-secondary/70 p-4 text-secondary-content">
            <p class="text-sm opacity-80">Cycles</p>
            <p class="text-3xl font-black mt-1">{{ $stats['cycles'] }}</p>
        </div>
        <div class="rounded-2xl bg-gradient-to-br from-accent to-accent/70 p-4 text-accent-content">
            <p class="text-sm opacity-80">Niveaux</p>
            <p class="text-3xl font-black mt-1">{{ $stats['grades'] }}</p>
        </div>
        <div class="rounded-2xl bg-gradient-to-br from-info to-info/70 p-4 text-info-content">
            <p class="text-sm opacity-80">Classes (année en cours)</p>
            <p class="text-3xl font-black mt-1">{{ $stats['classes'] }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Academic years --}}
        <x-card title="Années scolaires" separator>
            <div class="space-y-2">
                @forelse($academicYears as $year)
                <div wire:key="year-{{ $year->id }}" class="flex items-center justify-between p-3 rounded-xl
                            {{ $year->is_current ? 'bg-primary/10 border border-primary/30' : 'bg-base-200/50' }}">
                    <div>
                        <div class="flex items-center gap-2">
                            <p class="font-bold">{{ $year->name }}</p>
                            @if($year->is_current)
                            <x-badge value="En cours" class="badge-primary badge-xs" />
                            @endif
                        </div>
                        <p class="text-xs text-base-content/60">
                            {{ $year->start_date instanceof \Illuminate\Support\Carbon ? $year->start_date->format('d/m/Y') : $year->start_date }}
                            →
                            {{ $year->end_date instanceof \Illuminate\Support\Carbon ? $year->end_date->format('d/m/Y') : $year->end_date }}
                            · {{ $year->school_classes_count }} classe(s)
                        </p>
                    </div>
                    @if(!$year->is_current)
                    <x-button label="Définir comme actuelle"
                              wire:click="setCurrentYear({{ $year->id }})"
                              wire:confirm="Définir {{ $year->name }} comme l'année en cours ?"
                              class="btn-outline btn-xs" />
                    @endif
                </div>
                @empty
                <p class="text-center text-base-content/40 py-6">Aucune année scolaire</p>
                @endforelse
            </div>
            <x-button label="Nouvelle année scolaire" icon="o-plus"
                      wire:click="$set('showCreateYear', true)"
                      class="btn-outline btn-sm w-full mt-3" />
        </x-card>

        {{-- Cycles tree --}}
        <x-card title="Cycles & Niveaux" separator>
            <div class="space-y-3">
                @forelse($cycles as $cycle)
                <div wire:key="cycle-{{ $cycle->id }}">
                    <div class="flex items-center justify-between p-2 bg-base-200 rounded-lg mb-1">
                        <span class="font-bold text-sm">{{ $cycle->name }}</span>
                        <x-badge :value="$cycle->grades_count . ' niveau(x)'" class="badge-ghost badge-xs" />
                    </div>
                    @foreach($cycle->grades as $grade)
                    <div class="flex items-center justify-between py-1.5 px-4 border-l-2 border-base-300 ml-3">
                        <span class="text-sm">{{ $grade->name }}</span>
                        <span class="text-xs text-base-content/50">{{ $grade->school_classes_count }} classe(s)</span>
                    </div>
                    @endforeach
                </div>
                @empty
                <p class="text-center text-base-content/40 py-6">Aucun cycle défini</p>
                @endforelse
            </div>
            <a href="{{ route('admin.academic.cycles') }}" wire:navigate>
                <x-button label="Gérer les cycles" icon="o-arrow-right"
                          class="btn-outline btn-sm w-full mt-3" />
            </a>
        </x-card>
    </div>

    {{-- Create Year modal --}}
    <x-modal wire:model="showCreateYear" title="Nouvelle année scolaire" separator>
        <x-form wire:submit="createYear" class="space-y-4">
            <x-input label="Nom *" wire:model="cy_name" placeholder="2025-2026" required />
            <div class="grid grid-cols-2 gap-4">
                <x-datepicker label="Date de début *" wire:model="cy_start" icon="o-calendar" required :config="['dateFormat' => 'Y-m-d', 'altFormat' => 'd/m/Y', 'altInput' => true, 'locale' => ['firstDayOfWeek' => 1]]" />
                <x-datepicker label="Date de fin *" wire:model="cy_end" icon="o-calendar" required :config="['dateFormat' => 'Y-m-d', 'altFormat' => 'd/m/Y', 'altInput' => true, 'locale' => ['firstDayOfWeek' => 1]]" />
            </div>
            <x-checkbox label="Définir comme année en cours" wire:model="cy_is_current" />
            <x-slot:actions>
                <x-button label="Annuler" @click="$wire.showCreateYear = false" class="btn-ghost" />
                <x-button label="Créer" type="submit" icon="o-plus" class="btn-primary" spinner />
            </x-slot:actions>
        </x-form>
    </x-modal>
</div>
