<?php
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\AttendanceEntry;

new #[Layout('layouts.guardian')] class extends Component {
    public ?string $studentUuid = null;
    public ?int $selectedStudentId = null;

    public function mount(?string $student = null): void
    {
        $this->studentUuid = $student;
    }

    public function with(): array
    {
        $guardian = Guardian::where('user_id', auth()->id())->with('students')->first();
        $students = $guardian?->students ?? collect();

        $selectedStudent = null;
        if ($this->studentUuid) {
            $selectedStudent = $students->firstWhere('uuid', $this->studentUuid);
        } elseif ($students->isNotEmpty()) {
            $selectedStudent = $students->first();
        }

        $entries = $selectedStudent
            ? AttendanceEntry::where('student_id', $selectedStudent->id)
                ->with('session.schoolClass')
                ->orderByDesc('created_at')
                ->paginate(20)
            : collect();

        $stats = $selectedStudent ? [
            'present' => AttendanceEntry::where('student_id', $selectedStudent->id)->where('status', 'present')->count(),
            'absent'  => AttendanceEntry::where('student_id', $selectedStudent->id)->where('status', 'absent')->count(),
            'late'    => AttendanceEntry::where('student_id', $selectedStudent->id)->where('status', 'late')->count(),
        ] : [];

        return compact('students', 'selectedStudent', 'entries', 'stats');
    }
};
?>

<div class="p-4 lg:p-6 space-y-6">
    <x-header title="{{ __('navigation.attendance') }}" subtitle="Suivi des présences" separator>
        <x-slot:actions>
            <x-button icon="o-arrow-left" link="{{ route('guardian.dashboard') }}" wire:navigate class="btn-ghost btn-sm" />
        </x-slot:actions>
    </x-header>

    @if($students->count() > 1)
    <x-card shadow class="border-0">
        <div class="flex gap-2 flex-wrap">
            @foreach($students as $student)
            <a href="{{ route('guardian.attendance', ['student' => $student->uuid]) }}" wire:navigate>
                <x-badge :value="$student->full_name"
                    class="{{ $selectedStudent?->id === $student->id ? 'badge-success' : 'badge-ghost' }} badge-md cursor-pointer" />
            </a>
            @endforeach
        </div>
    </x-card>
    @endif

    @if($selectedStudent && !empty($stats))
    <div class="grid grid-cols-3 gap-4">
        <x-card class="border-0 shadow-sm text-center bg-gradient-to-br from-green-50 to-white">
            <p class="text-3xl font-black text-success">{{ $stats['present'] }}</p>
            <p class="text-xs text-base-content/60 mt-1">Présences</p>
        </x-card>
        <x-card class="border-0 shadow-sm text-center bg-gradient-to-br from-red-50 to-white">
            <p class="text-3xl font-black text-error">{{ $stats['absent'] }}</p>
            <p class="text-xs text-base-content/60 mt-1">Absences</p>
        </x-card>
        <x-card class="border-0 shadow-sm text-center bg-gradient-to-br from-yellow-50 to-white">
            <p class="text-3xl font-black text-warning">{{ $stats['late'] }}</p>
            <p class="text-xs text-base-content/60 mt-1">Retards</p>
        </x-card>
    </div>

    <x-card shadow class="border-0 p-0 overflow-hidden">
        <table class="table table-sm w-full">
            <thead class="bg-base-200">
                <tr>
                    <th>Date</th>
                    <th>Classe</th>
                    <th class="text-center">Statut</th>
                </tr>
            </thead>
            <tbody>
                @forelse($entries as $entry)
                <tr class="hover border-b border-base-100">
                    <td>{{ $entry->session?->date?->format('d/m/Y') }}</td>
                    <td>{{ $entry->session?->schoolClass?->name ?? '—' }}</td>
                    <td class="text-center">
                        @php $badge = match($entry->status) { 'present' => 'badge-success', 'absent' => 'badge-error', 'late' => 'badge-warning', 'excused' => 'badge-info', default => 'badge-ghost' };
                        $label = match($entry->status) { 'present' => 'Présent', 'absent' => 'Absent', 'late' => 'Retard', 'excused' => 'Excusé', default => $entry->status }; @endphp
                        <x-badge :value="$label" class="{{ $badge }} badge-sm" />
                    </td>
                </tr>
                @empty
                <tr><td colspan="3" class="text-center py-10 text-base-content/40">Aucune donnée disponible</td></tr>
                @endforelse
            </tbody>
        </table>
        @if(method_exists($entries, 'links'))<div class="p-4">{{ $entries->links() }}</div>@endif
    </x-card>
    @endif
</div>
