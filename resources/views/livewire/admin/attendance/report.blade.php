<?php
use App\Models\SchoolClass;
use App\Models\AcademicYear;
use App\Models\AttendanceSession;
use App\Models\AttendanceEntry;
use App\Enums\AttendanceStatus;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Carbon;

new #[Layout('layouts.app')] class extends Component {

    public int    $classFilter = 0;
    public int    $yearFilter  = 0;
    public string $from        = '';
    public string $to          = '';

    public function mount(): void
    {
        $this->from = Carbon::now()->startOfMonth()->format('Y-m-d');
        $this->to   = Carbon::now()->format('Y-m-d');

        $current = AcademicYear::where('school_id', auth()->user()->school_id)
            ->where('is_current', true)->first();
        if ($current) $this->yearFilter = $current->id;
    }

    public function with(): array
    {
        $schoolId = auth()->user()->school_id;

        $sessionIds = AttendanceSession::where('school_id', $schoolId)
            ->when($this->classFilter, fn($q) => $q->where('school_class_id', $this->classFilter))
            ->when($this->from, fn($q) => $q->whereDate('session_date', '>=', $this->from))
            ->when($this->to,   fn($q) => $q->whereDate('session_date', '<=', $this->to))
            ->pluck('id');

        $totalEntries   = AttendanceEntry::whereIn('attendance_session_id', $sessionIds)->count();
        $presentCount   = AttendanceEntry::whereIn('attendance_session_id', $sessionIds)
            ->where('status', AttendanceStatus::PRESENT->value)->count();
        $absentCount    = AttendanceEntry::whereIn('attendance_session_id', $sessionIds)
            ->where('status', AttendanceStatus::ABSENT->value)->count();
        $lateCount      = AttendanceEntry::whereIn('attendance_session_id', $sessionIds)
            ->where('status', AttendanceStatus::LATE->value)->count();
        $excusedCount   = AttendanceEntry::whereIn('attendance_session_id', $sessionIds)
            ->where('status', AttendanceStatus::EXCUSED->value)->count();

        $presentRate = $totalEntries > 0 ? round(($presentCount / $totalEntries) * 100, 1) : 0;

        // Daily breakdown (last 30 days max)
        $dailyData = [];
        $start = Carbon::parse($this->from);
        $end   = Carbon::parse($this->to);
        $days  = min(30, $start->diffInDays($end) + 1);
        for ($i = 0; $i < $days; $i++) {
            $day = $start->copy()->addDays($i);
            $daySessions = AttendanceSession::where('school_id', $schoolId)
                ->when($this->classFilter, fn($q) => $q->where('school_class_id', $this->classFilter))
                ->whereDate('session_date', $day)->pluck('id');
            $dayTotal   = AttendanceEntry::whereIn('attendance_session_id', $daySessions)->count();
            $dayPresent = AttendanceEntry::whereIn('attendance_session_id', $daySessions)
                ->where('status', AttendanceStatus::PRESENT->value)->count();
            $dailyData[] = [
                'date'    => $day->format('d/m'),
                'total'   => $dayTotal,
                'present' => $dayPresent,
                'rate'    => $dayTotal > 0 ? round(($dayPresent / $dayTotal) * 100) : 0,
            ];
        }

        // Top absent students
        $topAbsent = AttendanceEntry::whereIn('attendance_session_id', $sessionIds)
            ->where('status', AttendanceStatus::ABSENT->value)
            ->with('enrollment.student')
            ->select('enrollment_id', \DB::raw('COUNT(*) as absences'))
            ->groupBy('enrollment_id')
            ->orderByDesc('absences')
            ->limit(10)
            ->get();

        $classes      = SchoolClass::where('school_id', $schoolId)
            ->when($this->yearFilter, fn($q) => $q->where('academic_year_id', $this->yearFilter))
            ->orderBy('name')->get();
        $academicYears = AcademicYear::where('school_id', $schoolId)->orderByDesc('start_date')->get();

        return compact(
            'totalEntries', 'presentCount', 'absentCount', 'lateCount', 'excusedCount',
            'presentRate', 'dailyData', 'topAbsent', 'classes', 'academicYears'
        );
    }
};
?>

<div>
    <x-header title="Rapport d'absences" separator progress-indicator />

    {{-- Filters --}}
    <div class="flex flex-wrap items-center gap-3 mb-6">
        <x-select wire:model.live="yearFilter"
                  :options="$academicYears" option-value="id" option-label="name"
                  placeholder="Toutes les années" placeholder-value="0" class="select-sm w-44" />
        <x-select wire:model.live="classFilter"
                  :options="$classes" option-value="id" option-label="name"
                  placeholder="Toutes les classes" placeholder-value="0" class="select-sm w-44" />
        <x-datepicker wire:model.live="from" icon="o-calendar" placeholder="Du" class="input-sm w-36" :config="['dateFormat' => 'Y-m-d', 'altFormat' => 'd/m/Y', 'altInput' => true, 'locale' => ['firstDayOfWeek' => 1]]" />
        <span class="text-base-content/40 text-sm">→</span>
        <x-datepicker wire:model.live="to" icon="o-calendar" placeholder="Au" class="input-sm w-36" :config="['dateFormat' => 'Y-m-d', 'altFormat' => 'd/m/Y', 'altInput' => true, 'locale' => ['firstDayOfWeek' => 1]]" />
    </div>

    {{-- Stat cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-4 mb-6">
        <div class="rounded-2xl bg-gradient-to-br from-success to-success/70 p-4 text-success-content text-center">
            <p class="text-xs opacity-80">Taux présence</p>
            <p class="text-3xl font-black">{{ $presentRate }}%</p>
        </div>
        <div class="rounded-2xl bg-base-200 p-4 text-center">
            <p class="text-xs text-base-content/60">Total</p>
            <p class="text-3xl font-black">{{ number_format($totalEntries) }}</p>
        </div>
        <div class="rounded-2xl bg-success/10 p-4 text-center">
            <p class="text-xs text-base-content/60">Présents</p>
            <p class="text-3xl font-black text-success">{{ number_format($presentCount) }}</p>
        </div>
        <div class="rounded-2xl bg-error/10 p-4 text-center">
            <p class="text-xs text-base-content/60">Absents</p>
            <p class="text-3xl font-black text-error">{{ number_format($absentCount) }}</p>
        </div>
        <div class="rounded-2xl bg-warning/10 p-4 text-center">
            <p class="text-xs text-base-content/60">Retards</p>
            <p class="text-3xl font-black text-warning">{{ number_format($lateCount) }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Daily presence chart --}}
        <x-card title="Taux de présence quotidien" separator>
            @if(count($dailyData))
            <div class="flex items-end gap-1" style="height: 140px;">
                @foreach($dailyData as $day)
                @php $h = max(2, $day['rate']); @endphp
                <div class="flex-1 flex flex-col items-center gap-0.5 group">
                    <div class="tooltip tooltip-top w-full flex items-end justify-center"
                         data-tip="{{ $day['date'] }}: {{ $day['rate'] }}%">
                        <div class="w-full rounded-t {{ $day['rate'] >= 80 ? 'bg-success' : ($day['rate'] >= 60 ? 'bg-warning' : 'bg-error') }}"
                             style="height: {{ $h }}%"></div>
                    </div>
                    @if(count($dailyData) <= 15)
                    <span class="text-xs text-base-content/40 rotate-45 origin-left whitespace-nowrap mt-1">{{ $day['date'] }}</span>
                    @endif
                </div>
                @endforeach
            </div>
            @else
            <p class="text-center py-8 text-base-content/40">Aucune donnée pour cette période.</p>
            @endif
        </x-card>

        {{-- Top absent students --}}
        <x-card title="Top 10 élèves les plus absents" separator>
            @if($topAbsent->count())
            <div class="space-y-2">
                @php $maxAbs = $topAbsent->max('absences') ?: 1; @endphp
                @foreach($topAbsent as $entry)
                <div class="flex items-center gap-3">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold truncate">
                            {{ $entry->enrollment?->student?->full_name ?? '—' }}
                        </p>
                        <div class="w-full bg-base-200 rounded-full h-1.5 mt-1">
                            <div class="bg-error h-1.5 rounded-full"
                                 style="width: {{ ($entry->absences / $maxAbs) * 100 }}%"></div>
                        </div>
                    </div>
                    <span class="text-sm font-bold text-error shrink-0">{{ $entry->absences }}×</span>
                </div>
                @endforeach
            </div>
            @else
            <p class="text-center py-8 text-base-content/40">Aucune absence enregistrée.</p>
            @endif
        </x-card>
    </div>
</div>
