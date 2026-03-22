<?php
use App\Models\Assessment;
use App\Models\StudentScore;
use App\Models\Enrollment;
use App\Enums\AssessmentType;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;
use Illuminate\Support\Facades\Storage;

new #[Layout('layouts.app')] class extends Component {
    use Toast, WithFileUploads;

    public Assessment $assessment;

    public array $scores   = [];   // [student_id => float|null]
    public array $comments = [];   // [student_id => string]
    public array $isAbsent = [];   // [student_id => bool]

    // File upload — must stay untyped for Livewire
    public $assessmentFile = null;

    public function mount(int $id): void
    {
        $this->assessment = Assessment::where('school_id', auth()->user()->school_id)
            ->with(['subject', 'schoolClass.grade', 'studentScores.student', 'teacher', 'academicYear'])
            ->findOrFail($id);

        foreach ($this->assessment->studentScores as $score) {
            $this->scores[$score->student_id]   = $score->is_absent ? null : $score->score;
            $this->comments[$score->student_id] = $score->comment ?? '';
            $this->isAbsent[$score->student_id] = (bool) $score->is_absent;
        }
    }

    // ── Score helpers ─────────────────────────────────────────────────────────

    private function mentionForScore(float $score): array
    {
        $pct = ($score / (float) $this->assessment->max_score) * 100;
        return match(true) {
            $pct >= 90 => ['Excellent',   'badge-success'],
            $pct >= 75 => ['Très Bien',   'badge-success'],
            $pct >= 65 => ['Bien',        'badge-info'],
            $pct >= 55 => ['Assez Bien',  'badge-warning'],
            $pct >= 50 => ['Passable',    'badge-ghost'],
            default    => ['Insuffisant', 'badge-error'],
        };
    }

    // ── Save scores ───────────────────────────────────────────────────────────

    public function saveScores(): void
    {
        foreach ($this->scores as $studentId => $score) {
            $absent  = $this->isAbsent[$studentId] ?? false;
            $mention = null;

            if (! $absent && $score !== null && $score !== '') {
                [$mention] = $this->mentionForScore((float) $score);
            }

            StudentScore::updateOrCreate(
                ['assessment_id' => $this->assessment->id, 'student_id' => $studentId],
                [
                    'score'     => $absent ? null : ($score !== '' ? (float) $score : null),
                    'is_absent' => $absent,
                    'mention'   => $mention,
                    'comment'   => $this->comments[$studentId] ?? null,
                ]
            );
        }

        $this->assessment->load('studentScores.student');
        $this->success('Notes enregistrées avec succès.', position: 'toast-top toast-end', icon: 'o-pencil-square', css: 'alert-success', timeout: 3000);
    }

    public function toggleAbsent(int $studentId): void
    {
        $this->isAbsent[$studentId] = ! ($this->isAbsent[$studentId] ?? false);
        if ($this->isAbsent[$studentId]) {
            $this->scores[$studentId] = null;
        }
    }

    public function markAllAbsent(): void
    {
        foreach (array_keys($this->attendance ?? $this->scores) as $sid) {
            $this->isAbsent[$sid] = true;
            $this->scores[$sid]   = null;
        }
    }

    // ── File upload ───────────────────────────────────────────────────────────

    public function uploadFile(): void
    {
        $this->validate([
            'assessmentFile' => 'required|file|mimes:pdf,doc,docx,jpg,jpeg,png,ppt,pptx|max:10240',
        ], [
            'assessmentFile.required' => 'Veuillez sélectionner un fichier.',
            'assessmentFile.mimes'    => 'Format accepté : PDF, Word, PowerPoint, image (max 10 Mo).',
            'assessmentFile.max'      => 'Le fichier ne doit pas dépasser 10 Mo.',
        ]);

        // Remove old file
        if ($this->assessment->file_path) {
            Storage::disk('public')->delete($this->assessment->file_path);
        }

        $originalName = $this->assessmentFile->getClientOriginalName();
        $path = $this->assessmentFile->store("assessments/{$this->assessment->uuid}", 'public');

        $this->assessment->update([
            'file_path'          => $path,
            'file_original_name' => $originalName,
        ]);

        $this->assessmentFile = null;
        $this->success('Fichier uploadé.', position: 'toast-top toast-end', icon: 'o-document-check', css: 'alert-success', timeout: 3000);
    }

    public function deleteFile(): void
    {
        if ($this->assessment->file_path) {
            Storage::disk('public')->delete($this->assessment->file_path);
            $this->assessment->update(['file_path' => null, 'file_original_name' => null]);
            $this->success('Fichier supprimé.', position: 'toast-top toast-end', icon: 'o-trash', css: 'alert-success', timeout: 3000);
        }
    }

    // ── Publish toggle ────────────────────────────────────────────────────────

    public function togglePublished(): void
    {
        $this->assessment->update(['is_published' => ! $this->assessment->is_published]);
        $this->success($this->assessment->is_published ? 'Notes publiées aux élèves.' : 'Publication retirée.', position: 'toast-top toast-end', icon: 'o-trash', css: 'alert-success', timeout: 3000);
    }

    public function deleteAssessment(): void
    {
        $this->assessment->delete();
        $this->redirect(route('admin.assessments.index'), navigate: true);
    }

    // ── Data ──────────────────────────────────────────────────────────────────

    public function with(): array
    {
        $currentYear = \App\Models\AcademicYear::where('school_id', auth()->user()->school_id)
            ->where('is_current', true)->first();

        $enrolledStudents = Enrollment::where('school_class_id', $this->assessment->school_class_id)
            ->when($currentYear, fn($q) => $q->where('academic_year_id', $currentYear->id))
            ->where('status', 'confirmed')
            ->with('student')
            ->get()
            ->map(fn($e) => $e->student)
            ->filter()
            ->sortBy('name')
            ->values();

        // Initialise missing entries
        foreach ($enrolledStudents as $student) {
            $this->scores[$student->id]   ??= null;
            $this->comments[$student->id] ??= '';
            $this->isAbsent[$student->id] ??= false;
        }

        // Stats
        $enteredScores = collect($this->scores)
            ->filter(fn($v, $k) => $v !== null && $v !== '' && ! ($this->isAbsent[$k] ?? false))
            ->map(fn($v) => (float) $v);

        $absentCount = collect($this->isAbsent)->filter()->count();
        $n           = $enteredScores->count();
        $maxScore    = (float) $this->assessment->max_score;
        $threshold   = $maxScore / 2;

        $avg      = $n > 0 ? round($enteredScores->avg(), 2) : null;
        $max      = $n > 0 ? $enteredScores->max() : null;
        $min      = $n > 0 ? $enteredScores->min() : null;
        $passCount = $enteredScores->filter(fn($v) => $v >= $threshold)->count();
        $passRate  = $n > 0 ? round(($passCount / $n) * 100) : null;

        // Grade distribution (A/B/C/D/F)
        $distribution = [
            'A' => $enteredScores->filter(fn($v) => $v / $maxScore >= 0.90)->count(),
            'B' => $enteredScores->filter(fn($v) => $v / $maxScore >= 0.75 && $v / $maxScore < 0.90)->count(),
            'C' => $enteredScores->filter(fn($v) => $v / $maxScore >= 0.55 && $v / $maxScore < 0.75)->count(),
            'D' => $enteredScores->filter(fn($v) => $v / $maxScore >= 0.50 && $v / $maxScore < 0.55)->count(),
            'F' => $enteredScores->filter(fn($v) => $v / $maxScore < 0.50)->count(),
        ];

        // Sorted ranking (for display)
        $ranked = $enteredScores->sortDesc()->values();

        // Pre-compute live mentions for Blade template
        $mentions = [];
        foreach ($this->scores as $sid => $v) {
            if (! ($this->isAbsent[$sid] ?? false) && $v !== null && $v !== '') {
                try { $mentions[$sid] = $this->mentionForScore((float) $v); } catch (\Throwable) {}
            }
        }

        return [
            'enrolledStudents' => $enrolledStudents,
            'enteredCount'     => $n,
            'absentCount'      => $absentCount,
            'distribution'     => $distribution,
            'ranked'           => $ranked,
            'mentions'         => $mentions,
            'stats'            => compact('avg', 'max', 'min', 'passRate', 'passCount'),
        ];
    }
};
?>

<div>
    {{-- ── HEADER ─────────────────────────────────────────────────────────────── --}}
    <x-header separator progress-indicator>
        <x-slot:title>
            <div class="flex items-center gap-2 text-sm text-base-content/60 flex-wrap">
                <a href="{{ route('admin.assessments.index') }}" wire:navigate
                   class="hover:text-primary transition-colors">Évaluations</a>
                <x-icon name="o-chevron-right" class="w-3 h-3 shrink-0" />
                <span class="text-base-content font-bold">{{ $assessment->title }}</span>
            </div>
        </x-slot:title>
        <x-slot:actions>
            <x-button :label="$assessment->is_published ? 'Dépublier' : 'Publier aux élèves'"
                      :icon="$assessment->is_published ? 'o-eye-slash' : 'o-eye'"
                      wire:click="togglePublished"
                      class="{{ $assessment->is_published ? 'btn-ghost btn-sm' : 'btn-success btn-sm' }}"
                      spinner />
            <x-button icon="o-trash" wire:click="deleteAssessment"
                      wire:confirm="Supprimer cette évaluation et toutes ses notes ?"
                      class="btn-ghost btn-sm text-error" tooltip="Supprimer" />
            <x-button icon="o-arrow-left" :link="route('admin.assessments.index')"
                      class="btn-ghost btn-sm" wire:navigate />
        </x-slot:actions>
    </x-header>

    {{-- ── INFO BANNER ─────────────────────────────────────────────────────────── --}}
    @php
        $subjectColor = $assessment->subject?->color ?? '#6366f1';
        $typeIcon     = $assessment->type?->icon() ?? 'o-clipboard-document-list';
        $typeColor    = $assessment->type?->color() ?? 'badge-outline';
    @endphp
    <div class="rounded-2xl p-5 mb-6 border-l-4"
         style="background: color-mix(in srgb, {{ $subjectColor }} 8%, white);
                border-left-color: {{ $subjectColor }};">
        <div class="flex items-start gap-4 flex-wrap">
            {{-- Type icon circle --}}
            <div class="w-12 h-12 rounded-xl flex items-center justify-center shrink-0"
                 style="background: color-mix(in srgb, {{ $subjectColor }} 18%, white);">
                <x-icon name="{{ $typeIcon }}" class="w-6 h-6" style="color: {{ $subjectColor }}" />
            </div>

            <div class="flex-1 min-w-0">
                <h2 class="text-lg font-bold text-base-content">{{ $assessment->title }}</h2>
                <div class="flex flex-wrap items-center gap-2 mt-2">
                    <span class="badge {{ $typeColor }} badge-sm">{{ $assessment->type?->label() }}</span>
                    <span class="px-2 py-0.5 rounded-lg text-xs font-semibold"
                          style="background: color-mix(in srgb, {{ $subjectColor }} 18%, white); color: {{ $subjectColor }}">
                        {{ $assessment->subject?->name }}
                    </span>
                    <x-badge value="{{ $assessment->schoolClass?->name }}" class="badge-ghost badge-sm" />
                    @if($assessment->assessment_date)
                    <x-badge value="{{ $assessment->assessment_date->format('d/m/Y') }}" class="badge-ghost badge-sm" />
                    @endif
                    <x-badge value="/ {{ $assessment->max_score }} pts" class="badge-outline badge-sm" />
                    <x-badge value="×{{ $assessment->coefficient }}" class="badge-ghost badge-sm" />
                    @if($assessment->period)
                    <x-badge value="{{ $assessment->period->label() }}" class="badge-outline badge-sm" />
                    @endif
                    @if($assessment->teacher)
                    <span class="flex items-center gap-1 text-xs text-base-content/50">
                        <x-icon name="o-user" class="w-3 h-3" /> {{ $assessment->teacher->full_name }}
                    </span>
                    @endif
                    @if($assessment->is_published)
                    <x-badge value="✓ Publié" class="badge-success badge-sm" />
                    @endif
                </div>
                @if($assessment->instructions)
                <p class="text-sm text-base-content/60 mt-2 italic">{{ $assessment->instructions }}</p>
                @endif
            </div>
        </div>
    </div>

    {{-- ── TWO-COLUMN: FILE + STATS ────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-6">

        {{-- File upload card --}}
        <x-card shadow class="lg:col-span-1">
            <x-slot:title>
                <div class="flex items-center gap-2">
                    <x-icon name="o-paper-clip" class="w-4 h-4 text-primary" />
                    Sujet / Document
                </div>
            </x-slot:title>

            {{-- Existing file --}}
            @if($assessment->file_path)
            @php
                $ext = $assessment->file_extension;
                $extIcon = match($ext) {
                    'pdf'           => 'o-document-text',
                    'doc', 'docx'   => 'o-document',
                    'ppt', 'pptx'   => 'o-presentation-chart-bar',
                    'jpg','jpeg','png' => 'o-photo',
                    default         => 'o-document',
                };
                $extColor = match($ext) {
                    'pdf'           => 'text-red-500',
                    'doc', 'docx'   => 'text-blue-500',
                    'ppt', 'pptx'   => 'text-orange-500',
                    'jpg','jpeg','png' => 'text-green-500',
                    default         => 'text-base-content/50',
                };
            @endphp
            <div class="flex items-center gap-3 p-3 bg-base-200/60 rounded-xl mb-4">
                <x-icon name="{{ $extIcon }}" class="w-8 h-8 shrink-0 {{ $extColor }}" />
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold truncate">
                        {{ $assessment->file_original_name ?? basename($assessment->file_path) }}
                    </p>
                    <p class="text-xs text-base-content/50 uppercase">{{ $ext }}</p>
                </div>
                <div class="flex gap-1">
                    <a href="{{ $assessment->file_url }}" target="_blank"
                       class="btn btn-ghost btn-xs" title="Télécharger">
                        <x-icon name="o-arrow-down-tray" class="w-3.5 h-3.5" />
                    </a>
                    <button wire:click="deleteFile" wire:confirm="Supprimer ce fichier ?"
                            class="btn btn-ghost btn-xs text-error" title="Supprimer">
                        <x-icon name="o-trash" class="w-3.5 h-3.5" />
                    </button>
                </div>
            </div>
            @endif

            {{-- Upload form --}}
            <div x-data="{ dragOver: false }"
                 @dragover.prevent="dragOver = true"
                 @dragleave.prevent="dragOver = false"
                 @drop.prevent="dragOver = false"
                 :class="dragOver ? 'border-primary bg-primary/5' : 'border-base-300'"
                 class="border-2 border-dashed rounded-xl p-4 text-center transition-all">
                <x-icon name="o-cloud-arrow-up" class="w-8 h-8 mx-auto mb-2 text-base-content/30" />
                <p class="text-xs text-base-content/50 mb-3">
                    PDF, Word, PPT, image<br>Max 10 Mo
                </p>
                <input type="file" wire:model="assessmentFile"
                       accept=".pdf,.doc,.docx,.ppt,.pptx,.jpg,.jpeg,.png"
                       class="file-input file-input-bordered file-input-sm w-full mb-2" />
                @error('assessmentFile')
                    <p class="text-error text-xs mt-1">{{ $message }}</p>
                @enderror
                <x-button label="Uploader"
                          wire:click="uploadFile"
                          wire:loading.attr="disabled"
                          icon="o-arrow-up-tray"
                          class="btn-primary btn-sm w-full mt-2"
                          spinner="uploadFile" />
                <div wire:loading wire:target="assessmentFile" class="mt-2">
                    <span class="loading loading-dots loading-sm text-primary"></span>
                    <p class="text-xs text-base-content/50">Chargement...</p>
                </div>
            </div>
        </x-card>

        {{-- Stats + distribution --}}
        <div class="lg:col-span-2 space-y-4">
            {{-- Stat cards --}}
            @if($stats['avg'] !== null)
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div class="rounded-xl bg-base-200 p-3 text-center">
                    <p class="text-xs text-base-content/60 mb-1">Moyenne classe</p>
                    <p class="text-2xl font-black text-primary">{{ $stats['avg'] }}</p>
                    <p class="text-xs text-base-content/40">/ {{ $assessment->max_score }}</p>
                </div>
                <div class="rounded-xl bg-success/10 border border-success/20 p-3 text-center">
                    <p class="text-xs text-base-content/60 mb-1">Meilleure note</p>
                    <p class="text-2xl font-black text-success">{{ $stats['max'] }}</p>
                </div>
                <div class="rounded-xl bg-error/10 border border-error/20 p-3 text-center">
                    <p class="text-xs text-base-content/60 mb-1">Note la plus basse</p>
                    <p class="text-2xl font-black text-error">{{ $stats['min'] }}</p>
                </div>
                <div class="rounded-xl bg-info/10 border border-info/20 p-3 text-center">
                    <p class="text-xs text-base-content/60 mb-1">Taux de réussite</p>
                    <p class="text-2xl font-black text-info">{{ $stats['passRate'] }}%</p>
                    <p class="text-xs text-base-content/40">{{ $stats['passCount'] }} / {{ $enteredCount }}</p>
                </div>
            </div>

            {{-- Grade distribution --}}
            <x-card shadow>
                <x-slot:title>
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-semibold">Répartition des notes</span>
                        <span class="text-xs text-base-content/50">{{ $enteredCount }} notes saisies · {{ $absentCount }} absent(s)</span>
                    </div>
                </x-slot:title>
                <div class="space-y-2">
                    @foreach([
                        ['Excellent (≥90%)',  'bg-success',        $distribution['A'], 'text-success'],
                        ['Très Bien (≥75%)',  'bg-info',           $distribution['B'], 'text-info'],
                        ['Bien/Assez Bien (≥55%)', 'bg-warning',   $distribution['C'], 'text-warning'],
                        ['Passable (≥50%)',   'bg-orange-400',     $distribution['D'], 'text-orange-400'],
                        ['Insuffisant (<50%)','bg-error',          $distribution['F'], 'text-error'],
                    ] as [$label, $bar, $count, $textColor])
                    @php $pct = $enteredCount > 0 ? round(($count / $enteredCount) * 100) : 0; @endphp
                    <div class="flex items-center gap-3">
                        <span class="text-xs w-36 truncate text-base-content/60">{{ $label }}</span>
                        <div class="flex-1 bg-base-200 rounded-full h-2.5">
                            <div class="{{ $bar }} h-2.5 rounded-full transition-all duration-500"
                                 style="width: {{ $pct }}%"></div>
                        </div>
                        <span class="text-xs font-bold {{ $textColor }} w-6 text-right">{{ $count }}</span>
                    </div>
                    @endforeach
                </div>
            </x-card>
            @else
            <x-card shadow class="h-full flex items-center justify-center">
                <div class="text-center py-10 text-base-content/40">
                    <x-icon name="o-chart-bar" class="w-10 h-10 mx-auto mb-2 opacity-20" />
                    <p class="text-sm">Les statistiques s'afficheront après la saisie des notes</p>
                </div>
            </x-card>
            @endif
        </div>
    </div>

    {{-- ── SCORE ENTRY ──────────────────────────────────────────────────────────── --}}
    <x-card shadow>
        <x-slot:title>
            <div class="flex items-center justify-between flex-wrap gap-2">
                <div class="flex items-center gap-2">
                    <x-icon name="o-pencil-square" class="w-5 h-5 text-primary" />
                    <span>Saisie des notes</span>
                    <span class="badge badge-sm badge-ghost">{{ $enrolledStudents->count() }} élèves</span>
                </div>
                <div class="flex gap-2">
                    <x-button label="Tous absents" wire:click="markAllAbsent" icon="o-x-circle"
                              class="btn-error btn-xs btn-outline" spinner />
                </div>
            </div>
        </x-slot:title>

        @if($enrolledStudents->count())
        <div class="space-y-1.5">
            {{-- Column headers --}}
            <div class="hidden md:grid grid-cols-12 gap-3 px-3 py-1 text-xs font-semibold text-base-content/50 uppercase tracking-wide border-b border-base-200 mb-2">
                <div class="col-span-1 text-center">#</div>
                <div class="col-span-3">Élève</div>
                <div class="col-span-1 text-center">Absent</div>
                <div class="col-span-2 text-center">Note / {{ $assessment->max_score }}</div>
                <div class="col-span-3">Progression</div>
                <div class="col-span-2 text-center">Mention</div>
            </div>

            @foreach($enrolledStudents as $index => $student)
            @php
                $sid     = $student->id;
                $score   = $this->scores[$sid] ?? null;
                $absent  = $this->isAbsent[$sid] ?? false;
                $pct     = (! $absent && $score !== null && $score !== '' && (float) $assessment->max_score > 0)
                    ? min(100, ((float) $score / (float) $assessment->max_score) * 100)
                    : 0;
                $barColor = $absent ? 'bg-base-300' : ($pct >= 50 ? 'bg-success' : 'bg-error');
                $mention  = $mentions[$sid] ?? null;
            @endphp

            <div wire:key="student-{{ $sid }}"
                 class="rounded-xl border transition-all duration-150
                        {{ $absent ? 'bg-error/5 border-error/20' : 'bg-base-100 border-base-200 hover:border-base-300' }}">

                {{-- Desktop row --}}
                <div class="hidden md:grid grid-cols-12 gap-3 items-center px-3 py-2.5">
                    {{-- Rank --}}
                    <div class="col-span-1 text-center">
                        <span class="text-xs font-mono text-base-content/40">{{ $index + 1 }}</span>
                    </div>

                    {{-- Student --}}
                    <div class="col-span-3 flex items-center gap-2 min-w-0">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold shrink-0
                                    {{ $absent ? 'bg-error/20 text-error' : 'bg-secondary/20 text-secondary' }}">
                            {{ strtoupper(substr($student->name, 0, 1)) }}
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold truncate {{ $absent ? 'line-through text-base-content/50' : '' }}">
                                {{ $student->full_name }}
                            </p>
                            @if($student->student_code ?? null)
                            <p class="text-[10px] font-mono text-base-content/40">{{ $student->student_code }}</p>
                            @endif
                        </div>
                    </div>

                    {{-- Absent toggle --}}
                    <div class="col-span-1 text-center">
                        <button wire:click="toggleAbsent({{ $sid }})"
                                class="btn btn-xs {{ $absent ? 'btn-error' : 'btn-ghost opacity-40 hover:btn-error hover:opacity-100' }} gap-1">
                            <x-icon name="{{ $absent ? 'o-x-circle' : 'o-check-circle' }}" class="w-3.5 h-3.5" />
                        </button>
                    </div>

                    {{-- Score input --}}
                    <div class="col-span-2 flex items-center justify-center gap-1">
                        <input type="number"
                               wire:model.lazy="scores.{{ $sid }}"
                               min="0" max="{{ $assessment->max_score }}" step="0.25"
                               placeholder="{{ $absent ? 'ABS' : '—' }}"
                               @disabled($absent)
                               class="input input-bordered input-sm w-20 text-center font-bold
                                      {{ $absent ? 'opacity-40 cursor-not-allowed' : '' }}" />
                    </div>

                    {{-- Progress bar --}}
                    <div class="col-span-3">
                        <div class="w-full bg-base-200 rounded-full h-2">
                            <div class="{{ $barColor }} h-2 rounded-full transition-all duration-300"
                                 style="width: {{ $pct }}%"></div>
                        </div>
                        @if(! $absent && $score !== null && $score !== '')
                        <p class="text-[10px] text-base-content/40 mt-0.5 text-right">{{ round($pct) }}%</p>
                        @endif
                    </div>

                    {{-- Mention badge --}}
                    <div class="col-span-2 text-center">
                        @if($absent)
                            <x-badge value="Absent" class="badge-error badge-xs" />
                        @elseif($mention)
                            <x-badge value="{{ $mention[0] }}" class="{{ $mention[1] }} badge-xs" />
                        @endif
                    </div>
                </div>

                {{-- Mobile row --}}
                <div class="md:hidden p-3 space-y-2">
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold
                                        {{ $absent ? 'bg-error/20 text-error' : 'bg-secondary/20 text-secondary' }}">
                                {{ strtoupper(substr($student->name, 0, 1)) }}
                            </div>
                            <p class="font-semibold text-sm {{ $absent ? 'line-through opacity-50' : '' }}">
                                {{ $student->full_name }}
                            </p>
                        </div>
                        <button wire:click="toggleAbsent({{ $sid }})"
                                class="btn btn-xs {{ $absent ? 'btn-error' : 'btn-ghost opacity-50' }}">
                            ABS
                        </button>
                    </div>
                    @if(! $absent)
                    <div class="flex items-center gap-2">
                        <input type="number" wire:model.lazy="scores.{{ $sid }}"
                               min="0" max="{{ $assessment->max_score }}" step="0.25"
                               placeholder="Note"
                               class="input input-bordered input-xs w-24 text-center font-bold" />
                        <span class="text-xs text-base-content/40">/ {{ $assessment->max_score }}</span>
                        @if($mention)
                        <x-badge value="{{ $mention[0] }}" class="{{ $mention[1] }} badge-xs ml-auto" />
                        @endif
                    </div>
                    @endif
                </div>

                {{-- Comment row --}}
                <div class="px-3 pb-2.5">
                    <input type="text"
                           wire:model.blur="comments.{{ $sid }}"
                           placeholder="Commentaire (facultatif)…"
                           class="input input-xs input-ghost w-full text-xs text-base-content/60 border-0 focus:border focus:border-base-300 px-0" />
                </div>
            </div>
            @endforeach
        </div>

        @else
        <x-alert icon="o-information-circle" class="alert-warning">
            Aucun élève inscrit dans la classe {{ $assessment->schoolClass?->name }} pour l'année scolaire en cours.
        </x-alert>
        @endif
    </x-card>

    {{-- ── STICKY SAVE BAR ─────────────────────────────────────────────────────── --}}
    @if($enrolledStudents->count())
    <div class="sticky bottom-4 z-30 mt-4">
        <div class="bg-base-100/80 backdrop-blur-md rounded-2xl shadow-xl border border-base-200 px-5 py-3
                    flex items-center gap-4 flex-wrap">
            <div class="flex-1 text-sm text-base-content/60">
                @if($stats['avg'] !== null)
                Moyenne : <span class="font-bold text-primary">{{ $stats['avg'] }} / {{ $assessment->max_score }}</span> ·
                Réussite : <span class="font-bold text-success">{{ $stats['passRate'] }}%</span>
                @if($absentCount)
                · <span class="text-error">{{ $absentCount }} absent(s)</span>
                @endif
                @else
                <span class="italic">Saisir les notes puis enregistrer</span>
                @endif
            </div>
            <x-button label="Enregistrer les notes"
                      icon="o-check"
                      wire:click="saveScores"
                      class="btn-primary btn-sm"
                      spinner="saveScores" />
        </div>
    </div>
    @endif
</div>
