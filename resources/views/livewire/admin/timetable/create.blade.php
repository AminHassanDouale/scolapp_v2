<?php

use App\Models\TimetableTemplate;
use App\Models\SchoolClass;
use App\Models\AcademicYear;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Mary\Traits\Toast;

new #[Layout('layouts.app')] class extends Component {
    use Toast;

    #[Rule('required|string|max:120')]
    public string $name = '';

    #[Rule('required|integer|exists:school_classes,id')]
    public int $school_class_id = 0;

    #[Rule('required|integer|exists:academic_years,id')]
    public int $academic_year_id = 0;

    public bool $is_active = true;

    public function mount(): void
    {
        $schoolId = auth()->user()->school_id;
        $year     = AcademicYear::where('school_id', $schoolId)->where('is_current', true)->first();
        if ($year) {
            $this->academic_year_id = $year->id;
        }

        // Pre-select class from query param
        if (request()->has('class')) {
            $this->school_class_id = (int) request('class');
        }
    }

    public function save(): void
    {
        $this->validate();

        $schoolId = auth()->user()->school_id;

        // Check for duplicates
        $exists = TimetableTemplate::where('school_id', $schoolId)
            ->where('school_class_id', $this->school_class_id)
            ->where('academic_year_id', $this->academic_year_id)
            ->where('name', $this->name)
            ->exists();

        if ($exists) {
            $this->addError('name', 'Un emploi du temps avec ce nom existe déjà pour cette classe.');
            return;
        }

        $template = TimetableTemplate::create([
            'school_id'        => $schoolId,
            'school_class_id'  => $this->school_class_id,
            'academic_year_id' => $this->academic_year_id,
            'name'             => $this->name,
            'is_active'        => $this->is_active,
        ]);

        session()->flash('success', 'Emploi du temps créé. Ajoutez maintenant les créneaux horaires.');
        $this->redirect(route('admin.timetable.show', $template->uuid), navigate: true);
    }

    public function with(): array
    {
        $schoolId    = auth()->user()->school_id;
        $currentYear = AcademicYear::where('school_id', $schoolId)->where('is_current', true)->first();

        return [
            'classes' => SchoolClass::where('school_id', $schoolId)
                ->with(['grade', 'academicYear'])
                ->when($currentYear, fn($q) => $q->where('academic_year_id', $currentYear->id))
                ->orderBy('name')->get(),
            'years' => AcademicYear::where('school_id', $schoolId)->orderByDesc('start_date')->get(),
        ];
    }
};
?>

<div>
    <x-header title="Nouveau emploi du temps" separator progress-indicator>
        <x-slot:actions>
            <x-button label="Annuler" icon="o-arrow-left"
                      :link="route('admin.timetable.index')"
                      class="btn-ghost" wire:navigate />
        </x-slot:actions>
    </x-header>

    <div class="max-w-xl mx-auto">
        <x-card shadow>
            <x-form wire:submit="save">
                <x-input wire:model="name"
                         label="Nom de la grille"
                         placeholder="Ex: Emploi du temps S1 — 6ème A"
                         icon="o-tag"
                         hint="Donnez un nom descriptif pour identifier cette grille" />

                <x-select wire:model="school_class_id"
                          label="Classe"
                          :options="$classes"
                          option-value="id"
                          option-label="name"
                          placeholder="Sélectionner une classe"
                          placeholder-value="0"
                          icon="o-academic-cap" />

                <x-select wire:model="academic_year_id"
                          label="Année scolaire"
                          :options="$years"
                          option-value="id"
                          option-label="name"
                          placeholder="Sélectionner une année"
                          placeholder-value="0"
                          icon="o-calendar" />

                <x-toggle wire:model="is_active" label="Activer immédiatement" />

                <x-slot:actions>
                    <x-button label="Créer et configurer" icon-right="o-arrow-right"
                              type="submit" class="btn-primary w-full" spinner="save" />
                </x-slot:actions>
            </x-form>
        </x-card>
    </div>
</div>
