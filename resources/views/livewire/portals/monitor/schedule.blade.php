<?php
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\TimetableEntry;
use App\Models\SchoolClass;

new #[Layout('layouts.monitor')] class extends Component {
    public ?int $filterClassId = null;

    public function with(): array
    {
        $schoolId = auth()->user()->school_id;
        $classes  = SchoolClass::where('school_id', $schoolId)->where('is_active', true)->orderBy('name')->get();

        $entries = TimetableEntry::whereHas('template', fn($q) =>
                $q->where('school_id', $schoolId)->where('is_active', true)
                  ->when($this->filterClassId, fn($q2) => $q2->where('school_class_id', $this->filterClassId))
            )
            ->with(['subject', 'template.schoolClass', 'teacher', 'roomModel'])
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get()
            ->groupBy('day_of_week');

        $days = [0 => 'Dimanche', 1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi'];

        return compact('entries', 'days', 'classes');
    }
};
?>

<div class="p-4 lg:p-6 space-y-6">
    <x-header title="Planning" subtitle="Emplois du temps" separator>
        <x-slot:actions>
            <x-button icon="o-arrow-left" link="{{ route('monitor.dashboard') }}" wire:navigate class="btn-ghost btn-sm" />
        </x-slot:actions>
    </x-header>

    <x-card shadow class="border-0">
        <x-select wire:model.live="filterClassId" label="Filtrer par classe" placeholder="Toutes les classes"
            :options="$classes->map(fn($c) => ['id' => $c->id, 'name' => $c->name])->all()" />
    </x-card>

    @foreach($days as $dayNum => $dayName)
    @if(isset($entries[$dayNum]) && $entries[$dayNum]->isNotEmpty())
    <x-card :title="$dayName" shadow class="border-0">
        <div class="space-y-2">
            @foreach($entries[$dayNum]->sortBy('start_time') as $entry)
            <div class="flex items-center gap-3 p-3 bg-amber-50 border border-amber-200 rounded-xl">
                <div class="text-center min-w-16">
                    <p class="text-xs font-mono font-bold text-amber-800">{{ substr($entry->start_time, 0, 5) }}</p>
                    <p class="text-xs font-mono text-amber-500">{{ substr($entry->end_time, 0, 5) }}</p>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-semibold text-sm text-amber-900">{{ $entry->subject?->name }}</p>
                    <p class="text-xs text-amber-600">{{ $entry->template?->schoolClass?->name }} · {{ $entry->teacher?->full_name }}</p>
                </div>
                @if($entry->display_room)
                <x-badge :value="$entry->display_room" class="badge-warning badge-sm" />
                @endif
            </div>
            @endforeach
        </div>
    </x-card>
    @endif
    @endforeach
</div>
