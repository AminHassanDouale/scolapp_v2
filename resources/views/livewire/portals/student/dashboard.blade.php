<?php
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Mary\Traits\Toast;
use App\Models\Student;
use App\Models\Announcement;
use App\Models\AttendanceEntry;
use App\Models\StudentScore;

new #[Layout('layouts.student')] class extends Component {
    use Toast;

    public function with(): array
    {
        $user    = auth()->user();
        $student = Student::where('user_id', $user->id)
            ->with(['enrollments.schoolClass', 'enrollments.grade'])
            ->first();

        $enrollment = $student?->enrollments?->where('status', 'active')->first();

        $absences = $student
            ? AttendanceEntry::where('student_id', $student->id)->where('status', 'absent')->count()
            : 0;

        $totalScores = $student
            ? StudentScore::where('student_id', $student->id)->count()
            : 0;

        $avgScore = $student
            ? StudentScore::where('student_id', $student->id)
                ->whereHas('assessment', fn($q) => $q->where('max_score', '>', 0))
                ->selectRaw('AVG(score / assessments.max_score * 100) as avg_pct')
                ->join('assessments', 'student_scores.assessment_id', '=', 'assessments.id')
                ->value('avg_pct')
            : null;

        $announcements = Announcement::where('school_id', $user->school_id)
            ->where('is_published', true)
            ->orderByDesc('published_at')
            ->limit(4)
            ->get();

        $recentScores = $student
            ? StudentScore::where('student_id', $student->id)
                ->with(['assessment.subject'])
                ->orderByDesc('created_at')
                ->limit(5)
                ->get()
            : collect();

        return compact('student', 'enrollment', 'absences', 'totalScores', 'avgScore', 'announcements', 'recentScores');
    }
};
?>

<div class="p-4 lg:p-6 space-y-6">
    <x-header title="{{ __('navigation.student_portal') }}" subtitle="{{ now()->isoFormat('dddd D MMMM Y') }}" separator>
        <x-slot:actions>
            <x-badge value="{{ __('navigation.student') }}" class="badge-secondary badge-lg" />
        </x-slot:actions>
    </x-header>

    {{-- Welcome banner --}}
    <div class="relative overflow-hidden rounded-2xl p-6 text-white" style="background: linear-gradient(135deg, #7c3aed 0%, #8b5cf6 50%, #a78bfa 100%)">
        <div class="absolute top-0 right-0 w-40 h-40 rounded-full bg-white/10 -translate-y-10 translate-x-10"></div>
        <div class="absolute bottom-0 left-0 w-24 h-24 rounded-full bg-white/5 translate-y-8 -translate-x-6"></div>
        <div class="relative">
            <p class="text-violet-200 text-sm font-medium">{{ __('navigation.welcome_back') }}</p>
            <h2 class="text-2xl font-black mt-1">{{ $student?->full_name ?? auth()->user()->full_name }}</h2>
            @if($enrollment)
                <p class="text-violet-200 mt-1">
                    {{ $enrollment->schoolClass?->name }} · {{ $enrollment->grade?->name }}
                </p>
            @endif
        </div>
    </div>

    {{-- KPI cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <x-card class="border-0 shadow-sm bg-gradient-to-br from-violet-50 to-white text-center">
            <div class="w-10 h-10 rounded-xl bg-violet-100 flex items-center justify-center mx-auto mb-2">
                <x-icon name="o-chart-bar" class="w-5 h-5 text-violet-600" />
            </div>
            <p class="text-2xl font-black text-violet-700">{{ $totalScores }}</p>
            <p class="text-xs text-base-content/60">Notes</p>
        </x-card>

        <x-card class="border-0 shadow-sm bg-gradient-to-br from-green-50 to-white text-center">
            <div class="w-10 h-10 rounded-xl bg-green-100 flex items-center justify-center mx-auto mb-2">
                <x-icon name="o-star" class="w-5 h-5 text-green-600" />
            </div>
            <p class="text-2xl font-black text-green-700">{{ $avgScore ? round($avgScore) . '%' : '—' }}</p>
            <p class="text-xs text-base-content/60">Moyenne générale</p>
        </x-card>

        <x-card class="border-0 shadow-sm bg-gradient-to-br from-red-50 to-white text-center">
            <div class="w-10 h-10 rounded-xl bg-red-100 flex items-center justify-center mx-auto mb-2">
                <x-icon name="o-x-circle" class="w-5 h-5 text-red-600" />
            </div>
            <p class="text-2xl font-black text-red-700">{{ $absences }}</p>
            <p class="text-xs text-base-content/60">Absences</p>
        </x-card>

        <x-card class="border-0 shadow-sm bg-gradient-to-br from-blue-50 to-white text-center">
            <div class="w-10 h-10 rounded-xl bg-blue-100 flex items-center justify-center mx-auto mb-2">
                <x-icon name="o-megaphone" class="w-5 h-5 text-blue-600" />
            </div>
            <p class="text-2xl font-black text-blue-700">{{ $announcements->count() }}</p>
            <p class="text-xs text-base-content/60">Annonces</p>
        </x-card>
    </div>

    {{-- Quick actions --}}
    <x-card title="Accès rapide" shadow separator>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <a href="{{ route('student.timetable') }}" wire:navigate
               class="flex flex-col items-center gap-2 p-4 rounded-xl bg-violet-50 hover:bg-violet-100 transition-colors group">
                <div class="w-10 h-10 rounded-full bg-violet-600 flex items-center justify-center group-hover:scale-110 transition-transform">
                    <x-icon name="o-table-cells" class="w-5 h-5 text-white" />
                </div>
                <span class="text-xs font-semibold text-violet-700">Emploi du temps</span>
            </a>
            <a href="{{ route('student.grades') }}" wire:navigate
               class="flex flex-col items-center gap-2 p-4 rounded-xl bg-green-50 hover:bg-green-100 transition-colors group">
                <div class="w-10 h-10 rounded-full bg-green-600 flex items-center justify-center group-hover:scale-110 transition-transform">
                    <x-icon name="o-chart-bar" class="w-5 h-5 text-white" />
                </div>
                <span class="text-xs font-semibold text-green-700">Mes notes</span>
            </a>
            <a href="{{ route('student.attendance') }}" wire:navigate
               class="flex flex-col items-center gap-2 p-4 rounded-xl bg-orange-50 hover:bg-orange-100 transition-colors group">
                <div class="w-10 h-10 rounded-full bg-orange-600 flex items-center justify-center group-hover:scale-110 transition-transform">
                    <x-icon name="o-calendar-days" class="w-5 h-5 text-white" />
                </div>
                <span class="text-xs font-semibold text-orange-700">Présences</span>
            </a>
            <a href="{{ route('student.announcements') }}" wire:navigate
               class="flex flex-col items-center gap-2 p-4 rounded-xl bg-blue-50 hover:bg-blue-100 transition-colors group">
                <div class="w-10 h-10 rounded-full bg-blue-600 flex items-center justify-center group-hover:scale-110 transition-transform">
                    <x-icon name="o-megaphone" class="w-5 h-5 text-white" />
                </div>
                <span class="text-xs font-semibold text-blue-700">Annonces</span>
            </a>
        </div>
    </x-card>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Recent grades --}}
        <x-card title="Dernières notes" shadow separator>
            @forelse($recentScores as $score)
            <div class="flex items-center gap-3 py-2 border-b border-base-100 last:border-0">
                <div class="w-8 h-8 rounded-lg bg-violet-100 flex items-center justify-center flex-shrink-0">
                    <x-icon name="o-pencil-square" class="w-4 h-4 text-violet-600" />
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium truncate">{{ $score->assessment?->title }}</p>
                    <p class="text-xs text-base-content/50">{{ $score->assessment?->subject?->name }}</p>
                </div>
                <div class="text-right flex-shrink-0">
                    <p class="font-bold {{ $score->score >= ($score->assessment?->max_score / 2) ? 'text-success' : 'text-error' }}">
                        {{ $score->score }}/{{ $score->assessment?->max_score }}
                    </p>
                </div>
            </div>
            @empty
            <div class="text-center py-8 text-base-content/40">
                <x-icon name="o-chart-bar" class="w-10 h-10 mx-auto mb-2" />
                <p class="text-sm">Aucune note disponible</p>
            </div>
            @endforelse
        </x-card>

        {{-- Announcements --}}
        <x-card title="Annonces récentes" shadow separator>
            @forelse($announcements as $ann)
            <div class="py-2 border-b border-base-100 last:border-0">
                <p class="font-medium text-sm">{{ $ann->title }}</p>
                <p class="text-xs text-base-content/50 mt-0.5">{{ $ann->published_at?->diffForHumans() }}</p>
            </div>
            @empty
            <div class="text-center py-8 text-base-content/40">
                <x-icon name="o-megaphone" class="w-10 h-10 mx-auto mb-2" />
                <p class="text-sm">Aucune annonce</p>
            </div>
            @endforelse
        </x-card>
    </div>
</div>
