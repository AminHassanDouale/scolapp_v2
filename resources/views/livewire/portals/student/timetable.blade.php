<?php
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Student;
use App\Models\TimetableEntry;
use Illuminate\Support\Carbon;

new #[Layout('layouts.student')] class extends Component {
    public string $viewMode = 'table';

    public function with(): array
    {
        $student    = Student::where('user_id', auth()->id())->first();
        $enrollment = $student?->enrollments()
            ->where('status', 'active')
            ->with('schoolClass')
            ->first();

        $days = [
            0 => 'Dimanche',
            1 => 'Lundi',
            2 => 'Mardi',
            3 => 'Mercredi',
            4 => 'Jeudi',
        ];

        $allEntries = collect();
        $slots      = [];

        if ($enrollment?->schoolClass) {
            $allEntries = TimetableEntry::whereHas('template', fn($q) =>
                $q->where('school_class_id', $enrollment->school_class_id)
                  ->where('is_active', true)
            )
            ->with(['subject', 'teacher', 'roomModel'])
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

            $timeSlots = $allEntries
                ->map(fn($e) => substr($e->start_time, 0, 5))
                ->unique()->sort()->values()->toArray();
        }

        $entries = $allEntries->groupBy('day_of_week');

        // Generate calendar events for current month from recurring weekly schedule
        $calColors  = ['!bg-sky-200', '!bg-emerald-200', '!bg-violet-200', '!bg-rose-200',
                       '!bg-amber-200', '!bg-teal-200', '!bg-fuchsia-200', '!bg-orange-200'];
        $calEvents  = [];
        $monthStart = now()->startOfMonth();
        $monthEnd   = now()->endOfMonth();

        foreach ($entries as $dow => $dowEntries) {
            $d = $monthStart->copy();
            while ($d->lte($monthEnd)) {
                if ($d->dayOfWeek === (int) $dow) {
                    foreach ($dowEntries as $entry) {
                        $calEvents[] = [
                            'label'       => $entry->subject?->name ?? 'Cours',
                            'description' => substr($entry->start_time, 0, 5) . '–' . substr($entry->end_time, 0, 5),
                            'css'         => $calColors[($entry->subject_id ?? 0) % 8],
                            'date'        => $d->copy(),
                        ];
                    }
                }
                $d->addDay();
            }
        }

        return compact('entries', 'days', 'enrollment', 'timeSlots', 'calEvents', 'allEntries');
    }
};
?>

<div class="p-4 lg:p-6 space-y-6">
    <x-header title="Emploi du temps"
              subtitle="{{ $enrollment?->schoolClass?->name ?? 'Mon emploi du temps' }}"
              separator>
        <x-slot:actions>
            <div class="join">
                <button wire:click="$set('viewMode','table')"
                        class="join-item btn btn-sm {{ $viewMode === 'table' ? 'btn-primary' : 'btn-ghost' }}"
                        title="Vue tableau">
                    <x-icon name="o-table-cells" class="w-4 h-4" />
                </button>
                <button wire:click="$set('viewMode','calendar')"
                        class="join-item btn btn-sm {{ $viewMode === 'calendar' ? 'btn-primary' : 'btn-ghost' }}"
                        title="Vue calendrier">
                    <x-icon name="o-calendar-days" class="w-4 h-4" />
                </button>
            </div>
            <x-button icon="o-arrow-left" link="{{ route('student.dashboard') }}"
                      wire:navigate class="btn-ghost btn-sm" />
        </x-slot:actions>
    </x-header>

    @if($entries->isEmpty())
        <x-alert icon="o-information-circle" class="alert-info">
            Aucun emploi du temps disponible pour votre classe.
        </x-alert>
    @else

    @if($viewMode === 'table')
    {{-- ── TABLE VIEW ──────────────────────────────────────────────────────────── --}}
    <div class="overflow-x-auto rounded-2xl border border-base-200 shadow-sm">
        <table class="table table-sm w-full bg-base-100">
            <thead style="background: linear-gradient(135deg,#7c3aed,#8b5cf6); color:white;">
                <tr>
                    <th class="py-3 px-4 text-left font-semibold text-sm w-28">Heure</th>
                    @foreach($days as $num => $name)
                        @if($entries->has($num))
                            <th class="py-3 px-3 text-center font-semibold text-sm min-w-36">{{ $name }}</th>
                        @endif
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($timeSlots as $slot)
                @php
                    try {
                        $slotEnd = \Illuminate\Support\Carbon::createFromFormat('H:i', $slot)->addMinutes(90)->format('H:i');
                    } catch (\Throwable) { $slotEnd = ''; }
                    $isLunch = $slot >= '12:00' && $slot < '13:30';
                @endphp

                @if($isLunch)
                <tr class="bg-amber-50/60">
                    <td class="text-center py-2 text-xs text-amber-600 font-medium">🍽 Pause</td>
                    <td colspan="{{ $entries->count() }}" class="py-2 text-center text-xs text-amber-500/60 italic">Pause déjeuner</td>
                </tr>
                @else
                <tr class="border-b border-base-200 hover">
                    <td class="py-3 px-4 w-28">
                        <div class="text-xs font-bold font-mono text-base-content/70">{{ $slot }}</div>
                        @if($slotEnd)<div class="text-[10px] text-base-content/40">→ {{ $slotEnd }}</div>@endif
                    </td>
                    @foreach($days as $num => $name)
                    @if($entries->has($num))
                    @php
                        $entry = ($entries[$num] ?? collect())->first(fn($e) => substr($e->start_time, 0, 5) === $slot);
                    @endphp
                    <td class="py-2 px-2">
                        @if($entry)
                        @php $c = $entry->subject?->color; @endphp
                        <div class="rounded-xl p-2 text-left shadow-sm"
                             style="{{ $c
                                ? 'background:color-mix(in srgb,'.$c.' 15%,white);border-left:3px solid '.$c
                                : 'background:#f5f3ff;border-left:3px solid #7c3aed' }}">
                            <p class="font-bold text-xs truncate"
                               style="{{ $c ? 'color:'.$c : 'color:#7c3aed' }}">
                                {{ $entry->subject?->name }}
                            </p>
                            @if($entry->teacher)
                            <p class="text-[10px] text-base-content/55 truncate mt-0.5">
                                {{ $entry->teacher->full_name }}
                            </p>
                            @endif
                            @if($entry->roomModel)
                            <p class="text-[9px] text-base-content/40 flex items-center gap-0.5 mt-0.5">
                                <x-icon name="o-map-pin" class="w-2.5 h-2.5" />{{ $entry->roomModel->name }}
                            </p>
                            @endif
                        </div>
                        @else
                        <span class="text-base-content/15 text-sm">—</span>
                        @endif
                    </td>
                    @endif
                    @endforeach
                </tr>
                @endif
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Subject legend --}}
    @php $uniqueSubjects = $allEntries->pluck('subject')->filter()->unique('id'); @endphp
    @if($uniqueSubjects->isNotEmpty())
    <div class="flex flex-wrap gap-2">
        @foreach($uniqueSubjects as $subj)
        <span class="badge badge-sm gap-1.5 border"
              style="{{ $subj->color
                ? 'background:color-mix(in srgb,'.$subj->color.' 15%,white);border-color:'.$subj->color.';color:'.$subj->color
                : '' }}">
            @if($subj->color)
            <span class="w-2 h-2 rounded-full shrink-0"
                  style="background:{{ $subj->color }}"></span>
            @endif
            {{ $subj->name }}
        </span>
        @endforeach
    </div>
    @endif

    @else
    {{-- ── CALENDAR VIEW ────────────────────────────────────────────────────────── --}}
    <x-card shadow class="border-0">
        <div class="flex items-center justify-between mb-3">
            <p class="text-sm font-semibold text-base-content/70">
                {{ now()->translatedFormat('F Y') }}
            </p>
            <p class="text-xs text-base-content/40">
                Planning mensuel — {{ $allEntries->count() }} créneaux / semaine
            </p>
        </div>
        <x-calendar :events="$calEvents" locale="fr-FR" />
    </x-card>
    @endif

    @endif
</div>
