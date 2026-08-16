<?php
use App\Models\SchoolClass;
use App\Models\Enrollment;
use App\Models\AttendanceSession;
use App\Models\AttendanceEntry;
use App\Models\AcademicYear;
use App\Models\Student;
use App\Mail\AbsenceNotificationMail;
use App\Services\AttendanceService;
use App\Services\WhatsAppService;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Mary\Traits\Toast;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

new #[Layout('layouts.monitor')] class extends Component {
    use Toast;

    public int    $classId       = 0;
    public string $date          = '';
    public string $period        = 'morning';
    public int    $sessionId     = 0;
    public array  $attendance    = [];   // [student_id => status]
    public array  $notes         = [];   // [student_id => reason]
    public string $studentSearch = '';

    public function mount(): void
    {
        $this->date = Carbon::today()->format('Y-m-d');
    }

    private function schoolId(): int
    {
        return (int) auth()->user()->school_id;
    }

    public function updatedClassId(): void { $this->loadSession(); }
    public function updatedDate(): void    { $this->loadSession(); }
    public function updatedPeriod(): void  { $this->loadSession(); }

    public function loadSession(): void
    {
        if (! $this->classId || ! $this->date) {
            $this->sessionId = 0; $this->attendance = []; $this->notes = [];
            return;
        }

        $class = SchoolClass::where('school_id', $this->schoolId())->findOrFail($this->classId);
        $session = app(AttendanceService::class)->openSession($class, $this->period, Carbon::parse($this->date));
        $this->sessionId = $session->id;

        $year = AcademicYear::where('school_id', $this->schoolId())->where('is_current', true)->first();
        $studentIds = Enrollment::where('school_class_id', $this->classId)
            ->when($year, fn($q) => $q->where('academic_year_id', $year->id))
            ->where('status', 'confirmed')
            ->pluck('student_id')->all();

        $existing = AttendanceEntry::where('attendance_session_id', $session->id)->get()->keyBy('student_id');
        $this->attendance = []; $this->notes = [];
        foreach ($studentIds as $sid) {
            $e = $existing->get($sid);
            $this->attendance[$sid] = $e ? ($e->status?->value ?? (string) $e->status) : 'present';
            $this->notes[$sid]      = $e?->reason ?? '';
        }
    }

    public function markAll(string $status): void
    {
        foreach (array_keys($this->attendance) as $sid) {
            $this->attendance[$sid] = $status;
        }
    }

    public function save(): void
    {
        if (! $this->sessionId) {
            $this->error('Sélectionnez une classe et une date.', position: 'toast-top toast-center', icon: 'o-exclamation-circle', css: 'alert-error', timeout: 4000);
            return;
        }

        foreach ($this->attendance as $sid => $status) {
            AttendanceEntry::updateOrCreate(
                ['attendance_session_id' => $this->sessionId, 'student_id' => $sid],
                ['status' => $status, 'reason' => $this->notes[$sid] ?? null]
            );
        }

        $notified = $this->notifyGuardians();

        $this->success('Appel enregistré.' . ($notified ? " {$notified} parent(s) notifié(s)." : ''),
            position: 'toast-top toast-end', icon: 'o-check-circle', css: 'alert-success', timeout: 3000);
    }

    private function notifyGuardians(): int
    {
        $session    = AttendanceSession::find($this->sessionId);
        $nonPresent = collect($this->attendance)->filter(fn($s) => $s !== 'present')->keys()->all();
        if (empty($nonPresent) || ! $session) {
            return 0;
        }

        $labels = ['absent' => 'absent(e)', 'late' => 'en retard', 'excused' => 'excusé(e)'];
        $count  = 0;

        foreach (Student::whereIn('id', $nonPresent)->with('guardians')->get() as $student) {
            $status = $this->attendance[$student->id];
            $reason = $this->notes[$student->id] ?? null;
            $waText = "🔔 *Absence signalée* — " . ($session->school?->name ?? config('app.name')) . "\n"
                . "Élève : {$student->full_name}\n"
                . "Statut : " . ($labels[$status] ?? $status) . "\n"
                . "Date : " . ($session->session_date?->format('d/m/Y') ?? today()->format('d/m/Y'))
                . ($reason ? "\nMotif : {$reason}" : '');

            foreach ($student->guardians as $g) {
                if ($g->email) {
                    try {
                        Mail::to($g->email)->send(new AbsenceNotificationMail($g, $student, $session, $status, $reason));
                        $count++;
                    } catch (\Throwable $e) {
                        Log::warning('Monitor absence email failed: ' . $e->getMessage());
                    }
                }
                app(WhatsAppService::class)->notifyModel($g, $waText);
            }
        }

        return $count;
    }

    public function with(): array
    {
        $students = collect();
        if ($this->classId && ! empty($this->attendance)) {
            $students = Student::whereIn('id', array_keys($this->attendance))
                ->when($this->studentSearch, fn($q) => $q->where('name', 'like', "%{$this->studentSearch}%"))
                ->orderBy('name')->get();
        }

        return [
            'classes'  => SchoolClass::where('school_id', $this->schoolId())->where('is_active', true)->orderBy('name')->get(),
            'students' => $students,
            'periods'  => [
                ['id' => 'morning',   'name' => 'Matin'],
                ['id' => 'afternoon', 'name' => 'Après-midi'],
            ],
            'present'  => collect($this->attendance)->filter(fn($s) => $s === 'present')->count(),
            'absent'   => collect($this->attendance)->filter(fn($s) => $s === 'absent')->count(),
        ];
    }
};
?>

<div class="p-4 lg:p-6 space-y-5">
    <x-header title="Prise de présences" subtitle="Faire l'appel et notifier les parents" separator>
        <x-slot:actions>
            <x-button icon="o-arrow-left" link="{{ route('monitor.dashboard') }}" wire:navigate class="btn-ghost btn-sm" />
        </x-slot:actions>
    </x-header>

    {{-- Selectors --}}
    <x-card shadow class="border-0">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <x-select label="Classe" wire:model.live="classId" placeholder="Choisir une classe"
                :options="$classes->map(fn($c) => ['id' => $c->id, 'name' => $c->name])->all()" />
            <x-input type="date" label="Date" wire:model.live="date" />
            <x-select label="Période" wire:model.live="period" :options="$periods" />
        </div>
    </x-card>

    @if(!$classId)
        <x-alert icon="o-information-circle" class="alert-info">Sélectionnez une classe pour faire l'appel.</x-alert>
    @elseif($students->isEmpty())
        <x-alert icon="o-user-group" class="alert-warning">Aucun élève inscrit dans cette classe.</x-alert>
    @else

    {{-- Toolbar --}}
    <div class="flex flex-col sm:flex-row sm:items-center gap-3 justify-between">
        <div class="flex flex-wrap gap-2">
            <x-button label="Tous présents" icon="o-check" wire:click="markAll('present')" class="btn-sm btn-success text-white" />
            <x-button label="Tous absents"  icon="o-x-mark" wire:click="markAll('absent')" class="btn-sm btn-error text-white" />
        </div>
        <div class="flex items-center gap-3">
            <span class="text-sm"><b class="text-green-600">{{ $present }}</b> présents · <b class="text-red-600">{{ $absent }}</b> absents</span>
            <x-input wire:model.live.debounce="studentSearch" placeholder="Rechercher…" icon="o-magnifying-glass" clearable class="input-sm max-w-40" />
        </div>
    </div>

    {{-- Student list --}}
    <div class="space-y-2">
        @foreach($students as $student)
        <div wire:key="mk-{{ $student->id }}" class="bg-base-100 rounded-xl border border-base-200 p-3">
            <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                <div class="flex items-center gap-3 flex-1 min-w-0">
                    <div class="w-9 h-9 rounded-full bg-amber-100 text-amber-700 flex items-center justify-center font-bold text-sm shrink-0">
                        {{ strtoupper(substr($student->name, 0, 1)) }}
                    </div>
                    <div class="min-w-0">
                        <p class="font-semibold text-sm truncate">{{ $student->full_name }}</p>
                        <p class="text-xs text-base-content/50">{{ $student->reference }}</p>
                    </div>
                </div>
                <div class="flex gap-1 flex-wrap">
                    @foreach(['present' => ['Présent','success'], 'absent' => ['Absent','error'], 'late' => ['Retard','warning'], 'excused' => ['Excusé','info']] as $val => $meta)
                    <label class="cursor-pointer">
                        <input type="radio" wire:model="attendance.{{ $student->id }}" value="{{ $val }}" class="sr-only peer" />
                        <span class="px-2.5 py-1.5 rounded-lg text-xs font-medium border transition-all
                            {{ ($attendance[$student->id] ?? '') === $val
                                ? 'bg-'.$meta[1].' text-'.$meta[1].'-content border-'.$meta[1]
                                : 'bg-base-100 border-base-200 text-base-content/50 hover:border-base-300' }}">
                            {{ $meta[0] }}
                        </span>
                    </label>
                    @endforeach
                </div>
            </div>
            @if(($attendance[$student->id] ?? 'present') !== 'present')
            <input type="text" wire:model.lazy="notes.{{ $student->id }}" placeholder="Motif (optionnel)"
                   class="input input-bordered input-xs w-full mt-2" />
            @endif
        </div>
        @endforeach
    </div>

    {{-- Save --}}
    <div class="flex justify-end sticky bottom-2">
        <x-button label="Enregistrer l'appel" icon="o-check-circle" wire:click="save" spinner="save"
                  class="btn-warning text-white shadow-lg" />
    </div>

    @endif
</div>
