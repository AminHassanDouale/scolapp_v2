<?php
use App\Models\AttendanceSession;
use App\Models\AttendanceEntry;
use App\Models\SchoolClass;
use App\Models\AcademicYear;
use App\Enums\AttendanceStatus;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Mary\Traits\Toast;
use Illuminate\Support\Carbon;

new #[Layout('layouts.app')] class extends Component {
    use Toast, WithPagination;

    public int    $classFilter = 0;
    public string $dateFilter  = '';
    public bool   $showFilters = false;

    public function with(): array
    {
        $schoolId    = auth()->user()->school_id;
        $today       = Carbon::today();
        $monthStart  = Carbon::today()->startOfMonth();

        $sessions = AttendanceSession::where('school_id', $schoolId)
            ->with(['schoolClass.grade', 'teacher'])
            ->withCount([
                'attendanceEntries as present_count' => fn($q) => $q->where('status', 'present'),
                'attendanceEntries as absent_count'  => fn($q) => $q->where('status', 'absent'),
                'attendanceEntries as late_count'    => fn($q) => $q->where('status', 'late'),
                'attendanceEntries as total_count',
            ])
            ->when($this->classFilter, fn($q) => $q->where('school_class_id', $this->classFilter))
            ->when($this->dateFilter,  fn($q) => $q->whereDate('session_date', $this->dateFilter))
            ->orderByDesc('session_date')
            ->paginate(15);

        // Today stats
        $todaySessions = AttendanceSession::where('school_id', $schoolId)
            ->whereDate('session_date', $today)->pluck('id');

        $absentToday = AttendanceEntry::whereIn('attendance_session_id', $todaySessions)
            ->where('status', AttendanceStatus::ABSENT->value)->count();
        $lateToday   = AttendanceEntry::whereIn('attendance_session_id', $todaySessions)
            ->where('status', AttendanceStatus::LATE->value)->count();

        // Monthly attendance rate
        $monthSessions = AttendanceSession::where('school_id', $schoolId)
            ->whereBetween('session_date', [$monthStart, $today])->pluck('id');

        $totalEntries = AttendanceEntry::whereIn('attendance_session_id', $monthSessions)->count();
        $presentEntries = AttendanceEntry::whereIn('attendance_session_id', $monthSessions)
            ->where('status', AttendanceStatus::PRESENT->value)->count();
        $attendanceRate = $totalEntries > 0 ? round(($presentEntries / $totalEntries) * 100) : 0;

        // Recent absences
        $recentAbsences = AttendanceEntry::with(['student', 'attendanceSession.schoolClass'])
            ->whereIn('attendance_session_id',
                AttendanceSession::where('school_id', $schoolId)->pluck('id')
            )
            ->where('status', AttendanceStatus::ABSENT->value)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return [
            'sessions'       => $sessions,
            'classes'        => SchoolClass::where('school_id', $schoolId)->with('grade')->orderBy('name')->get(),
            'todaySessions'  => $todaySessions->count(),
            'absentToday'    => $absentToday,
            'lateToday'      => $lateToday,
            'attendanceRate' => $attendanceRate,
            'recentAbsences' => $recentAbsences,
        ];
    }
};
?>

<div>
    <x-header title="Absences & Présences" separator progress-indicator>
        <x-slot:actions>
            <x-button label="Faire l'appel" icon="o-clipboard-document-check"
                      :link="route('admin.attendance.mark')"
                      class="btn-primary" wire:navigate />
        </x-slot:actions>
    </x-header>

    {{-- Stats --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="rounded-2xl bg-linear-to-br from-primary to-primary/70 p-4 text-primary-content">
            <p class="text-sm opacity-80">Sessions aujourd'hui</p>
            <p class="text-3xl font-black mt-1">{{ $todaySessions }}</p>
        </div>
        <div class="rounded-2xl bg-linear-to-br from-error to-error/70 p-4 text-error-content">
            <p class="text-sm opacity-80">Absents aujourd'hui</p>
            <p class="text-3xl font-black mt-1">{{ $absentToday }}</p>
        </div>
        <div class="rounded-2xl bg-linear-to-br from-warning to-warning/70 p-4 text-warning-content">
            <p class="text-sm opacity-80">Retards aujourd'hui</p>
            <p class="text-3xl font-black mt-1">{{ $lateToday }}</p>
        </div>
        <div class="rounded-2xl bg-linear-to-br from-success to-success/70 p-4 text-success-content">
            <p class="text-sm opacity-80">Taux de présence (mois)</p>
            <p class="text-3xl font-black mt-1">{{ $attendanceRate }}%</p>
        </div>
    </div>

    {{-- Filters bar --}}
    <div class="flex items-center gap-3 mb-4 flex-wrap">
        <x-select wire:model.live="classFilter"
                  :options="$classes" option-value="id" option-label="name"
                  placeholder="Toutes les classes" placeholder-value="0"
                  class="select-sm w-48" />
        <x-datepicker wire:model.live="dateFilter" icon="o-calendar" placeholder="Filtrer par date" class="input-sm w-44" :config="['dateFormat' => 'Y-m-d', 'altFormat' => 'd/m/Y', 'altInput' => true, 'locale' => ['firstDayOfWeek' => 1]]" />
        @if($classFilter || $dateFilter)
        <x-button wire:click="$set('classFilter',0); $set('dateFilter','')" icon="o-x-mark"
                  class="btn-ghost btn-sm" label="Effacer" />
        @endif
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Sessions table --}}
        <div class="lg:col-span-2">
            <x-card title="Sessions d'appel" separator>
                <div class="overflow-x-auto"><table class="table w-full">
                    <thead><tr>
                        <th>Classe</th>
                        <th>Date</th>
                        <th>Période</th>
                        <th class="text-center">Présents</th>
                        <th class="text-center">Absents</th>
                        <th class="text-center">Retards</th>
                        <th class="w-16">Actions</th>
                    </tr></thead><tbody>

                    @forelse($sessions as $session)
                    @php
                        $total   = $session->entries_count ?? $session->attendanceEntries()->count();
                        $period  = match($session->period ?? '') {
                            'morning'   => 'Matin',
                            'afternoon' => 'Après-midi',
                            'full_day'  => 'Journée',
                            default     => $session->period ?? '—',
                        };
                    @endphp
                    <tr wire:key="session-{{ $session->id }}" class="hover">
                        <td>
                            <div>
                                <p class="font-semibold text-sm">{{ $session->schoolClass?->name }}</p>
                                <p class="text-xs text-base-content/50">{{ $session->schoolClass?->grade?->name }}</p>
                            </div>
                        </td>
                        <td class="text-sm">{{ $session->session_date?->format('d/m/Y') }}</td>
                        <td><x-badge :value="$period" class="badge-outline badge-sm" /></td>
                        <td class="text-center text-success font-semibold">{{ $session->present_count ?? '—' }}</td>
                        <td class="text-center text-error font-semibold">{{ $session->absent_count ?? '—' }}</td>
                        <td class="text-center text-warning font-semibold">{{ $session->late_count ?? '—' }}</td>
                        <td>
                            <x-button icon="o-pencil"
                                      :link="route('admin.attendance.mark') . '?session=' . $session->id"
                                      class="btn-ghost btn-xs" tooltip="Modifier" />
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-10 text-base-content/40">
                            <x-icon name="o-clipboard-document-list" class="w-10 h-10 mx-auto mb-2 opacity-20" />
                            <p>Aucune session d'appel</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody></table></div>
                <div class="mt-4">{{ $sessions->links() }}</div>
            </x-card>
        </div>

        {{-- Recent absences --}}
        <div>
            <x-card title="Absences récentes" separator>
                <div class="space-y-2">
                    @forelse($recentAbsences as $entry)
                    <div wire:key="absence-{{ $entry->id }}" class="flex items-center gap-3 py-2 border-b border-base-200 last:border-0">
                        <div class="w-8 h-8 rounded-full bg-error/10 flex items-center justify-center text-error text-xs font-bold shrink-0">
                            {{ substr($entry->student?->name ?? '?', 0, 1) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-sm truncate">{{ $entry->student?->full_name }}</p>
                            <p class="text-xs text-base-content/60">
                                {{ $entry->attendanceSession?->schoolClass?->name }} ·
                                {{ $entry->attendanceSession?->session_date?->format('d/m') }}
                            </p>
                        </div>
                        <x-badge value="Absent" class="badge-error badge-xs" />
                    </div>
                    @empty
                    <p class="text-center text-base-content/40 py-6 text-sm">Aucune absence récente</p>
                    @endforelse
                </div>
            </x-card>
        </div>
    </div>
</div>
