<?php
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Student;
use App\Models\SchoolClass;
use Livewire\WithPagination;

new #[Layout('layouts.monitor')] class extends Component {
    use WithPagination;

    public string $search       = '';
    public ?int $filterClassId  = null;

    public function with(): array
    {
        $schoolId = auth()->user()->school_id;

        $students = Student::where('school_id', $schoolId)
            ->when($this->filterClassId, fn($q) => $q->whereHas('enrollments', fn($q) => $q->where('school_class_id', $this->filterClassId)->where('status', 'active')))
            ->when($this->search, fn($q) => $q->where(function($q) {
                $q->where('name', 'like', "%{$this->search}%");
            }))
            ->with(['enrollments.schoolClass'])
            ->orderBy('name')
            ->paginate(25);

        $classes = SchoolClass::where('school_id', $schoolId)->where('is_active', true)->orderBy('name')->get();

        return compact('students', 'classes');
    }
};
?>

<div class="p-4 lg:p-6 space-y-6">
    <x-header title="Élèves" subtitle="Liste des élèves" separator>
        <x-slot:actions>
            <x-button icon="o-arrow-left" link="{{ route('monitor.dashboard') }}" wire:navigate class="btn-ghost btn-sm" />
        </x-slot:actions>
    </x-header>

    <x-card shadow class="border-0">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <x-input wire:model.live.debounce="search" placeholder="Rechercher..." icon="o-magnifying-glass" />
            <x-select wire:model.live="filterClassId" placeholder="Toutes les classes"
                :options="$classes->map(fn($c) => ['id' => $c->id, 'name' => $c->name])->all()" />
        </div>
    </x-card>

    <x-card shadow class="border-0 p-0 overflow-hidden">
        <table class="table table-sm w-full">
            <thead class="bg-base-200">
                <tr>
                    <th>Élève</th>
                    <th>Référence</th>
                    <th>Classe</th>
                    <th>Genre</th>
                </tr>
            </thead>
            <tbody>
                @forelse($students as $student)
                <tr class="hover border-b border-base-100">
                    <td>
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-amber-100 flex items-center justify-center">
                                <span class="text-xs font-bold text-amber-700">{{ substr($student->name, 0, 1) }}</span>
                            </div>
                            <span class="font-medium">{{ $student->full_name }}</span>
                        </div>
                    </td>
                    <td class="font-mono text-xs text-base-content/60">{{ $student->reference }}</td>
                    <td>{{ $student->enrollments->first()?->schoolClass?->name ?? '—' }}</td>
                    <td>{{ $student->gender?->label() ?? '—' }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="text-center py-12 text-base-content/40">
                        <x-icon name="o-users" class="w-10 h-10 mx-auto mb-2" />
                        <p class="text-sm">Aucun élève trouvé</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        <div class="p-4">{{ $students->links() }}</div>
    </x-card>
</div>
