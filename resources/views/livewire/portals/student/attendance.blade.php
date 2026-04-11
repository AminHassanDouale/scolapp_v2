<?php
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Student;
use App\Models\AttendanceEntry;

new #[Layout('layouts.student')] class extends Component {
    public function with(): array
    {
        $student = Student::where('user_id', auth()->id())->first();

        $entries = $student
            ? AttendanceEntry::where('student_id', $student->id)
                ->with('session.schoolClass')
                ->orderByDesc('created_at')
                ->paginate(20)
            : collect();

        $stats = $student ? [
            'present' => AttendanceEntry::where('student_id', $student->id)->where('status', 'present')->count(),
            'absent'  => AttendanceEntry::where('student_id', $student->id)->where('status', 'absent')->count(),
            'late'    => AttendanceEntry::where('student_id', $student->id)->where('status', 'late')->count(),
            'excused' => AttendanceEntry::where('student_id', $student->id)->where('status', 'excused')->count(),
        ] : [];

        $total    = array_sum($stats);
        $presRate = $total > 0 ? round(($stats['present'] / $total) * 100) : 0;

        return compact('student', 'entries', 'stats', 'presRate');
    }
};
?>

<div class="p-4 lg:p-6 space-y-6">
    <x-header title="{{ __('navigation.attendance') }}" subtitle="Mes présences" separator>
        <x-slot:actions>
            <x-button icon="o-arrow-left" link="{{ route('student.dashboard') }}" wire:navigate class="btn-ghost btn-sm" />
        </x-slot:actions>
    </x-header>

    @if(!empty($stats))
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
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
        <x-card class="border-0 shadow-sm text-center bg-gradient-to-br from-violet-50 to-white">
            <p class="text-3xl font-black text-violet-700">{{ $presRate }}%</p>
            <p class="text-xs text-base-content/60 mt-1">Taux de présence</p>
            <div class="w-full bg-base-200 rounded-full h-1.5 mt-2">
                <div class="h-1.5 rounded-full {{ $presRate >= 75 ? 'bg-success' : ($presRate >= 50 ? 'bg-warning' : 'bg-error') }}" style="width: {{ $presRate }}%"></div>
            </div>
        </x-card>
    </div>
    @endif

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
                    <td>{{ $entry->session?->session_date?->format('d/m/Y') }}</td>
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
</div>
