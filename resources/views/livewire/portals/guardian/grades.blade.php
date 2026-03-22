<?php
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Guardian;
use App\Models\StudentScore;
use App\Models\Assessment;

new #[Layout('layouts.guardian')] class extends Component {
    public ?string $studentUuid = null;

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

        $scores = $selectedStudent
            ? StudentScore::where('student_id', $selectedStudent->id)
                ->with(['assessment.subject', 'assessment.schoolClass'])
                ->orderByDesc('created_at')
                ->get()
                ->groupBy('assessment.subject.name')
            : collect();

        return compact('students', 'selectedStudent', 'scores');
    }
};
?>

<div class="p-4 lg:p-6 space-y-6">
    <x-header title="{{ __('navigation.grades') }}" subtitle="Notes et évaluations" separator>
        <x-slot:actions>
            <x-button icon="o-arrow-left" link="{{ route('guardian.dashboard') }}" wire:navigate class="btn-ghost btn-sm" />
        </x-slot:actions>
    </x-header>

    @if($students->count() > 1)
    <x-card shadow class="border-0">
        <div class="flex gap-2 flex-wrap">
            @foreach($students as $student)
            <a href="{{ route('guardian.grades', ['student' => $student->uuid]) }}" wire:navigate>
                <x-badge :value="$student->full_name"
                    class="{{ $selectedStudent?->id === $student->id ? 'badge-success' : 'badge-ghost' }} badge-md cursor-pointer" />
            </a>
            @endforeach
        </div>
    </x-card>
    @endif

    @forelse($scores as $subject => $subjectScores)
    <x-card :title="$subject" shadow separator class="border-0">
        <table class="table table-sm w-full">
            <thead class="bg-base-200">
                <tr>
                    <th>Évaluation</th>
                    <th>Date</th>
                    <th class="text-center">Note</th>
                    <th class="text-center">/ Max</th>
                    <th class="text-center">%</th>
                </tr>
            </thead>
            <tbody>
                @foreach($subjectScores as $score)
                @php $pct = $score->assessment?->max_score > 0 ? round(($score->score / $score->assessment->max_score) * 100) : 0; @endphp
                <tr class="hover border-b border-base-100">
                    <td>{{ $score->assessment?->title }}</td>
                    <td class="text-sm text-base-content/60">{{ $score->assessment?->assessment_date?->format('d/m/Y') }}</td>
                    <td class="text-center font-bold {{ $pct >= 50 ? 'text-success' : 'text-error' }}">{{ $score->score }}</td>
                    <td class="text-center text-base-content/50">{{ $score->assessment?->max_score }}</td>
                    <td class="text-center">
                        <x-badge :value="$pct.'%'" class="{{ $pct >= 75 ? 'badge-success' : ($pct >= 50 ? 'badge-warning' : 'badge-error') }} badge-sm" />
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </x-card>
    @empty
    <x-card shadow class="border-0 text-center py-16">
        <x-icon name="o-chart-bar" class="w-12 h-12 mx-auto mb-3 text-base-content/20" />
        <p class="font-medium text-base-content/40">Aucune note disponible</p>
    </x-card>
    @endforelse
</div>
