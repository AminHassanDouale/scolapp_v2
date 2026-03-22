<?php
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Teacher;
use App\Models\TimetableEntry;

new #[Layout('layouts.teacher')] class extends Component {
    public string $selectedWeek = '';

    public function mount(): void
    {
        $this->selectedWeek = now()->startOfWeek()->format('Y-m-d');
    }

    public function with(): array
    {
        $user    = auth()->user();
        $teacher = Teacher::where('user_id', $user->id)->first();

        $entries = $teacher
            ? TimetableEntry::where('teacher_id', $teacher->id)
                ->with(['subject', 'schoolClass', 'room'])
                ->orderBy('day_of_week')
                ->orderBy('start_time')
                ->get()
                ->groupBy('day_of_week')
            : collect();

        $days = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];

        return compact('entries', 'days', 'teacher');
    }
};
?>

<div class="p-4 lg:p-6 space-y-6">
    <x-header title="{{ __('navigation.timetable') }}" subtitle="{{ __('navigation.my_schedule') }}" separator>
        <x-slot:actions>
            <x-button icon="o-arrow-left" link="{{ route('teacher.dashboard') }}" wire:navigate class="btn-ghost btn-sm" />
        </x-slot:actions>
    </x-header>

    <div class="overflow-x-auto">
        <table class="table table-sm w-full bg-base-100 rounded-xl shadow-sm">
            <thead class="bg-indigo-600 text-white">
                <tr>
                    <th class="py-3 px-4 text-left font-semibold text-sm w-32">Heure</th>
                    @foreach($days as $day)
                        <th class="py-3 px-3 text-center font-semibold text-sm min-w-32">{{ $day }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @php
                    $slots = ['07:30','08:30','09:30','10:30','11:30','13:00','14:00','15:00','16:00'];
                    $dayMap = ['Lundi'=>1,'Mardi'=>2,'Mercredi'=>3,'Jeudi'=>4,'Vendredi'=>5,'Samedi'=>6];
                @endphp
                @foreach($slots as $slot)
                <tr class="border-b border-base-200 hover">
                    <td class="py-3 px-4">
                        <span class="text-xs font-mono text-base-content/60 bg-base-200 px-2 py-1 rounded">{{ $slot }}</span>
                    </td>
                    @foreach($days as $day)
                    @php
                        $dayNum = $dayMap[$day];
                        $entry  = ($entries[$dayNum] ?? collect())->first(fn($e) => substr($e->start_time, 0, 5) === $slot);
                    @endphp
                    <td class="py-2 px-2 text-center">
                        @if($entry)
                            <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-2 text-left">
                                <p class="font-semibold text-xs text-indigo-800">{{ $entry->subject?->name }}</p>
                                <p class="text-xs text-indigo-600">{{ $entry->schoolClass?->name }}</p>
                                @if($entry->room)
                                    <p class="text-xs text-base-content/50">{{ $entry->room->name }}</p>
                                @endif
                            </div>
                        @else
                            <span class="text-base-content/10">—</span>
                        @endif
                    </td>
                    @endforeach
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Legend --}}
    <div class="flex items-center gap-4 text-xs text-base-content/50">
        <span class="flex items-center gap-1">
            <span class="w-3 h-3 rounded bg-indigo-200 inline-block"></span> Cours planifié
        </span>
        <span class="flex items-center gap-1">
            <span class="w-3 h-3 rounded bg-base-200 inline-block"></span> Libre
        </span>
    </div>
</div>
