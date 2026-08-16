<?php
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Guardian;
use App\Models\ReportCard;

new #[Layout('layouts.guardian')] class extends Component {
    public ReportCard $reportCard;

    public function mount(string $uuid): void
    {
        $guardian    = Guardian::where('user_id', auth()->id())->with('students')->firstOrFail();
        $studentIds  = $guardian->students->pluck('id');

        $this->reportCard = ReportCard::where('uuid', $uuid)
            ->where('is_published', true)
            ->whereIn('student_id', $studentIds)
            ->with([
                'student', 'academicYear',
                'enrollment.schoolClass.grade',
                'subjectGrades.subject',
            ])
            ->firstOrFail();
    }

    public function with(): array
    {
        $avg = $this->reportCard->average !== null ? (float) $this->reportCard->average : null;

        $mention = match (true) {
            $avg === null => ['label' => '—',            'color' => 'text-base-content/50'],
            $avg >= 16    => ['label' => 'Très bien',     'color' => 'text-emerald-600'],
            $avg >= 14    => ['label' => 'Bien',          'color' => 'text-green-600'],
            $avg >= 12    => ['label' => 'Assez bien',    'color' => 'text-lime-600'],
            $avg >= 10    => ['label' => 'Passable',      'color' => 'text-amber-600'],
            default       => ['label' => 'Insuffisant',   'color' => 'text-red-600'],
        };

        return [
            'rc'      => $this->reportCard,
            'school'  => $this->reportCard->student?->school ?? auth()->user()->school,
            'avg'     => $avg,
            'mention' => $mention,
        ];
    }
};
?>

<div class="p-4 lg:p-6 space-y-5">
    {{-- Toolbar (hidden when printing) --}}
    <div class="print:hidden flex items-center justify-between gap-3">
        <x-button icon="o-arrow-left" label="Retour" link="{{ route('guardian.bulletins', ['student' => $rc->student?->uuid]) }}" wire:navigate class="btn-ghost btn-sm" />
        <x-button icon="o-arrow-down-tray" label="Télécharger / Imprimer" onclick="window.print()" class="btn-success btn-sm text-white" />
    </div>

    {{-- Printable bulletin --}}
    <div class="bg-white rounded-2xl shadow-lg border border-base-200 overflow-hidden print:shadow-none print:border-0 print:rounded-none max-w-3xl mx-auto">

        {{-- Header --}}
        <div class="px-6 sm:px-8 py-6 text-white" style="background:linear-gradient(135deg,#065f46,#10b981);">
            <div class="flex items-center gap-4">
                @if($school?->logo)
                <img src="{{ $school->logo_url }}" alt="" class="w-14 h-14 rounded-xl object-cover bg-white/20 p-1 shrink-0">
                @endif
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-widest opacity-80">{{ $school?->name ?? 'École' }}</p>
                    <h1 class="text-xl sm:text-2xl font-black mt-0.5">Bulletin scolaire</h1>
                    <p class="text-sm opacity-80">{{ $rc->academicYear?->name }} · {{ $rc->period?->label() }}</p>
                </div>
                @if($rc->rank)
                <div class="text-right shrink-0">
                    <p class="text-2xl font-black leading-none">{{ $rc->rank }}<span class="text-sm opacity-70">/{{ $rc->class_size }}</span></p>
                    <p class="text-[10px] uppercase tracking-wider opacity-70">Rang</p>
                </div>
                @endif
            </div>
        </div>

        {{-- Student + average --}}
        <div class="flex items-center gap-4 px-6 sm:px-8 py-4 bg-base-50 border-b border-base-200">
            <div class="w-12 h-12 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center font-black text-xl shrink-0">
                {{ strtoupper(substr($rc->student?->name ?? '?', 0, 1)) }}
            </div>
            <div class="flex-1 min-w-0">
                <p class="font-black text-lg leading-tight truncate">{{ $rc->student?->full_name }}</p>
                <p class="text-sm text-base-content/60">
                    {{ $rc->enrollment?->schoolClass?->name }}
                    @if($rc->enrollment?->schoolClass?->grade) — {{ $rc->enrollment?->schoolClass?->grade?->name }}@endif
                </p>
            </div>
            <div class="text-center shrink-0">
                <div class="w-16 h-16 rounded-full border-4 flex flex-col items-center justify-center {{ $avg !== null && $avg >= 10 ? 'border-emerald-500' : ($avg !== null ? 'border-red-400' : 'border-base-300') }}">
                    <span class="text-xl font-black leading-none">{{ $avg !== null ? number_format($avg, 2) : '—' }}</span>
                    <span class="text-[9px] text-base-content/40">/20</span>
                </div>
                <p class="text-xs font-semibold mt-1 {{ $mention['color'] }}">{{ $mention['label'] }}</p>
            </div>
        </div>

        {{-- Subjects --}}
        <div class="px-4 sm:px-8 py-5">
            <h2 class="text-xs font-semibold uppercase tracking-widest text-base-content/50 mb-3">Notes par matière</h2>
            @if($rc->subjectGrades->count())
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b-2 border-base-200 text-base-content/70">
                            <th class="text-left py-2 font-semibold">Matière</th>
                            <th class="text-center py-2 font-semibold w-14">Coef.</th>
                            <th class="text-center py-2 font-semibold w-20">Note /20</th>
                            <th class="text-center py-2 font-semibold w-16">Rang</th>
                            <th class="text-left py-2 font-semibold hidden sm:table-cell">Appréciation</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rc->subjectGrades as $sg)
                        @php $savg = $sg->average !== null ? (float) $sg->average : null; @endphp
                        <tr class="border-b border-base-100">
                            <td class="py-2 font-medium">{{ $sg->subject?->name ?? '—' }}</td>
                            <td class="py-2 text-center text-base-content/60">{{ $sg->coefficient ?? 1 }}</td>
                            <td class="py-2 text-center font-bold {{ $savg !== null && $savg >= 10 ? 'text-emerald-600' : ($savg !== null ? 'text-red-500' : '') }}">
                                {{ $savg !== null ? number_format($savg, 2) : '—' }}
                            </td>
                            <td class="py-2 text-center text-base-content/60">{{ $sg->rank ?? '—' }}</td>
                            <td class="py-2 text-base-content/60 text-xs hidden sm:table-cell">{{ $sg->comment }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <p class="text-sm text-base-content/50 italic">Aucune note enregistrée.</p>
            @endif
        </div>

        {{-- Comments --}}
        @if($rc->general_comment || $rc->teacher_comment)
        <div class="px-6 sm:px-8 py-4 bg-base-50 border-t border-base-200">
            <p class="text-xs font-semibold uppercase tracking-widest text-base-content/50 mb-1">Appréciation générale</p>
            <p class="text-sm text-base-content/70">{{ $rc->general_comment ?? $rc->teacher_comment }}</p>
        </div>
        @endif

        {{-- Footer --}}
        <div class="px-6 sm:px-8 py-3 text-center text-[11px] text-base-content/40 border-t border-base-200">
            Bulletin généré par {{ $school?->name ?? config('app.name') }} · publié le {{ $rc->published_at?->format('d/m/Y') }}
        </div>
    </div>
</div>
