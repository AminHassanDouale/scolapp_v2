<?php
use App\Models\AttendanceSession;
use App\Models\AttendanceEntry;
use App\Models\SchoolClass;
use App\Models\Enrollment;
use App\Models\Teacher;
use App\Models\Subject;
use App\Enums\AttendanceStatus;
use App\Services\AttendanceService;
use App\Mail\AttendanceSessionSummaryMail;
use App\Mail\AbsenceNotificationMail;
use App\Models\Guardian;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Mary\Traits\Toast;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

new #[Layout('layouts.app')] class extends Component {
    use Toast;

    // ── Session selector ──────────────────────────────────────────────────────
    public int    $classId      = 0;
    public string $date         = '';
    public string $period       = 'morning';
    public int    $subjectId    = 0;
    public int    $teacherId    = 0;
    public string $startTime    = '';
    public string $endTime      = '';
    public string $sessionNotes = '';

    // ── Loaded session ────────────────────────────────────────────────────────
    public int    $sessionId    = 0;

    // ── Attendance data ───────────────────────────────────────────────────────
    public array  $attendance     = [];   // [student_id => status_string]
    public array  $notes          = [];   // [student_id => reason]
    public array  $absenceCounts  = [];   // [student_id => int] (total absences this year)

    // ── UI state ──────────────────────────────────────────────────────────────
    public string $studentSearch  = '';
    public bool   $showSessionForm = false;   // expand subject/teacher row

    // ─────────────────────────────────────────────────────────────────────────

    public function mount(): void
    {
        $this->date            = Carbon::today()->format('Y-m-d');
        $this->showSessionForm = false;
    }

    public function updatedClassId(): void  { $this->loadSession(); }
    public function updatedDate(): void     { $this->loadSession(); }
    public function updatedPeriod(): void   { $this->loadSession(); }

    public function loadSession(): void
    {
        if (! $this->classId || ! $this->date) {
            $this->sessionId  = 0;
            $this->attendance = [];
            $this->notes      = [];
            return;
        }

        $schoolId = auth()->user()->school_id;
        $class    = SchoolClass::findOrFail($this->classId);

        // Fix: correct parameter order — (SchoolClass, string $period, Carbon $date)
        $session = app(AttendanceService::class)->openSession(
            $class,
            $this->period,
            Carbon::parse($this->date),
        );

        $this->sessionId     = $session->id;
        $this->subjectId     = $session->subject_id  ?? 0;
        $this->teacherId     = $session->teacher_id  ?? 0;
        $this->startTime     = $session->start_time  ? substr($session->start_time, 0, 5) : '';
        $this->endTime       = $session->end_time    ? substr($session->end_time,   0, 5) : '';
        $this->sessionNotes  = $session->notes ?? '';

        // Load student IDs from confirmed enrollments
        $studentIds = $this->enrolledStudentIds();

        // Existing entries — enum cast safe
        $existing = AttendanceEntry::where('attendance_session_id', $session->id)
            ->get()
            ->keyBy('student_id');

        $this->attendance = [];
        $this->notes      = [];
        foreach ($studentIds as $sid) {
            $entry = $existing->get($sid);
            $this->attendance[$sid] = $entry
                ? ($entry->status instanceof AttendanceStatus ? $entry->status->value : (string) $entry->status)
                : AttendanceStatus::PRESENT->value;
            $this->notes[$sid] = $entry?->reason ?? '';
        }

        // Pre-compute absence counts for this school (single query)
        if ($studentIds) {
            $this->absenceCounts = AttendanceEntry::whereIn('student_id', $studentIds)
                ->whereIn('status', [AttendanceStatus::ABSENT->value, AttendanceStatus::LATE->value])
                ->whereHas('attendanceSession', fn($q) => $q->where('school_id', $schoolId))
                ->selectRaw('student_id, COUNT(*) as cnt')
                ->groupBy('student_id')
                ->pluck('cnt', 'student_id')
                ->toArray();
        }
    }

    private function enrolledStudentIds(): array
    {
        $currentYear = \App\Models\AcademicYear::where('school_id', auth()->user()->school_id)
            ->where('is_current', true)->first();

        return Enrollment::where('school_class_id', $this->classId)
            ->when($currentYear, fn($q) => $q->where('academic_year_id', $currentYear->id))
            ->where('status', 'confirmed')
            ->pluck('student_id')
            ->toArray();
    }

    // ── Save & email ──────────────────────────────────────────────────────────

    public function save(): void
    {
        if (! $this->sessionId) {
            $this->error('Aucune session ouverte. Veuillez sélectionner une classe et une date.', position: 'toast-top toast-center', icon: 'o-exclamation-circle', css: 'alert-error', timeout: 4000);
            return;
        }

        // Update session metadata (subject, teacher, times, notes)
        $session = AttendanceSession::findOrFail($this->sessionId);
        $session->update([
            'subject_id' => $this->subjectId ?: null,
            'teacher_id' => $this->teacherId ?: null,
            'start_time' => $this->startTime ?: null,
            'end_time'   => $this->endTime   ?: null,
            'notes'      => $this->sessionNotes ?: null,
        ]);

        // Save each attendance entry
        foreach ($this->attendance as $studentId => $status) {
            AttendanceEntry::updateOrCreate(
                ['attendance_session_id' => $this->sessionId, 'student_id' => $studentId],
                [
                    'status' => $status,
                    'reason' => $this->notes[$studentId] ?? null,
                ]
            );
        }

        // Send summary email to all class teachers
        $teacherEmails = $this->sendSummaryEmails($session);

        // Send absence notification to guardians of absent/late/excused students
        $guardianEmails = $this->sendGuardianEmails($session);

        $msg = 'Appel enregistré.';
        if ($teacherEmails > 0) {
            $msg .= " Récapitulatif envoyé à {$teacherEmails} enseignant(s).";
        }
        if ($guardianEmails > 0) {
            $msg .= " Notification d'absence envoyée à {$guardianEmails} parent(s).";
        }
        $this->success($msg, position: 'toast-top toast-end', icon: 'o-check-circle', css: 'alert-success', timeout: 3000);

        if ($teacherEmails === 0) {
            $this->warning('Aucun email enseignant configuré pour cette classe.', position: 'toast-bottom toast-end', icon: 'o-exclamation-triangle', css: 'alert-warning', timeout: 4000);
        }
    }

    private function sendGuardianEmails(AttendanceSession $session): int
    {
        // Collect absent / late / excused student IDs from current attendance
        $nonPresentStudentIds = collect($this->attendance)
            ->filter(fn($s) => $s !== AttendanceStatus::PRESENT->value)
            ->keys()
            ->toArray();

        if (empty($nonPresentStudentIds)) {
            return 0;
        }

        // Load those students with their guardians (only those who accept notifications)
        $students = \App\Models\Student::whereIn('id', $nonPresentStudentIds)
            ->with(['guardians' => fn($q) =>
                $q->wherePivot('receive_notifications', true)
                  ->whereNotNull('email')
                  ->where('is_active', true)
            ])
            ->get()
            ->keyBy('id');

        $session->loadMissing(['schoolClass.grade', 'subject', 'teacher', 'academicYear']);

        $sent = 0;
        foreach ($nonPresentStudentIds as $studentId) {
            $student = $students->get($studentId);
            if (! $student) {
                continue;
            }

            $status = $this->attendance[$studentId];
            $reason = $this->notes[$studentId] ?? null;

            foreach ($student->guardians as $guardian) {
                if (! $guardian->email) {
                    continue;
                }
                try {
                    Mail::to($guardian->email)->send(
                        new AbsenceNotificationMail($guardian, $student, $session, $status, $reason)
                    );
                    $sent++;
                } catch (\Throwable $e) {
                    Log::warning("Guardian absence email failed to {$guardian->email}: " . $e->getMessage());
                }
            }
        }

        return $sent;
    }

    private function sendSummaryEmails(AttendanceSession $session): int
    {
        $session->load([
            'attendanceEntries.student',
            'schoolClass.grade',
            'subject',
            'teacher',
            'academicYear',
        ]);

        $emails = collect();

        // Teacher assigned to this session
        if ($session->teacher?->email) {
            $emails->push($session->teacher->email);
        }

        // All teachers assigned to this class
        $classTeachers = Teacher::whereHas(
            'schoolClasses',
            fn($q) => $q->where('school_classes.id', $session->school_class_id)
        )->whereNotNull('email')->where('is_active', true)->get();

        foreach ($classTeachers as $t) {
            if (! $emails->contains($t->email)) {
                $emails->push($t->email);
            }
        }

        $sent = 0;
        foreach ($emails as $email) {
            try {
                Mail::to($email)->send(new AttendanceSessionSummaryMail($session));
                $sent++;
            } catch (\Throwable $e) {
                Log::warning("Attendance email failed to {$email}: " . $e->getMessage());
            }
        }

        return $sent;
    }

    // ── Quick actions ─────────────────────────────────────────────────────────

    public function markAll(string $status): void
    {
        foreach ($this->attendance as $sid => $_) {
            $this->attendance[$sid] = $status;
        }
    }

    // ── Data ──────────────────────────────────────────────────────────────────

    public function with(): array
    {
        $schoolId = auth()->user()->school_id;

        $classes = SchoolClass::where('school_id', $schoolId)
            ->with('grade')
            ->orderBy('name')
            ->get()
            ->map(fn($c) => [
                'id'   => $c->id,
                'name' => $c->name . ($c->grade ? ' — ' . $c->grade->name : ''),
            ])
            ->all();

        $subjects = Subject::where('school_id', $schoolId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $teachers = Teacher::where('school_id', $schoolId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn($t) => ['id' => $t->id, 'name' => $t->full_name]);

        $students = collect();
        if ($this->classId && $this->sessionId && count($this->attendance)) {
            $studentIds = array_keys($this->attendance);
            $query = \App\Models\Student::whereIn('id', $studentIds)
                ->orderBy('name');

            if ($this->studentSearch) {
                $s = '%' . $this->studentSearch . '%';
                $query->where(fn($q) => $q
                    ->where('name', 'like', $s)
                );
            }

            $students = $query->get();
        }

        // Attendance counters
        $present  = collect($this->attendance)->filter(fn($s) => $s === AttendanceStatus::PRESENT->value)->count();
        $absent   = collect($this->attendance)->filter(fn($s) => $s === AttendanceStatus::ABSENT->value)->count();
        $late     = collect($this->attendance)->filter(fn($s) => $s === AttendanceStatus::LATE->value)->count();
        $excused  = collect($this->attendance)->filter(fn($s) => $s === AttendanceStatus::EXCUSED->value)->count();
        $total    = count($this->attendance);
        $rate     = $total > 0 ? round(($present / $total) * 100) : 0;

        $periods = [
            ['id' => 'morning',   'name' => 'Matin'],
            ['id' => 'afternoon', 'name' => 'Après-midi'],
            ['id' => 'full_day',  'name' => 'Journée entière'],
        ];

        return compact(
            'classes', 'subjects', 'teachers', 'students', 'periods',
            'present', 'absent', 'late', 'excused', 'total', 'rate'
        );
    }
};
?>

<div>
    {{-- ── HEADER ─────────────────────────────────────────────────────────────── --}}
    <x-header title="Faire l'appel" separator progress-indicator>
        <x-slot:subtitle>
            @if($sessionId && $total > 0)
            <span class="text-sm text-base-content/60">
                {{ $total }} élève(s) · {{ $date ? \Carbon\Carbon::parse($date)->format('d/m/Y') : '' }}
            </span>
            @endif
        </x-slot:subtitle>
        <x-slot:actions>
            <x-button label="Retour" icon="o-arrow-left"
                      :link="route('admin.attendance.index')"
                      class="btn-ghost btn-sm" wire:navigate />
        </x-slot:actions>
    </x-header>

    {{-- ── SESSION CONFIG ──────────────────────────────────────────────────────── --}}
    <x-card class="mb-5 shadow-sm">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <x-select wire:model.live="classId"
                      label="Classe *"
                      :options="$classes"
                      option-value="id"
                      option-label="name"
                      placeholder="Choisir une classe…"
                      placeholder-value="0"
                      icon="o-academic-cap" />

            <x-datepicker wire:model.live="date"
                          label="Date *"
                          icon="o-calendar"
                          :config="['dateFormat' => 'Y-m-d', 'altFormat' => 'd/m/Y', 'altInput' => true,
                                    'locale' => ['firstDayOfWeek' => 1]]" />

            <x-select wire:model.live="period"
                      label="Période *"
                      :options="$periods"
                      option-value="id"
                      option-label="name"
                      icon="o-clock" />
        </div>

        {{-- Expandable session details --}}
        @if($sessionId)
        <div class="mt-4 pt-4 border-t border-base-200">
            <button wire:click="$toggle('showSessionForm')"
                    class="flex items-center gap-2 text-sm font-medium text-base-content/60 hover:text-primary transition-colors">
                <x-icon name="{{ $showSessionForm ? 'o-chevron-up' : 'o-chevron-down' }}" class="w-4 h-4" />
                {{ $showSessionForm ? 'Masquer les détails de séance' : 'Ajouter matière / enseignant / horaire' }}
            </button>

            @if($showSessionForm)
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mt-4">
                <x-select wire:model="subjectId"
                          label="Matière"
                          :options="$subjects"
                          option-value="id"
                          option-label="name"
                          placeholder="Aucune"
                          placeholder-value="0"
                          icon="o-book-open" />

                <x-select wire:model="teacherId"
                          label="Enseignant"
                          :options="$teachers"
                          option-value="id"
                          option-label="name"
                          placeholder="Non assigné"
                          placeholder-value="0"
                          icon="o-user" />

                <div>
                    <label class="label text-sm font-medium pb-1 block">Heure de début</label>
                    <input type="time" wire:model="startTime"
                           class="input input-bordered w-full" step="300" />
                </div>
                <div>
                    <label class="label text-sm font-medium pb-1 block">Heure de fin</label>
                    <input type="time" wire:model="endTime"
                           class="input input-bordered w-full" step="300" />
                </div>

                <div class="col-span-2 lg:col-span-4">
                    <label class="label text-sm font-medium pb-1 block">Notes de séance</label>
                    <textarea wire:model="sessionNotes" rows="2"
                              placeholder="Remarques générales sur la séance…"
                              class="textarea textarea-bordered w-full text-sm"></textarea>
                </div>
            </div>
            @endif
        </div>
        @endif
    </x-card>

    {{-- ── STATS + LIST ─────────────────────────────────────────────────────────── --}}
    @if($sessionId && $total > 0)

    {{-- Attendance stats bar --}}
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-5">
        {{-- Rate ring --}}
        <div class="lg:col-span-1 rounded-2xl bg-linear-to-br from-primary to-primary/70 p-4 text-primary-content flex flex-col items-center justify-center gap-1">
            <div class="radial-progress text-primary-content font-black text-lg"
                 style="--value:{{ $rate }}; --size: 4rem; --thickness: 5px;"
                 role="progressbar">
                {{ $rate }}%
            </div>
            <p class="text-xs opacity-80 text-center">Taux de présence</p>
        </div>

        <div class="rounded-2xl bg-success/10 border border-success/20 p-4 flex flex-col items-center justify-center gap-1">
            <p class="text-3xl font-black text-success">{{ $present }}</p>
            <p class="text-xs text-success/70 font-medium">Présents</p>
        </div>

        <div class="rounded-2xl bg-error/10 border border-error/20 p-4 flex flex-col items-center justify-center gap-1">
            <p class="text-3xl font-black text-error">{{ $absent }}</p>
            <p class="text-xs text-error/70 font-medium">Absents</p>
        </div>

        <div class="rounded-2xl bg-warning/10 border border-warning/20 p-4 flex flex-col items-center justify-center gap-1">
            <p class="text-3xl font-black text-warning">{{ $late }}</p>
            <p class="text-xs text-warning/70 font-medium">Retards</p>
        </div>

        <div class="rounded-2xl bg-info/10 border border-info/20 p-4 flex flex-col items-center justify-center gap-1">
            <p class="text-3xl font-black text-info">{{ $excused }}</p>
            <p class="text-xs text-info/70 font-medium">Excusés</p>
        </div>
    </div>

    {{-- Search + quick actions --}}
    <div class="flex items-center gap-3 mb-4 flex-wrap">
        <div class="relative flex-1 max-w-xs">
            <x-icon name="o-magnifying-glass" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-base-content/40 pointer-events-none" />
            <input type="text" wire:model.live.debounce.250ms="studentSearch"
                   placeholder="Rechercher un élève…"
                   class="input input-bordered w-full pl-9 input-sm" />
        </div>
        <div class="flex gap-2 ml-auto">
            <x-button label="Tous présents"
                      wire:click="markAll('present')"
                      icon="o-check-circle"
                      class="btn-success btn-sm btn-outline" />
            <x-button label="Tous absents"
                      wire:click="markAll('absent')"
                      icon="o-x-circle"
                      class="btn-error btn-sm btn-outline" />
        </div>
    </div>

    {{-- Student cards --}}
    <div class="space-y-2 mb-6">
        @forelse($students as $index => $student)
        @php
            $sid    = $student->id;
            $status = $attendance[$sid] ?? AttendanceStatus::PRESENT->value;
            $reason = $notes[$sid] ?? '';
            $absN   = $absenceCounts[$sid] ?? 0;

            $borderColor = match($status) {
                'absent'  => 'border-l-error',
                'late'    => 'border-l-warning',
                'excused' => 'border-l-info',
                default   => 'border-l-success',
            };
            $bgColor = match($status) {
                'absent'  => 'bg-error/5',
                'late'    => 'bg-warning/5',
                'excused' => 'bg-info/5',
                default   => 'bg-base-100',
            };
            $avatarColor = match($status) {
                'absent'  => 'bg-error/20 text-error',
                'late'    => 'bg-warning/20 text-warning',
                'excused' => 'bg-info/20 text-info',
                default   => 'bg-success/20 text-success',
            };
        @endphp

        <div wire:key="student-{{ $sid }}"
             class="rounded-xl border-l-4 border border-base-200 {{ $borderColor }} {{ $bgColor }} transition-all duration-200">

            <div class="flex items-center gap-3 p-3">
                {{-- Avatar --}}
                <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-sm shrink-0 {{ $avatarColor }}">
                    {{ strtoupper(substr($student->name, 0, 1)) }}
                </div>

                {{-- Name + info --}}
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <p class="font-semibold text-sm">{{ $student->full_name }}</p>
                        @if($student->student_code ?? null)
                        <span class="text-[10px] font-mono text-base-content/40 bg-base-200 px-1.5 py-0.5 rounded">
                            {{ $student->student_code }}
                        </span>
                        @endif
                        @if($absN > 0)
                        <span class="badge badge-xs {{ $absN >= 5 ? 'badge-error' : 'badge-warning' }} gap-0.5">
                            <x-icon name="o-exclamation-triangle" class="w-2.5 h-2.5" />
                            {{ $absN }} abs. cette année
                        </span>
                        @endif
                    </div>
                    <p class="text-[11px] text-base-content/50 mt-0.5">Élève n°{{ $index + 1 }}</p>
                </div>

                {{-- Status buttons --}}
                <div class="flex gap-1 shrink-0 flex-wrap justify-end">
                    @foreach([
                        ['present', 'Présent',  'o-check',            'btn-success'],
                        ['absent',  'Absent',   'o-x-mark',           'btn-error'],
                        ['late',    'Retard',   'o-clock',            'btn-warning'],
                        ['excused', 'Excusé',   'o-document-text',    'btn-info'],
                    ] as [$val, $label, $icon, $cls])
                    <button wire:click="$set('attendance.{{ $sid }}', '{{ $val }}')"
                            class="btn btn-xs gap-1 {{ $status === $val ? $cls : 'btn-ghost opacity-40 hover:opacity-80' }}">
                        <x-icon name="{{ $icon }}" class="w-3 h-3" />
                        <span class="hidden sm:inline">{{ $label }}</span>
                    </button>
                    @endforeach
                </div>
            </div>

            {{-- Reason field — only when not present --}}
            @if($status !== 'present')
            <div class="px-3 pb-3 pt-0">
                <input type="text"
                       wire:model.blur="notes.{{ $sid }}"
                       placeholder="Motif / remarque (facultatif)…"
                       class="input input-sm input-bordered w-full text-xs bg-base-100/80" />
            </div>
            @endif
        </div>
        @empty
        @if($studentSearch)
        <div class="text-center py-10 text-base-content/40">
            <x-icon name="o-magnifying-glass" class="w-8 h-8 mx-auto mb-2 opacity-30" />
            <p class="text-sm">Aucun élève trouvé pour "{{ $studentSearch }}"</p>
        </div>
        @endif
        @endforelse
    </div>

    {{-- ── SUBMIT BUTTON ────────────────────────────────────────────────────────── --}}
    <div class="sticky bottom-4 z-30">
        <div class="bg-base-100/80 backdrop-blur-md rounded-2xl shadow-xl border border-base-200 p-4 flex items-center gap-4 flex-wrap">
            <div class="flex-1 text-sm text-base-content/60">
                <span class="font-semibold text-base-content">{{ $total }} élèves</span> ·
                <span class="text-success font-medium">{{ $present }} présent(s)</span> ·
                <span class="text-error font-medium">{{ $absent }} absent(s)</span>
                @if($late) · <span class="text-warning font-medium">{{ $late }} retard(s)</span> @endif
                @if($excused) · <span class="text-info font-medium">{{ $excused }} excusé(s)</span> @endif
            </div>
            <x-button label="Enregistrer & Envoyer Email"
                      icon="o-paper-airplane"
                      wire:click="save"
                      class="btn-primary btn-sm sm:btn-md"
                      spinner="save" />
        </div>
    </div>

    @elseif($classId && $sessionId)
    <x-alert icon="o-information-circle" class="alert-info mt-4">
        Aucun élève inscrit dans cette classe pour l'année scolaire en cours.
    </x-alert>

    @else
    <div class="text-center py-24 text-base-content/40">
        <x-icon name="o-clipboard-document-check" class="w-20 h-20 mx-auto mb-4 opacity-15" />
        <p class="font-bold text-xl mb-1">Sélectionnez une classe pour commencer</p>
        <p class="text-sm">Choisissez une classe, une date et une période pour faire l'appel.</p>
    </div>
    @endif
</div>
