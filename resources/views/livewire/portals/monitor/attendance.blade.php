<?php
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\AttendanceSession;
use App\Models\AttendanceEntry;
use App\Models\SchoolClass;
use Livewire\WithPagination;

new #[Layout('layouts.monitor')] class extends Component {
    use WithPagination;

    public string $selectedDate  = '';
    public ?int $filterClassId   = null;
    public string $filterStatus  = '';

    public function mount(): void
    {
        $this->selectedDate = today()->format('Y-m-d');
    }

    public function with(): array
    {
        $schoolId = auth()->user()->school_id;

        $entries = AttendanceEntry::whereHas('session', function($q) use ($schoolId) {
                $q->where('school_id', $schoolId)->whereDate('date', $this->selectedDate);
                if ($this->filterClassId) $q->where('school_class_id', $this->filterClassId);
            })
            ->when($this->filterStatus, fn($q) => $q->where('status', $this->filterStatus))
            ->with(['student', 'session.schoolClass'])
            ->orderBy('status')
            ->paginate(25);

        $classes = SchoolClass::where('school_id', $schoolId)->where('is_active', true)->orderBy('name')->get();

        return compact('entries', 'classes');
    }
};
?>

<div class="p-4 lg:p-6 space-y-6">
    <x-header title="Présences" subtitle="Consultation des présences" separator>
        <x-slot:actions>
            <x-button icon="o-arrow-left" link="{{ route('monitor.dashboard') }}" wire:navigate class="btn-ghost btn-sm" />
        </x-slot:actions>
    </x-header>

    <x-card shadow class="border-0">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <x-input type="date" label="Date" wire:model.live="selectedDate" />
            <x-select label="Classe" wire:model.live="filterClassId" placeholder="Toutes"
                :options="$classes->map(fn($c) => ['id' => $c->id, 'name' => $c->name])->all()" />
            <x-select label="Statut" wire:model.live="filterStatus" placeholder="Tous"
                :options="[['id' => 'present', 'name' => 'Présent'], ['id' => 'absent', 'name' => 'Absent'], ['id' => 'late', 'name' => 'Retard'], ['id' => 'excused', 'name' => 'Excusé']]" />
        </div>
    </x-card>

    <x-card shadow class="border-0 p-0 overflow-hidden">
        <table class="table table-sm w-full">
            <thead class="bg-base-200">
                <tr>
                    <th>Élève</th>
                    <th>Classe</th>
                    <th>Date</th>
                    <th class="text-center">Statut</th>
                </tr>
            </thead>
            <tbody>
                @forelse($entries as $entry)
                <tr class="hover border-b border-base-100">
                    <td class="font-medium">{{ $entry->student?->full_name }}</td>
                    <td>{{ $entry->session?->schoolClass?->name ?? '—' }}</td>
                    <td class="text-sm text-base-content/60">{{ $entry->session?->date?->format('d/m/Y') }}</td>
                    <td class="text-center">
                        @php $badge = match($entry->status) {
                            'present' => 'badge-success',
                            'absent'  => 'badge-error',
                            'late'    => 'badge-warning',
                            'excused' => 'badge-info',
                            default   => 'badge-ghost'
                        }; $label = match($entry->status) {
                            'present' => 'Présent',
                            'absent'  => 'Absent',
                            'late'    => 'Retard',
                            'excused' => 'Excusé',
                            default   => $entry->status
                        }; @endphp
                        <x-badge :value="$label" class="{{ $badge }} badge-sm" />
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="text-center py-12 text-base-content/40">
                        <x-icon name="o-inbox" class="w-10 h-10 mx-auto mb-2" />
                        <p class="text-sm">Aucune donnée pour ce filtre</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        <div class="p-4">{{ $entries->links() }}</div>
    </x-card>
</div>
