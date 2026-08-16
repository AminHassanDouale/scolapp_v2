<?php
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Guardian;
use App\Models\ReportCard;

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

        $selectedStudent = $this->studentUuid
            ? $students->firstWhere('uuid', $this->studentUuid)
            : $students->first();

        $bulletins = $selectedStudent
            ? ReportCard::where('student_id', $selectedStudent->id)
                ->where('is_published', true)
                ->with(['enrollment.schoolClass', 'academicYear'])
                ->orderByDesc('published_at')
                ->get()
            : collect();

        return compact('students', 'selectedStudent', 'bulletins');
    }
};
?>

<div class="p-4 lg:p-6 space-y-6">
    <x-header title="{{ __('navigation.report_cards') }}" subtitle="Bulletins publiés — consulter &amp; télécharger" separator>
        <x-slot:actions>
            <x-button icon="o-arrow-left" link="{{ route('guardian.dashboard') }}" wire:navigate class="btn-ghost btn-sm" />
        </x-slot:actions>
    </x-header>

    {{-- Child selector --}}
    @if($students->count() > 1)
    <div class="flex gap-2 flex-wrap">
        @foreach($students as $student)
        <a href="{{ route('guardian.bulletins', ['student' => $student->uuid]) }}" wire:navigate>
            <x-badge :value="$student->full_name"
                class="{{ $selectedStudent?->id === $student->id ? 'badge-success' : 'badge-ghost' }} badge-lg cursor-pointer" />
        </a>
        @endforeach
    </div>
    @endif

    @if(!$selectedStudent)
        <x-alert icon="o-user" class="alert-info">Aucun enfant trouvé.</x-alert>
    @elseif($bulletins->isEmpty())
        <x-alert icon="o-information-circle" class="alert-info">
            Aucun bulletin publié pour {{ $selectedStudent->full_name }} pour le moment.
        </x-alert>
    @else
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($bulletins as $b)
        @php
            $avg = $b->average !== null ? (float) $b->average : null;
            $ok  = $avg !== null && $avg >= 10;
        @endphp
        <x-card class="border-0 shadow-sm hover:shadow-md transition-shadow">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="font-bold text-base">{{ $b->period?->label() ?? 'Bulletin' }}</p>
                    <p class="text-xs text-base-content/60">{{ $b->academicYear?->name }} · {{ $b->enrollment?->schoolClass?->name }}</p>
                    <p class="text-[11px] text-base-content/40 mt-0.5">Publié le {{ $b->published_at?->format('d/m/Y') }}</p>
                </div>
                <div class="w-14 h-14 rounded-full border-4 flex flex-col items-center justify-center shrink-0
                            {{ $avg === null ? 'border-base-300' : ($ok ? 'border-emerald-500' : 'border-red-400') }}">
                    <span class="text-sm font-black leading-none">{{ $avg !== null ? number_format($avg, 1) : '—' }}</span>
                    <span class="text-[9px] text-base-content/40">/20</span>
                </div>
            </div>

            <div class="flex items-center gap-2 mt-3 text-xs">
                @if($b->rank)<x-badge value="Rang {{ $b->rank }}/{{ $b->class_size }}" class="badge-ghost badge-sm" />@endif
                @if($avg !== null)
                <x-badge :value="$ok ? 'Admis' : 'À améliorer'" class="{{ $ok ? 'badge-success' : 'badge-warning' }} badge-sm" />
                @endif
            </div>

            <a href="{{ route('guardian.bulletins.show', $b->uuid) }}" wire:navigate
               class="btn btn-sm btn-success btn-block mt-4 text-white">
                <x-icon name="o-eye" class="w-4 h-4" /> Voir &amp; télécharger
            </a>
        </x-card>
        @endforeach
    </div>
    @endif
</div>
