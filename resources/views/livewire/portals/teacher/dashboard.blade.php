<?php
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Mary\Traits\Toast;
use App\Models\Teacher;
use App\Models\SchoolClass;
use App\Models\AttendanceSession;
use App\Models\Assessment;
use Carbon\Carbon;

new #[Layout('layouts.teacher')] class extends Component {
    use Toast;

    public function with(): array
    {
        $user    = auth()->user();
        $teacher = Teacher::where('user_id', $user->id)->with(['schoolClasses', 'subjects'])->first();

        $classCount    = $teacher?->schoolClasses?->count() ?? 0;
        $subjectCount  = $teacher?->subjects?->count() ?? 0;

        // Today's attendance sessions by this teacher
        $todayAttendance = $teacher
            ? AttendanceSession::where('teacher_id', $teacher->id)->whereDate('session_date', today())
                ->count()
            : 0;

        // Recent assessments
        $recentAssessments = $teacher
            ? Assessment::where('teacher_id', $teacher->id)
                ->with(['schoolClass', 'subject'])
                ->orderByDesc('assessment_date')
                ->limit(5)
                ->get()
            : collect();

        // Classes this teacher teaches
        $myClasses = $teacher?->schoolClasses ?? collect();

        return compact('teacher', 'classCount', 'subjectCount', 'todayAttendance', 'recentAssessments', 'myClasses');
    }
};
?>

<div class="p-4 lg:p-6 space-y-6">
    <x-header title="{{ __('navigation.teacher_portal') }}" subtitle="{{ now()->isoFormat('dddd D MMMM Y') }}" separator>
        <x-slot:actions>
            <x-badge value="{{ __('navigation.teacher') }}" class="badge-primary badge-lg" />
        </x-slot:actions>
    </x-header>

    {{-- Welcome banner --}}
    <div class="relative overflow-hidden rounded-2xl p-6 text-white" style="background: linear-gradient(135deg, #4f46e5 0%, #6366f1 50%, #818cf8 100%)">
        <div class="absolute top-0 right-0 w-40 h-40 rounded-full bg-white/10 -translate-y-10 translate-x-10"></div>
        <div class="absolute bottom-0 left-0 w-24 h-24 rounded-full bg-white/5 translate-y-8 -translate-x-6"></div>
        <div class="relative">
            <p class="text-indigo-200 text-sm font-medium">{{ __('navigation.welcome_back') }}</p>
            <h2 class="text-2xl font-black mt-1">{{ $teacher?->full_name ?? auth()->user()->full_name }}</h2>
            @if($teacher?->specialization)
                <p class="text-indigo-200 mt-1">{{ $teacher->specialization }}</p>
            @endif
        </div>
    </div>

    {{-- KPI cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <x-card class="border-0 shadow-sm bg-gradient-to-br from-indigo-50 to-white">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-indigo-100 flex items-center justify-center">
                    <x-icon name="o-building-office" class="w-5 h-5 text-indigo-600" />
                </div>
                <div>
                    <p class="text-2xl font-black text-indigo-700">{{ $classCount }}</p>
                    <p class="text-xs text-base-content/60">{{ __('navigation.classes') }}</p>
                </div>
            </div>
        </x-card>

        <x-card class="border-0 shadow-sm bg-gradient-to-br from-purple-50 to-white">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-purple-100 flex items-center justify-center">
                    <x-icon name="o-book-open" class="w-5 h-5 text-purple-600" />
                </div>
                <div>
                    <p class="text-2xl font-black text-purple-700">{{ $subjectCount }}</p>
                    <p class="text-xs text-base-content/60">{{ __('navigation.subjects') }}</p>
                </div>
            </div>
        </x-card>

        <x-card class="border-0 shadow-sm bg-gradient-to-br from-blue-50 to-white">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-blue-100 flex items-center justify-center">
                    <x-icon name="o-calendar-days" class="w-5 h-5 text-blue-600" />
                </div>
                <div>
                    <p class="text-2xl font-black text-blue-700">{{ $todayAttendance }}</p>
                    <p class="text-xs text-base-content/60">{{ __('navigation.today_attendance') }}</p>
                </div>
            </div>
        </x-card>

        <x-card class="border-0 shadow-sm bg-gradient-to-br from-green-50 to-white">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-green-100 flex items-center justify-center">
                    <x-icon name="o-pencil-square" class="w-5 h-5 text-green-600" />
                </div>
                <div>
                    <p class="text-2xl font-black text-green-700">{{ $recentAssessments->count() }}</p>
                    <p class="text-xs text-base-content/60">{{ __('navigation.recent_assessments') }}</p>
                </div>
            </div>
        </x-card>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Quick actions --}}
        <x-card title="{{ __('navigation.quick_actions') }}" shadow separator>
            <div class="grid grid-cols-2 gap-3">
                <a href="{{ route('teacher.attendance') }}" wire:navigate
                   class="flex flex-col items-center gap-2 p-4 rounded-xl bg-indigo-50 hover:bg-indigo-100 transition-colors text-center group">
                    <div class="w-10 h-10 rounded-full bg-indigo-600 flex items-center justify-center group-hover:scale-110 transition-transform">
                        <x-icon name="o-calendar-days" class="w-5 h-5 text-white" />
                    </div>
                    <span class="text-xs font-semibold text-indigo-700">{{ __('navigation.mark_attendance') }}</span>
                </a>
                <a href="{{ route('teacher.assessments') }}" wire:navigate
                   class="flex flex-col items-center gap-2 p-4 rounded-xl bg-purple-50 hover:bg-purple-100 transition-colors text-center group">
                    <div class="w-10 h-10 rounded-full bg-purple-600 flex items-center justify-center group-hover:scale-110 transition-transform">
                        <x-icon name="o-pencil-square" class="w-5 h-5 text-white" />
                    </div>
                    <span class="text-xs font-semibold text-purple-700">{{ __('navigation.assessments') }}</span>
                </a>
                <a href="{{ route('teacher.timetable') }}" wire:navigate
                   class="flex flex-col items-center gap-2 p-4 rounded-xl bg-blue-50 hover:bg-blue-100 transition-colors text-center group">
                    <div class="w-10 h-10 rounded-full bg-blue-600 flex items-center justify-center group-hover:scale-110 transition-transform">
                        <x-icon name="o-table-cells" class="w-5 h-5 text-white" />
                    </div>
                    <span class="text-xs font-semibold text-blue-700">{{ __('navigation.timetable') }}</span>
                </a>
                <a href="{{ route('teacher.messages') }}" wire:navigate
                   class="flex flex-col items-center gap-2 p-4 rounded-xl bg-green-50 hover:bg-green-100 transition-colors text-center group">
                    <div class="w-10 h-10 rounded-full bg-green-600 flex items-center justify-center group-hover:scale-110 transition-transform">
                        <x-icon name="o-envelope" class="w-5 h-5 text-white" />
                    </div>
                    <span class="text-xs font-semibold text-green-700">{{ __('navigation.messages') }}</span>
                </a>
            </div>
        </x-card>

        {{-- My Classes --}}
        <x-card title="{{ __('navigation.my_classes') }}" shadow separator>
            @forelse($myClasses as $class)
                <div class="flex items-center justify-between py-2 border-b border-base-100 last:border-0">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-indigo-100 flex items-center justify-center">
                            <x-icon name="o-building-office" class="w-4 h-4 text-indigo-600" />
                        </div>
                        <div>
                            <p class="font-semibold text-sm">{{ $class->name }}</p>
                            <p class="text-xs text-base-content/50">{{ $class->grade?->name }}</p>
                        </div>
                    </div>
                    <x-badge :value="($class->students_count ?? $class->students()->count()) . ' élèves'" class="badge-ghost badge-sm" />
                </div>
            @empty
                <div class="text-center py-8 text-base-content/40">
                    <x-icon name="o-inbox" class="w-10 h-10 mx-auto mb-2" />
                    <p class="text-sm">{{ __('navigation.no_classes_assigned') }}</p>
                </div>
            @endforelse
        </x-card>
    </div>

    {{-- Recent assessments --}}
    @if($recentAssessments->isNotEmpty())
    <x-card title="{{ __('navigation.recent_assessments') }}" shadow separator>
        <x-table :rows="$recentAssessments" :columns="[
            ['key' => 'subject.name',          'label' => __('navigation.subject')],
            ['key' => 'school_class.name',     'label' => __('navigation.class')],
            ['key' => 'assessment_date',       'label' => __('navigation.date'), 'format' => 'date'],
            ['key' => 'max_score',             'label' => __('navigation.max_score')],
        ]" />
    </x-card>
    @endif
</div>
