<?php
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Student;
use App\Models\TimetableEntry;
use App\Models\SchoolClass;

new #[Layout('layouts.student')] class extends Component {
    public function with(): array
    {
        $student    = Student::where('user_id', auth()->id())->first();
        $enrollment = $student?->enrollments()->where('status', 'active')->with('schoolClass')->first();

        $entries = $enrollment?->schoolClass
            ? TimetableEntry::where('school_class_id', $enrollment->school_class_id)
                ->with(['subject', 'teacher', 'room'])
                ->orderBy('day_of_week')
                ->orderBy('start_time')
                ->get()
                ->groupBy('day_of_week')
            : collect();

        $days = [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi'];

        return compact('entries', 'days', 'enrollment');
    }
};
?>

<div class="p-4 lg:p-6 space-y-6">
    <x-header title="{{ __('navigation.timetable') }}" subtitle="{{ $enrollment?->schoolClass?->name ?? 'Mon emploi du temps' }}" separator>
        <x-slot:actions>
            <x-button icon="o-arrow-left" link="{{ route('student.dashboard') }}" wire:navigate class="btn-ghost btn-sm" />
        </x-slot:actions>
    </x-header>

    @if($entries->isEmpty())
        <x-alert icon="o-information-circle" class="alert-info">Aucun emploi du temps disponible pour votre classe.</x-alert>
    @else
    <div class="overflow-x-auto">
        <table class="table table-sm w-full bg-base-100 rounded-xl shadow-sm">
            <thead style="background: linear-gradient(135deg, #7c3aed, #8b5cf6); color: white;">
                <tr>
                    <th class="py-3 px-4 text-left font-semibold text-sm w-32">Heure</th>
                    @foreach([1=>'Lundi',2=>'Mardi',3=>'Mercredi',4=>'Jeudi',5=>'Vendredi',6=>'Samedi'] as $dayNum => $dayName)
                        @if(isset($entries[$dayNum]))
                            <th class="py-3 px-3 text-center font-semibold text-sm min-w-32">{{ $dayName }}</th>
                        @endif
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach(['07:30','08:30','09:30','10:30','11:30','13:00','14:00','15:00','16:00'] as $slot)
                <tr class="border-b border-base-200 hover">
                    <td class="py-3 px-4">
                        <span class="text-xs font-mono text-base-content/60 bg-base-200 px-2 py-1 rounded">{{ $slot }}</span>
                    </td>
                    @foreach([1,2,3,4,5,6] as $dayNum)
                    @if(isset($entries[$dayNum]))
                    @php $entry = $entries[$dayNum]->first(fn($e) => substr($e->start_time, 0, 5) === $slot); @endphp
                    <td class="py-2 px-2 text-center">
                        @if($entry)
                            <div class="bg-violet-50 border border-violet-200 rounded-lg p-2 text-left">
                                <p class="font-semibold text-xs text-violet-800">{{ $entry->subject?->name }}</p>
                                <p class="text-xs text-violet-600">{{ $entry->teacher?->full_name }}</p>
                                @if($entry->room)
                                    <p class="text-xs text-base-content/50">{{ $entry->room->name }}</p>
                                @endif
                            </div>
                        @else
                            <span class="text-base-content/10">—</span>
                        @endif
                    </td>
                    @endif
                    @endforeach
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>
