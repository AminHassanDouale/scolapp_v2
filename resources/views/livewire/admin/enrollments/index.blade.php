<?php
use App\Models\Enrollment;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Enums\EnrollmentStatus;
use App\Services\EnrollmentService;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new #[Layout('layouts.app')] class extends Component {
    use Toast, WithPagination;

    public string $search       = '';
    public string $statusFilter = '';
    public int    $yearFilter   = 0;
    public int    $classFilter  = 0;
    public bool   $showFilters  = false;
    public string $sortBy       = 'created_at';
    public string $sortDir      = 'desc';

    public function updatingSearch(): void { $this->resetPage(); }

    public function confirmEnrollment(int $id): void
    {
        $enrollment = Enrollment::findOrFail($id);
        app(EnrollmentService::class)->confirm($enrollment);
        $this->success('Inscription confirmée.', position: 'toast-top toast-end', icon: 'o-banknotes', css: 'alert-success', timeout: 3000);
    }

    public function cancelEnrollment(int $id): void
    {
        Enrollment::findOrFail($id)->update(['status' => EnrollmentStatus::CANCELLED->value]);
        $this->success('Inscription annulée.', position: 'toast-top toast-end', icon: 'o-x-mark', css: 'alert-success', timeout: 3000);
    }

    public function with(): array
    {
        $schoolId = auth()->user()->school_id;

        $query = Enrollment::whereHas('student', fn($q) => $q->where('school_id', $schoolId))
            ->with(['student', 'schoolClass.grade', 'academicYear'])
            ->when($this->search, fn($q) =>
                $q->whereHas('student', fn($s) =>
                    $s->where('name', 'like', "%{$this->search}%")
                ))
            ->when($this->statusFilter, fn($q) => $q->where('status', $this->statusFilter))
            ->when($this->yearFilter,   fn($q) => $q->where('academic_year_id', $this->yearFilter))
            ->when($this->classFilter,  fn($q) => $q->where('school_class_id', $this->classFilter))
            ->orderBy($this->sortBy, $this->sortDir);

        $statusCounts = Enrollment::whereHas('student', fn($q) => $q->where('school_id', $schoolId))
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        $currentYear = AcademicYear::where('school_id', $schoolId)->where('is_current', true)->first();

        return [
            'enrollments'  => $query->paginate(20),
            'statusCounts' => $statusCounts,
            'academicYears' => AcademicYear::where('school_id', $schoolId)->orderByDesc('start_date')->get(),
            'classes'      => SchoolClass::where('school_id', $schoolId)
                ->when($currentYear, fn($q) => $q->where('academic_year_id', $currentYear->id))
                ->with('grade')->orderBy('name')->get(),
            'statusOptions' => collect(EnrollmentStatus::cases())->map(fn($s) => ['id' => $s->value, 'name' => $s->label()])->all(),
        ];
    }
};
?>

<div>
    <x-header title="Inscriptions" separator progress-indicator>
        <x-slot:actions>
            <x-button label="Nouvelle inscription" icon="o-plus"
                      :link="route('admin.enrollments.create')"
                      class="btn-primary" wire:navigate />
        </x-slot:actions>
    </x-header>

    {{-- Stats --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        @foreach(EnrollmentStatus::cases() as $status)
        @php
            $cnt   = $statusCounts[$status->value] ?? 0;
            $color = match($status) {
                EnrollmentStatus::CONFIRMED  => 'from-success to-success/70 text-success-content',
                EnrollmentStatus::HOLD       => 'from-warning to-warning/70 text-warning-content',
                EnrollmentStatus::CANCELLED  => 'from-base-300 to-base-200 text-base-content/60',
            };
        @endphp
        <button wire:click="$set('statusFilter', '{{ $statusFilter === $status->value ? '' : $status->value }}')"
                class="rounded-2xl bg-gradient-to-br {{ $color }} p-4 text-left transition-all hover:shadow-md
                       {{ $statusFilter === $status->value ? 'ring-2 ring-offset-2 ring-primary' : '' }}">
            <p class="text-sm opacity-80">{{ $status->label() }}</p>
            <p class="text-3xl font-black mt-1">{{ $cnt }}</p>
        </button>
        @endforeach
        <div class="rounded-2xl bg-gradient-to-br from-primary to-primary/70 text-primary-content p-4">
            <p class="text-sm opacity-80">Total</p>
            <p class="text-3xl font-black mt-1">{{ $statusCounts->sum() }}</p>
        </div>
    </div>

    {{-- Toolbar --}}
    <div class="flex items-center gap-3 mb-4">
        <x-input wire:model.live.debounce="search" placeholder="Rechercher un élève..."
                 icon="o-magnifying-glass" clearable class="flex-1" />
        <x-button icon="o-adjustments-horizontal" wire:click="$set('showFilters', true)"
                  class="btn-outline" tooltip="Filtres" />
    </div>

    {{-- Table --}}
    <x-card>
        <div class="overflow-x-auto"><table class="table w-full">
            <thead><tr>
                <th>Élève</th>
                <th>Classe</th>
                <th>Année scolaire</th>
                <th>Statut</th>
                <th>Date d'inscription</th>
                <th class="w-28">Actions</th>
            </tr></thead><tbody>

            @forelse($enrollments as $enrollment)
            @php
                $statusClass = match($enrollment->status) {
                    EnrollmentStatus::CONFIRMED => 'badge-success',
                    EnrollmentStatus::HOLD      => 'badge-warning',
                    EnrollmentStatus::CANCELLED => 'badge-ghost',
                    default => 'badge-ghost',
                };
            @endphp
            <tr wire:key="enrollment-{{ $enrollment->id }}" class="hover">
                <td>
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center font-bold text-primary text-sm">
                            {{ substr($enrollment->student?->name ?? '?', 0, 1) }}
                        </div>
                        <div>
                            <a href="{{ route('admin.students.show', $enrollment->student?->uuid) }}"
                               wire:navigate class="font-semibold hover:text-primary text-sm">
                                {{ $enrollment->student?->full_name }}
                            </a>
                            @if($enrollment->student?->student_code)
                            <p class="text-xs font-mono text-base-content/50">{{ $enrollment->student->student_code }}</p>
                            @endif
                        </div>
                    </div>
                </td>
                <td>
                    <div>
                        <p class="font-semibold text-sm">{{ $enrollment->schoolClass?->name }}</p>
                        <p class="text-xs text-base-content/50">{{ $enrollment->schoolClass?->grade?->name }}</p>
                    </div>
                </td>
                <td class="text-sm">{{ $enrollment->academicYear?->name }}</td>
                <td>
                    <x-badge :value="$enrollment->status->label()" class="{{ $statusClass }} badge-sm" />
                </td>
                <td class="text-sm text-base-content/70">{{ $enrollment->created_at->format('d/m/Y') }}</td>
                <td>
                    <div class="flex gap-1">
                        <x-button icon="o-eye"
                                  :link="route('admin.enrollments.show', $enrollment->uuid)"
                                  class="btn-ghost btn-xs" tooltip="Voir" wire:navigate />
                        @if($enrollment->status === EnrollmentStatus::HOLD)
                        <x-button icon="o-check"
                                  wire:click="confirmEnrollment({{ $enrollment->id }})"
                                  wire:confirm="Confirmer cette inscription ?"
                                  class="btn-ghost btn-xs text-success" tooltip="Confirmer" />
                        <x-button icon="o-x-mark"
                                  wire:click="cancelEnrollment({{ $enrollment->id }})"
                                  wire:confirm="Annuler cette inscription ?"
                                  class="btn-ghost btn-xs text-error" tooltip="Annuler" />
                        @endif
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="text-center py-12 text-base-content/40">
                    <x-icon name="o-academic-cap" class="w-12 h-12 mx-auto mb-2 opacity-20" />
                    <p>Aucune inscription trouvée</p>
                </td>
            </tr>
            @endforelse
        </tbody></table></div>
        <div class="mt-4">{{ $enrollments->links() }}</div>
    </x-card>

    {{-- Filters drawer --}}
    <x-drawer wire:model="showFilters" title="Filtres" position="right" class="w-80">
        <div class="p-4 space-y-4">
            <x-select label="Statut" wire:model.live="statusFilter"
                      :options="$statusOptions" option-value="id" option-label="name"
                      placeholder="Tous" placeholder-value="" />
            <x-select label="Année scolaire" wire:model.live="yearFilter"
                      :options="$academicYears" option-value="id" option-label="name"
                      placeholder="Toutes" placeholder-value="0" />
            <x-select label="Classe" wire:model.live="classFilter"
                      :options="$classes" option-value="id" option-label="name"
                      placeholder="Toutes" placeholder-value="0" />
        </div>
        <x-slot:actions>
            <x-button label="Réinitialiser"
                      wire:click="$set('statusFilter',''); $set('yearFilter',0); $set('classFilter',0); $set('showFilters',false)"
                      class="btn-ghost" />
            <x-button label="Fermer" wire:click="$set('showFilters', false)" class="btn-primary" />
        </x-slot:actions>
    </x-drawer>
</div>
