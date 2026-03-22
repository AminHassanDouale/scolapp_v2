<?php
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Mary\Traits\Toast;
use App\Models\Teacher;
use App\Models\Assessment;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\StudentScore;

new #[Layout('layouts.teacher')] class extends Component {
    use Toast;

    public ?int $filterClassId   = null;
    public ?int $filterSubjectId = null;
    public bool $showModal       = false;
    public ?int $selectedId      = null;

    public function with(): array
    {
        $user    = auth()->user();
        $teacher = Teacher::where('user_id', $user->id)->with(['schoolClasses', 'subjects'])->first();

        $assessments = Assessment::where('teacher_id', $teacher?->id)
            ->when($this->filterClassId,   fn($q) => $q->where('school_class_id', $this->filterClassId))
            ->when($this->filterSubjectId, fn($q) => $q->where('subject_id',      $this->filterSubjectId))
            ->with(['schoolClass', 'subject', 'scores'])
            ->orderByDesc('assessment_date')
            ->paginate(15);

        return [
            'teacher'     => $teacher,
            'assessments' => $assessments,
            'classes'     => $teacher?->schoolClasses ?? collect(),
            'subjects'    => $teacher?->subjects      ?? collect(),
        ];
    }
};
?>

<div class="p-4 lg:p-6 space-y-6">
    <x-header title="{{ __('navigation.assessments') }}" subtitle="{{ __('navigation.my_assessments') }}" separator>
        <x-slot:actions>
            <x-button icon="o-arrow-left" link="{{ route('teacher.dashboard') }}" wire:navigate class="btn-ghost btn-sm" />
        </x-slot:actions>
    </x-header>

    {{-- Filters --}}
    <x-card shadow class="border-0">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <x-select label="{{ __('navigation.class') }}" wire:model.live="filterClassId" placeholder="Toutes les classes"
                :options="$classes->map(fn($c) => ['id' => $c->id, 'name' => $c->name])->all()" />
            <x-select label="{{ __('navigation.subject') }}" wire:model.live="filterSubjectId" placeholder="Toutes les matières"
                :options="$subjects->map(fn($s) => ['id' => $s->id, 'name' => $s->name])->all()" />
        </div>
    </x-card>

    {{-- Assessments list --}}
    <x-card shadow class="border-0 p-0 overflow-hidden">
        <table class="table table-sm w-full">
            <thead class="bg-base-200">
                <tr>
                    <th>Titre</th>
                    <th>Classe</th>
                    <th>Matière</th>
                    <th>Date</th>
                    <th class="text-center">Note max</th>
                    <th class="text-center">Élèves notés</th>
                    <th class="text-center">Statut</th>
                </tr>
            </thead>
            <tbody>
                @forelse($assessments as $assessment)
                <tr class="hover border-b border-base-100">
                    <td class="font-medium">{{ $assessment->title }}</td>
                    <td>{{ $assessment->schoolClass?->name ?? '—' }}</td>
                    <td>{{ $assessment->subject?->name ?? '—' }}</td>
                    <td class="text-sm text-base-content/60">{{ $assessment->assessment_date?->format('d/m/Y') }}</td>
                    <td class="text-center">{{ $assessment->max_score }}</td>
                    <td class="text-center">
                        <x-badge :value="$assessment->scores->count()" class="badge-ghost badge-sm" />
                    </td>
                    <td class="text-center">
                        @if($assessment->is_published ?? false)
                            <x-badge value="Publié" class="badge-success badge-sm" />
                        @else
                            <x-badge value="Brouillon" class="badge-ghost badge-sm" />
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center py-12 text-base-content/40">
                        <x-icon name="o-inbox" class="w-10 h-10 mx-auto mb-2" />
                        <p class="text-sm">Aucune évaluation trouvée</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        <div class="p-4">{{ $assessments->links() }}</div>
    </x-card>
</div>
