<?php
use App\Models\Student;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Enums\GenderType;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Mary\Traits\Toast;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination, Toast;

    #[Url] public string $search     = '';
    #[Url] public int    $gradeFilter = 0;
    #[Url] public int    $classFilter = 0;
    #[Url] public string $genderFilter = 'all';
    #[Url] public array  $sortBy = ['column' => 'name', 'direction' => 'asc'];

    public bool $showFilters = false;

    private function schoolId(): int
    {
        return auth()->user()->school_id;
    }

    public function headers(): array
    {
        return [
            ['key' => 'student_code', 'label' => __('students.fields.student_code'), 'class' => 'hidden lg:table-cell'],
            ['key' => 'full_name',    'label' => __('students.table.name'),           'sortable' => false],
            ['key' => 'class_name',   'label' => __('students.table.class'),          'sortable' => false, 'class' => 'hidden md:table-cell'],
            ['key' => 'gender_label', 'label' => __('students.fields.gender'),        'sortable' => false, 'class' => 'hidden lg:table-cell'],
            ['key' => 'status_badge', 'label' => __('students.table.status'),         'sortable' => false],
        ];
    }

    public function students(): LengthAwarePaginator
    {
        return Student::query()
            ->where('school_id', $this->schoolId())
            ->with(['currentEnrollment.schoolClass', 'currentEnrollment.grade'])
            ->when($this->search, fn($q) =>
                $q->where(fn($sq) =>
                    $sq->where('name', 'like', "%{$this->search}%")
                       ->orWhere('student_code', 'like', "%{$this->search}%")
                )
            )
            ->when($this->genderFilter !== 'all', fn($q) =>
                $q->where('gender', $this->genderFilter)
            )
            ->when($this->classFilter, fn($q) =>
                $q->whereHas('currentEnrollment', fn($sq) =>
                    $sq->where('school_class_id', $this->classFilter)
                )
            )
            ->when($this->gradeFilter, fn($q) =>
                $q->whereHas('currentEnrollment', fn($sq) =>
                    $sq->where('grade_id', $this->gradeFilter)
                )
            )
            ->orderBy($this->sortBy['column'], $this->sortBy['direction'])
            ->paginate(20);
    }

    public function filterCount(): int
    {
        return collect([
            $this->genderFilter !== 'all',
            (bool) $this->classFilter,
            (bool) $this->gradeFilter,
        ])->filter()->count();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'genderFilter', 'classFilter', 'gradeFilter']);
        $this->resetPage();
        $this->success(__('students.filters_reset', [], 'Filtres réinitialisés'), position: 'toast-top toast-end', icon: 'o-arrow-path', css: 'alert-success', timeout: 3000);
    }

    public function deleteStudent(int $id): void
    {
        Student::where('school_id', $this->schoolId())->findOrFail($id)->delete();
        $this->success(__('students.deleted'), position: 'toast-top toast-end', icon: 'o-check-circle', css: 'alert-success', timeout: 3000);
    }

    public function with(): array
    {
        $students = $this->students();

        $students->through(function (Student $s) {
            $s->full_name    = $s->full_name;
            $s->class_name   = $s->currentEnrollment?->schoolClass?->name ?? '—';
            $s->gender_label = $s->gender?->label() ?? '—';
            $s->status_badge = $s->is_active;
            return $s;
        });

        return [
            'headers'  => $this->headers(),
            'students' => $students,
            'filterCount' => $this->filterCount(),
            'grades'   => Grade::where('school_id', $this->schoolId())
                ->where('is_active', true)->orderBy('order')->get()
                ->map(fn($g) => ['id' => $g->id, 'name' => $g->name])->all(),
            'classes'  => SchoolClass::where('school_id', $this->schoolId())
                ->where('is_active', true)->orderBy('name')->get()
                ->map(fn($c) => ['id' => $c->id, 'name' => $c->name])->all(),
            'genders'  => collect(GenderType::cases())
                ->map(fn($g) => ['id' => $g->value, 'name' => $g->label()])->all(),
            'stats' => [
                'total'  => Student::where('school_id', $this->schoolId())->where('is_active', true)->count(),
                'boys'   => Student::where('school_id', $this->schoolId())->where('gender', 'male')->count(),
                'girls'  => Student::where('school_id', $this->schoolId())->where('gender', 'female')->count(),
            ],
        ];
    }
};
?>

<div>
    <x-header :title="__('students.title')" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-input
                wire:model.live.debounce="search"
                :placeholder="__('students.search')"
                icon="o-magnifying-glass"
                clearable
            />
        </x-slot:middle>
        <x-slot:actions>
            <x-button
                :label="__('invoices.filters.title')"
                icon="o-funnel"
                :badge="$filterCount"
                badge-classes="font-mono"
                @click="$wire.showFilters = true"
                class="bg-base-300"
                responsive
            />
            <x-button
                :label="__('students.new')"
                icon="o-plus"
                :link="route('admin.students.create')"
                wire:navigate
                class="btn-primary"
                responsive
            />
        </x-slot:actions>
    </x-header>

    {{-- Stats --}}
    <div class="grid grid-cols-3 gap-4 mb-6">
        @foreach([
            ['label' => __('students.stats.total'),  'val' => $stats['total'], 'grad' => 'from-blue-500 to-blue-600',   'icon' => 'o-user-group'],
            ['label' => __('enums.gender.male'),      'val' => $stats['boys'],  'grad' => 'from-sky-500 to-sky-600',     'icon' => 'o-user'],
            ['label' => __('enums.gender.female'),    'val' => $stats['girls'], 'grad' => 'from-pink-500 to-pink-600',   'icon' => 'o-user'],
        ] as $s)
        <div class="relative p-5 overflow-hidden bg-base-100 shadow-lg rounded-2xl">
            <div class="absolute top-0 right-0 w-20 h-20 translate-x-1/2 -translate-y-1/2 rounded-full opacity-10 bg-gradient-to-br {{ $s['grad'] }}"></div>
            <div class="relative flex items-center gap-3">
                <div class="p-2.5 rounded-xl bg-gradient-to-br {{ $s['grad'] }} shadow">
                    <x-icon name="{{ $s['icon'] }}" class="w-5 h-5 text-white"/>
                </div>
                <div>
                    <p class="text-xs text-base-content/60">{{ $s['label'] }}</p>
                    <p class="text-2xl font-black text-base-content">{{ $s['val'] }}</p>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Table --}}
    <x-card>
        <x-table
            :headers="$headers"
            :rows="$students"
            :sort-by="$sortBy"
            with-pagination
            wire:model="sortBy"
        >
            @scope('cell_full_name', $student)
            <div class="flex items-center gap-3">
                <div class="avatar placeholder">
                    <div class="w-9 h-9 rounded-full bg-neutral text-neutral-content">
                        <span class="text-sm font-bold">{{ substr($student->name, 0, 1) }}</span>
                    </div>
                </div>
                <div>
                    <p class="font-semibold">{{ $student->full_name }}</p>
                    @if($student->student_code)
                    <p class="text-xs text-base-content/50 font-mono">{{ $student->student_code }}</p>
                    @endif
                </div>
            </div>
            @endscope

            @scope('cell_status_badge', $student)
            <x-badge
                :value="$student->is_active ? __('Actif') : __('Inactif')"
                :class="$student->is_active ? 'badge-success' : 'badge-error'"
            />
            @endscope

            @scope('actions', $student)
            <div class="flex items-center gap-1">
                <a href="{{ route('admin.students.show', $student->uuid) }}"
                   wire:navigate class="btn btn-ghost btn-xs">
                    <x-icon name="o-eye" class="w-3.5 h-3.5"/>
                </a>
                <a href="{{ route('admin.students.edit', $student->uuid) }}"
                   wire:navigate class="btn btn-ghost btn-xs">
                    <x-icon name="o-pencil" class="w-3.5 h-3.5"/>
                </a>
                <button
                    wire:click="deleteStudent({{ $student->id }})"
                    wire:confirm="{{ __('students.confirm_delete') }}"
                    class="btn btn-ghost btn-xs text-error">
                    <x-icon name="o-trash" class="w-3.5 h-3.5"/>
                </button>
            </div>
            @endscope
        </x-table>
    </x-card>

    {{-- Filters Drawer --}}
    <x-drawer wire:model="showFilters" :title="__('invoices.filters.title')" right separator with-close-button class="lg:w-96">
        <div class="space-y-4">
            <x-select
                :label="__('students.fields.gender')"
                wire:model.live="genderFilter"
                :options="$genders"
                option-value="id"
                option-label="name"
                placeholder="{{ __('invoices.filters.all') }}"
                placeholder-value="all"
            />
            <x-select
                :label="__('invoices.filters.class')"
                wire:model.live="classFilter"
                :options="$classes"
                option-value="id"
                option-label="name"
                placeholder="{{ __('invoices.filters.all') }}"
                placeholder-value="0"
            />
            <x-select
                :label="__('navigation.academic')"
                wire:model.live="gradeFilter"
                :options="$grades"
                option-value="id"
                option-label="name"
                placeholder="{{ __('invoices.filters.all') }}"
                placeholder-value="0"
            />
        </div>
        <x-slot:actions>
            <x-button :label="__('invoices.filters.reset')" icon="o-x-mark" wire:click="resetFilters" />
            <x-button :label="__('invoices.filters.apply')" class="btn-primary" @click="$wire.showFilters = false" />
        </x-slot:actions>
    </x-drawer>
</div>
