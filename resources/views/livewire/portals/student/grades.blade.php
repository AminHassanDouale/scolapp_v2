<?php
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Student;
use App\Models\StudentScore;

new #[Layout('layouts.student')] class extends Component {
    public function with(): array
    {
        $student = Student::where('user_id', auth()->id())->first();

        $scores = $student
            ? StudentScore::where('student_id', $student->id)
                ->with(['assessment.subject'])
                ->orderByDesc('created_at')
                ->get()
                ->groupBy('assessment.subject.name')
            : collect();

        $overall = $student
            ? StudentScore::where('student_id', $student->id)
                ->join('assessments', 'student_scores.assessment_id', '=', 'assessments.id')
                ->selectRaw('AVG(student_scores.score / assessments.max_score * 100) as avg_pct, COUNT(*) as total')
                ->where('assessments.max_score', '>', 0)
                ->first()
            : null;

        return compact('student', 'scores', 'overall');
    }
};
?>

<div class="p-4 lg:p-6 space-y-6">
    <x-header title="{{ __('navigation.grades') }}" subtitle="Mes résultats" separator>
        <x-slot:actions>
            <x-button icon="o-arrow-left" link="{{ route('student.dashboard') }}" wire:navigate class="btn-ghost btn-sm" />
        </x-slot:actions>
    </x-header>

    @if($overall)
    <div class="grid grid-cols-2 gap-4">
        <x-card class="border-0 shadow-sm bg-gradient-to-br from-violet-50 to-white text-center">
            <p class="text-4xl font-black text-violet-700">{{ round($overall->avg_pct ?? 0) }}%</p>
            <p class="text-xs text-base-content/60 mt-1">Moyenne générale</p>
            <div class="w-full bg-base-200 rounded-full h-2 mt-3">
                <div class="h-2 rounded-full {{ ($overall->avg_pct ?? 0) >= 50 ? 'bg-success' : 'bg-error' }}" style="width: {{ min(100, $overall->avg_pct ?? 0) }}%"></div>
            </div>
        </x-card>
        <x-card class="border-0 shadow-sm bg-gradient-to-br from-blue-50 to-white text-center">
            <p class="text-4xl font-black text-blue-700">{{ $overall->total ?? 0 }}</p>
            <p class="text-xs text-base-content/60 mt-1">Évaluations passées</p>
        </x-card>
    </div>
    @endif

    @forelse($scores as $subject => $subjectScores)
    @php
        $subjectAvg = $subjectScores->avg(fn($s) => $s->assessment?->max_score > 0 ? ($s->score / $s->assessment->max_score * 100) : null);
    @endphp
    <x-card shadow class="border-0">
        <div class="flex items-center justify-between mb-3">
            <h3 class="font-bold text-base">{{ $subject }}</h3>
            @if($subjectAvg !== null)
            <x-badge :value="round($subjectAvg).'%'" class="{{ $subjectAvg >= 75 ? 'badge-success' : ($subjectAvg >= 50 ? 'badge-warning' : 'badge-error') }} badge-sm" />
            @endif
        </div>
        <div class="space-y-2">
            @foreach($subjectScores as $score)
            @php $pct = $score->assessment?->max_score > 0 ? round($score->score / $score->assessment->max_score * 100) : 0; @endphp
            <div class="flex items-center gap-3 p-2 bg-base-50 rounded-lg">
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium truncate">{{ $score->assessment?->title }}</p>
                    <p class="text-xs text-base-content/50">{{ $score->assessment?->assessment_date?->format('d/m/Y') }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="font-bold text-sm {{ $pct >= 50 ? 'text-success' : 'text-error' }}">
                        {{ $score->score }}/{{ $score->assessment?->max_score }}
                    </span>
                    <div class="w-16 bg-base-200 rounded-full h-1.5">
                        <div class="h-1.5 rounded-full {{ $pct >= 75 ? 'bg-success' : ($pct >= 50 ? 'bg-warning' : 'bg-error') }}" style="width: {{ $pct }}%"></div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </x-card>
    @empty
    <x-card shadow class="border-0 text-center py-16">
        <x-icon name="o-chart-bar" class="w-12 h-12 mx-auto mb-3 text-base-content/20" />
        <p class="font-medium text-base-content/40">Aucune note disponible</p>
    </x-card>
    @endforelse
</div>
