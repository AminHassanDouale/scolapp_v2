<?php
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Mary\Traits\Toast;
use App\Models\Teacher;
use App\Models\SchoolClass;
use App\Models\AttendanceSession;
use App\Models\AttendanceEntry;
use App\Models\Student;
use App\Models\Enrollment;

new #[Layout('layouts.teacher')] class extends Component {
    use Toast;

    public ?int $selectedClassId = null;
    public string $selectedDate  = '';
    public array $attendance     = [];
    public bool $sessionSaved    = false;

    public function mount(): void
    {
        $this->selectedDate = today()->format('Y-m-d');
    }

    public function updatedSelectedClassId(): void
    {
        $this->loadExistingSession();
    }

    private function loadExistingSession(): void
    {
        if (!$this->selectedClassId) return;

        $session = AttendanceSession::where('school_class_id', $this->selectedClassId)
            ->whereDate('date', $this->selectedDate)
            ->with('entries')
            ->first();

        if ($session) {
            $this->attendance   = $session->entries->pluck('status', 'student_id')->toArray();
            $this->sessionSaved = true;
        } else {
            // Pre-fill with 'present'
            $students = $this->getStudents();
            $this->attendance = $students->pluck('id')->mapWithKeys(fn($id) => [$id => 'present'])->toArray();
            $this->sessionSaved = false;
        }
    }

    private function getStudents()
    {
        if (!$this->selectedClassId) return collect();
        return Student::whereHas('enrollments', fn($q) => $q->where('school_class_id', $this->selectedClassId)->where('status', 'active'))
            ->orderBy('name')
            ->get();
    }

    public function save(): void
    {
        if (!$this->selectedClassId) {
            $this->error('Sélectionnez une classe.', position: 'toast-top toast-center', timeout: 3000);
            return;
        }

        $teacher = Teacher::where('user_id', auth()->id())->first();

        $session = AttendanceSession::firstOrCreate(
            ['school_class_id' => $this->selectedClassId, 'date' => $this->selectedDate],
            ['teacher_id' => $teacher?->id, 'school_id' => auth()->user()->school_id]
        );

        foreach ($this->attendance as $studentId => $status) {
            AttendanceEntry::updateOrCreate(
                ['attendance_session_id' => $session->id, 'student_id' => $studentId],
                ['status' => $status]
            );
        }

        $this->sessionSaved = true;
        $this->success('Présences enregistrées !', position: 'toast-top toast-end', icon: 'o-check-circle', css: 'alert-success', timeout: 3000);
    }

    public function with(): array
    {
        $user    = auth()->user();
        $teacher = Teacher::where('user_id', $user->id)->with('schoolClasses')->first();
        $classes = $teacher?->schoolClasses ?? collect();
        $students = $this->selectedClassId ? $this->getStudents() : collect();

        if ($this->selectedClassId && $students->isNotEmpty() && empty($this->attendance)) {
            $this->loadExistingSession();
        }

        return compact('classes', 'students');
    }
};
?>

<div class="p-4 lg:p-6 space-y-6">
    <x-header title="{{ __('navigation.attendance') }}" subtitle="{{ __('navigation.mark_attendance') }}" separator>
        <x-slot:actions>
            <x-button icon="o-arrow-left" link="{{ route('teacher.dashboard') }}" wire:navigate class="btn-ghost btn-sm" />
        </x-slot:actions>
    </x-header>

    {{-- Filters --}}
    <x-card shadow class="border-0">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <x-select label="{{ __('navigation.class') }}" wire:model.live="selectedClassId" placeholder="Choisir une classe..." :options="$classes->map(fn($c) => ['id' => $c->id, 'name' => $c->name])->all()" />
            <x-input type="date" label="{{ __('navigation.date') }}" wire:model.live="selectedDate" />
        </div>
    </x-card>

    @if($selectedClassId && $students->isNotEmpty())
    <x-card shadow class="border-0">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-bold text-lg">
                {{ $students->count() }} élèves
                @if($sessionSaved)
                    <x-badge value="Sauvegardé" class="badge-success badge-sm ml-2" />
                @endif
            </h3>
            <div class="flex gap-2">
                <x-button label="Tous présents" icon="o-check" class="btn-xs btn-success btn-outline"
                    wire:click="$set('attendance', @js($students->pluck('id')->mapWithKeys(fn($id) => [$id => 'present'])->all()))" />
                <x-button label="Sauvegarder" icon="o-cloud-arrow-up" class="btn-sm btn-primary" wire:click="save" wire:loading.attr="disabled" spinner="save" />
            </div>
        </div>

        <div class="space-y-2">
            @foreach($students as $student)
            <div class="flex items-center gap-3 p-3 rounded-xl bg-base-50 border border-base-200 hover:border-indigo-200 transition-colors">
                <div class="w-9 h-9 rounded-full bg-indigo-100 flex items-center justify-center flex-shrink-0">
                    <span class="text-sm font-bold text-indigo-700">{{ substr($student->name, 0, 1) }}</span>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-semibold text-sm truncate">{{ $student->full_name }}</p>
                    <p class="text-xs text-base-content/50">{{ $student->reference }}</p>
                </div>
                <div class="flex gap-1">
                    @foreach(['present' => 'Présent', 'absent' => 'Absent', 'late' => 'Retard', 'excused' => 'Excusé'] as $status => $label)
                    <label class="cursor-pointer">
                        <input type="radio" wire:model="attendance.{{ $student->id }}" value="{{ $status }}" class="sr-only" />
                        <span class="px-2 py-1 rounded-lg text-xs font-medium border transition-all
                            {{ ($attendance[$student->id] ?? '') === $status
                                ? match($status) {
                                    'present' => 'bg-success text-success-content border-success',
                                    'absent'  => 'bg-error text-error-content border-error',
                                    'late'    => 'bg-warning text-warning-content border-warning',
                                    'excused' => 'bg-info text-info-content border-info',
                                    default   => 'bg-base-200'
                                  }
                                : 'bg-base-100 border-base-200 text-base-content/50 hover:border-base-300'
                            }}">
                            {{ $label }}
                        </span>
                    </label>
                    @endforeach
                </div>
            </div>
            @endforeach
        </div>

        <div class="mt-4 pt-4 border-t border-base-200 flex justify-end">
            <x-button label="Enregistrer les présences" icon="o-cloud-arrow-up" class="btn-primary" wire:click="save" wire:loading.attr="disabled" spinner="save" />
        </div>
    </x-card>
    @elseif($selectedClassId)
        <x-alert icon="o-information-circle" class="alert-info">Aucun élève inscrit dans cette classe.</x-alert>
    @else
        <x-alert icon="o-arrow-up" class="alert-info">Sélectionnez une classe pour commencer la prise de présences.</x-alert>
    @endif
</div>
