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

    public TimetableTemplate $template;

    #[Rule('required|string|max:120')]
    public string $name = '';

    #[Rule('required|integer|exists:school_classes,id')]
    public int $school_class_id = 0;

    #[Rule('required|integer|exists:academic_years,id')]
    public int $academic_year_id = 0;

    public bool $is_active = true;

    public function mount(string $uuid): void
    {
        $this->template = TimetableTemplate::where('uuid', $uuid)
            ->where('school_id', auth()->user()->school_id)
            ->firstOrFail();

        $this->name             = $this->template->name;
        $this->school_class_id  = $this->template->school_class_id;
        $this->academic_year_id = $this->template->academic_year_id;
        $this->is_active        = $this->template->is_active;
    }

    public function save(): void
    {
        $this->validate();

        $this->template->update([
            'name'             => $this->name,
            'school_class_id'  => $this->school_class_id,
            'academic_year_id' => $this->academic_year_id,
            'is_active'        => $this->is_active,
        ]);

        session()->flash('success', 'Emploi du temps mis à jour.');
        $this->redirect(route('admin.timetable.show', $this->template->uuid), navigate: true);
    }

    public function with(): array
    {
        $schoolId    = auth()->user()->school_id;
        $currentYear = AcademicYear::where('school_id', $schoolId)->where('is_current', true)->first();

        return [
            'classes' => SchoolClass::where('school_id', $schoolId)
                ->with(['grade'])
                ->orderBy('name')->get(),
            'years' => AcademicYear::where('school_id', $schoolId)->orderByDesc('start_date')->get(),
        ];
    }
};
?>

<div>
    <x-header :title="'Modifier : ' . $template->name" separator progress-indicator>
        <x-slot:actions>
            <x-button label="Annuler" icon="o-arrow-left"
                      :link="route('admin.timetable.show', $template->uuid)"
                      class="btn-ghost" wire:navigate />
        </x-slot:actions>
    </x-header>

    <div class="max-w-xl mx-auto">
        <x-card shadow>
            <x-form wire:submit="save">
                <x-input wire:model="name"
                         label="Nom de la grille"
                         icon="o-tag" />

                <x-select wire:model="school_class_id"
                          label="Classe"
                          :options="$classes"
                          option-value="id"
                          option-label="name"
                          placeholder-value="0"
                          icon="o-academic-cap" />

                <x-select wire:model="academic_year_id"
                          label="Année scolaire"
                          :options="$years"
                          option-value="id"
                          option-label="name"
                          placeholder-value="0"
                          icon="o-calendar" />

                <x-toggle wire:model="is_active" label="Actif" />

                <x-slot:actions>
                    <x-button label="Enregistrer" icon="o-check"
                              type="submit" class="btn-primary" spinner="save" />
                </x-slot:actions>
            </x-form>
        </x-card>
    </div>
</div>
