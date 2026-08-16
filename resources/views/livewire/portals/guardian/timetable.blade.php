<?php
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Guardian;
use App\Models\TimetableEntry;

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

        $days = [0 => 'Dimanche', 1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi'];

        $enrollment = $selectedStudent?->enrollments()
            ->where('status', 'confirmed')
            ->with('schoolClass')
            ->first();

        $allEntries = collect();
        $timeSlots  = [];

        if ($enrollment?->schoolClass) {
            $allEntries = TimetableEntry::whereHas('template', fn($q) =>
                $q->where('school_class_id', $enrollment->school_class_id)->where('is_active', true))
                ->with(['subject', 'teacher', 'roomModel'])
                ->orderBy('day_of_week')->orderBy('start_time')
                ->get();

            $timeSlots = $allEntries->map(fn($e) => substr($e->start_time, 0, 5))->unique()->sort()->values()->toArray();
        }

        $entries = $allEntries->groupBy('day_of_week');

        return compact('students', 'selectedStudent', 'enrollment', 'days', 'entries', 'timeSlots', 'allEntries');
    }
};
?>

<div class="p-4 lg:p-6 space-y-6">
    <x-header title="{{ __('navigation.timetable') }}"
              subtitle="{{ $enrollment?->schoolClass?->name ?? __('navigation.timetable') }}" separator>
        <x-slot:actions>
            <x-button icon="o-arrow-left" link="{{ route('guardian.dashboard') }}" wire:navigate class="btn-ghost btn-sm" />
        </x-slot:actions>
    </x-header>

    {{-- Child selector --}}
    @if($students->count() > 1)
    <div class="flex gap-2 flex-wrap">
        @foreach($students as $student)
        <a href="{{ route('guardian.timetable', ['student' => $student->uuid]) }}" wire:navigate>
            <x-badge :value="$student->full_name"
                class="{{ $selectedStudent?->id === $student->id ? 'badge-success' : 'badge-ghost' }} badge-lg cursor-pointer" />
        </a>
        @endforeach
    </div>
    @endif

    @if(!$selectedStudent)
        <x-alert icon="o-user" class="alert-info">Aucun enfant trouvé.</x-alert>
    @elseif($entries->isEmpty())
        <x-alert icon="o-information-circle" class="alert-info">
            Aucun emploi du temps disponible pour {{ $selectedStudent->full_name }}.
        </x-alert>
    @else

    <div class="overflow-x-auto rounded-2xl border border-base-200 shadow-sm">
        <table class="table table-sm w-full bg-base-100">
            <thead style="background: linear-gradient(135deg,#059669,#10b981); color:white;">
                <tr>
                    <th class="py-3 px-4 text-left font-semibold text-sm w-24">Heure</th>
                    @foreach($days as $num => $name)
                        @if($entries->has($num))
                        <th class="py-3 px-3 text-center font-semibold text-sm min-w-32">{{ $name }}</th>
                        @endif
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($timeSlots as $slot)
                @php $isLunch = $slot >= '12:00' && $slot < '13:30'; @endphp
                @if($isLunch)
                <tr class="bg-amber-50/60">
                    <td class="text-center py-2 text-xs text-amber-600 font-medium">🍽</td>
                    <td colspan="{{ $entries->count() }}" class="py-2 text-center text-xs text-amber-500/70 italic">Pause déjeuner</td>
                </tr>
                @else
                <tr class="border-b border-base-200 hover">
                    <td class="py-3 px-4 w-24"><div class="text-xs font-bold font-mono text-base-content/70">{{ $slot }}</div></td>
                    @foreach($days as $num => $name)
                    @if($entries->has($num))
                    @php $entry = ($entries[$num] ?? collect())->first(fn($e) => substr($e->start_time, 0, 5) === $slot); @endphp
                    <td class="py-2 px-2">
                        @if($entry)
                        @php $c = $entry->subject?->color; @endphp
                        <div class="rounded-xl p-2 text-left shadow-sm"
                             style="{{ $c ? 'background:color-mix(in srgb,'.$c.' 15%,white);border-left:3px solid '.$c : 'background:#ecfdf5;border-left:3px solid #059669' }}">
                            <p class="font-bold text-xs truncate" style="{{ $c ? 'color:'.$c : 'color:#059669' }}">{{ $entry->subject?->name }}</p>
                            @if($entry->teacher)<p class="text-[10px] text-base-content/55 truncate mt-0.5">{{ $entry->teacher->full_name }}</p>@endif
                            @if($entry->roomModel)<p class="text-[9px] text-base-content/40 flex items-center gap-0.5 mt-0.5"><x-icon name="o-map-pin" class="w-2.5 h-2.5" />{{ $entry->roomModel->name }}</p>@endif
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
              style="{{ $subj->color ? 'background:color-mix(in srgb,'.$subj->color.' 15%,white);border-color:'.$subj->color.';color:'.$subj->color : '' }}">
            @if($subj->color)<span class="w-2 h-2 rounded-full shrink-0" style="background:{{ $subj->color }}"></span>@endif
            {{ $subj->name }}
        </span>
        @endforeach
    </div>
    @endif

    @endif
</div>
