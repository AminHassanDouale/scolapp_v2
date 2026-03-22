<?php
use App\Models\Assessment;
use App\Models\Subject;
use App\Models\SchoolClass;
use App\Models\AcademicYear;
use App\Models\Teacher;
use App\Enums\AssessmentType;
use App\Enums\ReportPeriod;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Mary\Traits\Toast;

new #[Layout('layouts.app')] class extends Component {
    use Toast;

    public string $title       = '';
    public string $type        = '';
    public int    $subject_id  = 0;
    public int    $class_id    = 0;
    public string $date        = '';
    public float  $max_score   = 20;
    public float  $coefficient = 1;
    public string $period      = '';
    public int    $teacher_id  = 0;
    public string $description = '';

    public function mount(): void
    {
        $this->date = now()->format('Y-m-d');
    }

    public function selectType(string $type): void
    {
        $this->type = $type;
    }

    public function save(): void
    {
        $this->validate([
            'title'       => 'required|string|max:200',
            'type'        => 'required|in:' . implode(',', array_column(AssessmentType::cases(), 'value')),
            'subject_id'  => 'required|integer|min:1',
            'class_id'    => 'required|integer|min:1',
            'date'        => 'required|date',
            'max_score'   => 'required|numeric|min:1|max:1000',
            'coefficient' => 'required|numeric|min:0.1|max:10',
            'period'      => 'nullable|in:' . implode(',', array_column(ReportPeriod::cases(), 'value')),
        ]);

        $schoolId    = auth()->user()->school_id;
        $currentYear = AcademicYear::where('school_id', $schoolId)->where('is_current', true)->firstOrFail();

        $assessment = Assessment::create([
            'school_id'        => $schoolId,
            'academic_year_id' => $currentYear->id,
            'title'            => $this->title,
            'type'             => $this->type,
            'subject_id'       => $this->subject_id,
            'school_class_id'  => $this->class_id,
            'assessment_date'  => $this->date,
            'max_score'        => $this->max_score,
            'coefficient'      => $this->coefficient,
            'period'           => $this->period ?: null,
            'teacher_id'       => $this->teacher_id ?: null,
            'instructions'     => $this->description ?: null,
        ]);

        $this->success('Évaluation créée avec succès.', position: 'toast-top toast-end', icon: 'o-plus-circle', css: 'alert-success', timeout: 3000);
        $this->redirectRoute('admin.assessments.show', $assessment->id, navigate: true);
    }

    public function with(): array
    {
        $schoolId    = auth()->user()->school_id;
        $currentYear = AcademicYear::where('school_id', $schoolId)->where('is_current', true)->first();

        $subjects = Subject::where('school_id', $schoolId)->where('is_active', true)
            ->orderBy('name')->get()
            ->map(fn($s) => ['id' => $s->id, 'name' => $s->name, 'color' => $s->color ?? '#6366f1'])->all();

        $classes = SchoolClass::where('school_id', $schoolId)
            ->when($currentYear, fn($q) => $q->where('academic_year_id', $currentYear->id))
            ->with('grade')->orderBy('name')->get()
            ->map(fn($c) => ['id' => $c->id, 'name' => $c->name . ($c->grade ? ' — ' . $c->grade->name : '')])->all();

        $teachers = Teacher::where('school_id', $schoolId)->orderBy('name')->get()
            ->map(fn($t) => ['id' => $t->id, 'name' => $t->name])->all();

        $periods = collect(ReportPeriod::cases())
            ->map(fn($p) => ['id' => $p->value, 'name' => $p->label()])->all();

        $selectedSubject = $this->subject_id
            ? Subject::find($this->subject_id)
            : null;

        $selectedType = $this->type
            ? AssessmentType::tryFrom($this->type)
            : null;

        return compact('subjects', 'classes', 'teachers', 'periods', 'selectedSubject', 'selectedType');
    }
};
?>

<div>
    <x-header separator progress-indicator>
        <x-slot:title>
            <div class="flex items-center gap-2 text-sm text-base-content/60">
                <a href="{{ route('admin.assessments.index') }}" wire:navigate class="hover:text-primary">Évaluations</a>
                <x-icon name="o-chevron-right" class="w-3 h-3"/>
                <span class="text-base-content font-semibold">Nouvelle évaluation</span>
            </div>
        </x-slot:title>
    </x-header>

    <x-form wire:submit="save">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- ─── LEFT COLUMN (form) ─────────────────────────── --}}
            <div class="lg:col-span-2 space-y-5">

                {{-- Type picker --}}
                <x-card title="Type d'évaluation" separator>
                    @error('type')
                        <p class="text-error text-sm mb-3">{{ $message }}</p>
                    @enderror
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        @foreach(App\Enums\AssessmentType::cases() as $t)
                        <button type="button" wire:click="selectType('{{ $t->value }}')"
                                class="group relative rounded-xl p-3 text-left border-2 transition-all duration-200
                                       {{ $type === $t->value
                                            ? 'border-primary bg-primary/10 shadow-md scale-[0.97]'
                                            : 'border-base-200 bg-base-100 hover:border-primary/40 hover:bg-base-200/60' }}">
                            <div class="flex items-center justify-between mb-2">
                                <div class="w-8 h-8 rounded-lg flex items-center justify-center bg-linear-to-br {{ $t->gradient() }}">
                                    <x-icon name="{{ $t->icon() }}" class="w-4 h-4" />
                                </div>
                                @if($type === $t->value)
                                <div class="w-4 h-4 rounded-full bg-primary flex items-center justify-center">
                                    <x-icon name="o-check" class="w-2.5 h-2.5 text-primary-content" />
                                </div>
                                @endif
                            </div>
                            <p class="text-xs font-semibold leading-tight text-base-content/80">{{ $t->label() }}</p>
                        </button>
                        @endforeach
                    </div>
                </x-card>

                {{-- Details --}}
                <x-card title="Informations générales" separator>
                    <div class="space-y-4">
                        <x-input label="Titre de l'évaluation *" wire:model="title"
                                 placeholder="ex : Contrôle de mathématiques — Chapitre 3" />

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <x-select label="Matière *" wire:model.live="subject_id"
                                      :options="$subjects" option-value="id" option-label="name"
                                      placeholder="Choisir une matière..." placeholder-value="0" />
                            <x-select label="Classe *" wire:model="class_id"
                                      :options="$classes" option-value="id" option-label="name"
                                      placeholder="Choisir une classe..." placeholder-value="0" />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <x-datepicker label="Date *" wire:model="date" icon="o-calendar"
                                          :config="['dateFormat' => 'Y-m-d', 'altFormat' => 'd/m/Y', 'altInput' => true, 'locale' => ['firstDayOfWeek' => 1]]" />
                            <x-select label="Période" wire:model="period"
                                      :options="$periods" option-value="id" option-label="name"
                                      placeholder="Non spécifiée" placeholder-value="" />
                        </div>

                        <x-select label="Enseignant responsable" wire:model="teacher_id"
                                  :options="$teachers" option-value="id" option-label="name"
                                  placeholder="Non assigné" placeholder-value="0" />
                    </div>
                </x-card>

                {{-- Scoring --}}
                <x-card title="Barème" separator>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input label="Note maximale *" wire:model.live="max_score"
                                     type="number" step="0.5" min="1" max="1000" />
                            <p class="text-xs text-base-content/50 mt-1">Note sur laquelle est évaluée l'élève</p>
                        </div>
                        <div>
                            <x-input label="Coefficient" wire:model.live="coefficient"
                                     type="number" step="0.5" min="0.5" max="10" />
                            <p class="text-xs text-base-content/50 mt-1">Poids dans la moyenne générale</p>
                        </div>
                    </div>

                    {{-- Visual weight indicator --}}
                    <div class="mt-4 p-3 rounded-xl bg-base-200/60 flex items-center gap-3">
                        <x-icon name="o-scale" class="w-5 h-5 text-base-content/50 shrink-0" />
                        <div class="flex-1">
                            <p class="text-xs text-base-content/60">Poids relatif de cette évaluation</p>
                            <div class="flex items-baseline gap-1 mt-0.5">
                                <span class="text-lg font-black text-primary">{{ number_format($coefficient, 1) }}</span>
                                <span class="text-xs text-base-content/50">× sur {{ number_format($max_score, 0) }} pts</span>
                            </div>
                        </div>
                    </div>
                </x-card>

                {{-- Instructions --}}
                <x-card title="Instructions & remarques" separator>
                    <x-textarea wire:model="description" rows="4"
                                placeholder="Chapitres couverts, consignes particulières, matériel autorisé..." />
                    <p class="text-xs text-base-content/40 mt-1 text-right">
                        {{ mb_strlen($description) }}/500 caractères
                    </p>
                </x-card>

            </div>

            {{-- ─── RIGHT COLUMN (preview) ──────────────────────── --}}
            <div class="space-y-5">

                {{-- Live preview card --}}
                <div class="sticky top-4 space-y-4">
                    <x-card>
                        <div class="space-y-4">
                            <p class="text-xs font-semibold uppercase tracking-widest text-base-content/40">Aperçu</p>

                            {{-- Type badge --}}
                            @if($selectedType)
                            <div class="flex items-center gap-2">
                                <div class="w-9 h-9 rounded-xl flex items-center justify-center bg-linear-to-br {{ $selectedType->gradient() }}">
                                    <x-icon name="{{ $selectedType->icon() }}" class="w-5 h-5" />
                                </div>
                                <div>
                                    <p class="font-semibold text-sm">{{ $selectedType->label() }}</p>
                                    <x-badge :value="$selectedType->label()" class="{{ $selectedType->color() }} badge-sm" />
                                </div>
                            </div>
                            @else
                            <div class="flex items-center gap-2 opacity-40">
                                <div class="w-9 h-9 rounded-xl bg-base-200 flex items-center justify-center">
                                    <x-icon name="o-document" class="w-5 h-5" />
                                </div>
                                <p class="text-sm italic">Choisir un type...</p>
                            </div>
                            @endif

                            <div class="divider my-0"></div>

                            {{-- Title preview --}}
                            <div>
                                <p class="text-xs text-base-content/50 mb-1">Titre</p>
                                <p class="font-bold text-base leading-snug">
                                    {{ $title ?: 'Titre de l\'évaluation' }}
                                </p>
                            </div>

                            {{-- Subject swatch --}}
                            @if($selectedSubject)
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 rounded-full shrink-0"
                                     style="background-color: {{ $selectedSubject->color ?? '#6366f1' }}"></div>
                                <span class="text-sm font-medium" style="color: {{ $selectedSubject->color ?? '#6366f1' }}">
                                    {{ $selectedSubject->name }}
                                </span>
                            </div>
                            @endif

                            {{-- Date --}}
                            @if($date)
                            <div class="flex items-center gap-2 text-sm text-base-content/70">
                                <x-icon name="o-calendar" class="w-4 h-4 shrink-0 text-base-content/40" />
                                {{ \Carbon\Carbon::parse($date)->locale('fr')->isoFormat('dddd D MMMM YYYY') }}
                            </div>
                            @endif

                            {{-- Score / coeff chips --}}
                            <div class="flex gap-2 flex-wrap">
                                <div class="badge badge-outline badge-md font-bold">
                                    /{{ number_format($max_score, 0) }} pts
                                </div>
                                <div class="badge badge-outline badge-md">
                                    Coeff. {{ number_format($coefficient, 1) }}
                                </div>
                                @if($period)
                                <div class="badge badge-ghost badge-md">
                                    {{ collect(App\Enums\ReportPeriod::cases())->firstWhere('value', $period)?->label() ?? $period }}
                                </div>
                                @endif
                            </div>

                            @if($description)
                            <div class="p-3 rounded-lg bg-base-200/60 text-xs text-base-content/60 leading-relaxed italic">
                                "{{ \Illuminate\Support\Str::limit($description, 120) }}"
                            </div>
                            @endif
                        </div>
                    </x-card>

                    {{-- Tips --}}
                    <x-card class="bg-info/5 border border-info/20">
                        <div class="flex gap-3">
                            <x-icon name="o-light-bulb" class="w-5 h-5 text-info shrink-0 mt-0.5" />
                            <div class="text-xs text-base-content/60 space-y-1">
                                <p class="font-semibold text-info">Conseils</p>
                                <p>Après création, vous pourrez saisir les notes des élèves, joindre le sujet et publier l'évaluation.</p>
                            </div>
                        </div>
                    </x-card>

                    {{-- Actions --}}
                    <div class="flex flex-col gap-2">
                        <x-button label="Créer l'évaluation" type="submit" icon="o-check"
                                  class="btn-primary w-full" spinner />
                        <a href="{{ route('admin.assessments.index') }}" wire:navigate>
                            <x-button label="Annuler" icon="o-arrow-left"
                                      class="btn-ghost w-full" />
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </x-form>
</div>
