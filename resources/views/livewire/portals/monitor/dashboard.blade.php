<?php
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Mary\Traits\Toast;
use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\AttendanceEntry;
use App\Models\SchoolClass;

new #[Layout('layouts.monitor')] class extends Component {
    use Toast;

    public function with(): array
    {
        $schoolId = auth()->user()->school_id;

        // Today stats
        $todaySessions    = AttendanceSession::where('school_id', $schoolId)->whereDate('date', today())->count();
        $todayAbsent      = AttendanceEntry::whereHas('session', fn($q) => $q->where('school_id', $schoolId)->whereDate('date', today()))
            ->where('status', 'absent')->count();
        $todayLate        = AttendanceEntry::whereHas('session', fn($q) => $q->where('school_id', $schoolId)->whereDate('date', today()))
            ->where('status', 'late')->count();
        $totalClasses     = SchoolClass::where('school_id', $schoolId)->where('is_active', true)->count();

        // Recent absences
        $recentAbsences = AttendanceEntry::whereHas('session', fn($q) => $q->where('school_id', $schoolId))
            ->where('status', 'absent')
            ->with(['student', 'session.schoolClass'])
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();

        return compact('todaySessions', 'todayAbsent', 'todayLate', 'totalClasses', 'recentAbsences');
    }
};
?>

<div class="p-4 lg:p-6 space-y-6">
    <x-header title="{{ __('navigation.monitor_portal') }}" subtitle="{{ now()->isoFormat('dddd D MMMM Y') }}" separator>
        <x-slot:actions>
            <x-badge value="{{ __('navigation.monitor') }}" class="badge-warning badge-lg" />
        </x-slot:actions>
    </x-header>

    {{-- Welcome banner --}}
    <div class="relative overflow-hidden rounded-2xl p-6 text-white" style="background: linear-gradient(135deg, #d97706 0%, #f59e0b 50%, #fbbf24 100%)">
        <div class="absolute top-0 right-0 w-40 h-40 rounded-full bg-white/10 -translate-y-10 translate-x-10"></div>
        <div class="relative">
            <p class="text-amber-800 text-sm font-medium">{{ __('navigation.welcome_back') }}</p>
            <h2 class="text-2xl font-black mt-1 text-amber-900">{{ auth()->user()->full_name }}</h2>
            <p class="text-amber-800 mt-1">{{ __('navigation.surveillance_today') }}</p>
        </div>
    </div>

    {{-- KPI cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <x-card class="border-0 shadow-sm bg-gradient-to-br from-amber-50 to-white">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-amber-100 flex items-center justify-center">
                    <x-icon name="o-building-office" class="w-5 h-5 text-amber-600" />
                </div>
                <div>
                    <p class="text-2xl font-black text-amber-700">{{ $totalClasses }}</p>
                    <p class="text-xs text-base-content/60">Classes</p>
                </div>
            </div>
        </x-card>

        <x-card class="border-0 shadow-sm bg-gradient-to-br from-green-50 to-white">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-green-100 flex items-center justify-center">
                    <x-icon name="o-check-circle" class="w-5 h-5 text-green-600" />
                </div>
                <div>
                    <p class="text-2xl font-black text-green-700">{{ $todaySessions }}</p>
                    <p class="text-xs text-base-content/60">Sessions aujourd'hui</p>
                </div>
            </div>
        </x-card>

        <x-card class="border-0 shadow-sm bg-gradient-to-br from-red-50 to-white">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-red-100 flex items-center justify-center">
                    <x-icon name="o-x-circle" class="w-5 h-5 text-red-600" />
                </div>
                <div>
                    <p class="text-2xl font-black text-red-700">{{ $todayAbsent }}</p>
                    <p class="text-xs text-base-content/60">Absents</p>
                </div>
            </div>
        </x-card>

        <x-card class="border-0 shadow-sm bg-gradient-to-br from-orange-50 to-white">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-orange-100 flex items-center justify-center">
                    <x-icon name="o-clock" class="w-5 h-5 text-orange-600" />
                </div>
                <div>
                    <p class="text-2xl font-black text-orange-700">{{ $todayLate }}</p>
                    <p class="text-xs text-base-content/60">Retards</p>
                </div>
            </div>
        </x-card>
    </div>

    {{-- Quick actions --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <x-card title="Actions rapides" shadow separator>
            <div class="grid grid-cols-2 gap-3">
                <a href="{{ route('monitor.attendance') }}" wire:navigate
                   class="flex flex-col items-center gap-2 p-4 rounded-xl bg-amber-50 hover:bg-amber-100 transition-colors text-center group">
                    <div class="w-10 h-10 rounded-full bg-amber-600 flex items-center justify-center group-hover:scale-110 transition-transform">
                        <x-icon name="o-calendar-days" class="w-5 h-5 text-white" />
                    </div>
                    <span class="text-xs font-semibold text-amber-700">Présences</span>
                </a>
                <a href="{{ route('monitor.students') }}" wire:navigate
                   class="flex flex-col items-center gap-2 p-4 rounded-xl bg-orange-50 hover:bg-orange-100 transition-colors text-center group">
                    <div class="w-10 h-10 rounded-full bg-orange-600 flex items-center justify-center group-hover:scale-110 transition-transform">
                        <x-icon name="o-user-group" class="w-5 h-5 text-white" />
                    </div>
                    <span class="text-xs font-semibold text-orange-700">Élèves</span>
                </a>
                <a href="{{ route('monitor.schedule') }}" wire:navigate
                   class="flex flex-col items-center gap-2 p-4 rounded-xl bg-yellow-50 hover:bg-yellow-100 transition-colors text-center group">
                    <div class="w-10 h-10 rounded-full bg-yellow-600 flex items-center justify-center group-hover:scale-110 transition-transform">
                        <x-icon name="o-clock" class="w-5 h-5 text-white" />
                    </div>
                    <span class="text-xs font-semibold text-yellow-700">Planning</span>
                </a>
            </div>
        </x-card>

        {{-- Recent absences --}}
        <x-card title="Absences récentes" shadow separator>
            @forelse($recentAbsences as $entry)
            <div class="flex items-center gap-3 py-2 border-b border-base-100 last:border-0">
                <div class="w-8 h-8 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0">
                    <span class="text-xs font-bold text-red-700">{{ substr($entry->student?->name ?? '?', 0, 1) }}</span>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium truncate">{{ $entry->student?->full_name }}</p>
                    <p class="text-xs text-base-content/50">{{ $entry->session?->schoolClass?->name }} · {{ $entry->session?->date?->format('d/m') }}</p>
                </div>
                <x-badge value="Absent" class="badge-error badge-sm" />
            </div>
            @empty
            <div class="text-center py-8 text-base-content/40">
                <x-icon name="o-check-circle" class="w-10 h-10 mx-auto mb-2 text-success" />
                <p class="text-sm">Aucune absence aujourd'hui</p>
            </div>
            @endforelse
        </x-card>
    </div>
</div>
