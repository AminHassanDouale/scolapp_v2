<?php
use App\Models\Teacher;
use App\Models\Subject;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new #[Layout('layouts.app')] class extends Component {
    use Toast, WithPagination;

    public string $search        = '';
    public int    $subjectFilter = 0;
    public string $statusFilter  = '';
    public bool   $showFilters   = false;
    public string $sortBy        = 'name';
    public string $sortDir       = 'asc';

    public function updatingSearch(): void { $this->resetPage(); }

    public function sortBy(string $col): void
    {
        $this->sortDir = $this->sortBy === $col && $this->sortDir === 'asc' ? 'desc' : 'asc';
        $this->sortBy  = $col;
    }

    public function toggleActive(int $id): void
    {
        $teacher = Teacher::findOrFail($id);
        $teacher->update(['is_active' => !$teacher->is_active]);
        $this->success($teacher->is_active ? 'Enseignant activé.' : 'Enseignant désactivé.', position: 'toast-top toast-end', icon: 'o-bolt', css: 'alert-success', timeout: 3000);
    }

    public function deleteTeacher(int $id): void
    {
        $teacher = Teacher::findOrFail($id);
        $teacher->subjects()->detach();
        $teacher->schoolClasses()->detach();
        $teacher->delete();
        $this->success('Enseignant supprimé.', position: 'toast-top toast-end', icon: 'o-trash', css: 'alert-success', timeout: 3000);
    }

    public function with(): array
    {
        $schoolId = auth()->user()->school_id;

        $teachers = Teacher::where('school_id', $schoolId)
            ->with(['subjects', 'schoolClasses'])
            ->when($this->search, fn($q) =>
                $q->where(fn($s) =>
                    $s->where('name',       'like', "%{$this->search}%")
                      ->orWhere('email',    'like', "%{$this->search}%")
                      ->orWhere('reference','like', "%{$this->search}%")
                ))
            ->when($this->subjectFilter, fn($q) =>
                $q->whereHas('subjects', fn($s) => $s->where('subjects.id', $this->subjectFilter))
            )
            ->when($this->statusFilter !== '', fn($q) =>
                $q->where('is_active', $this->statusFilter === 'active')
            )
            ->orderBy($this->sortBy, $this->sortDir)
            ->paginate(20);

        $base    = Teacher::where('school_id', $schoolId);
        $total   = (clone $base)->count();
        $active  = (clone $base)->where('is_active', true)->count();
        $male    = (clone $base)->where('gender', 'male')->count();
        $female  = (clone $base)->where('gender', 'female')->count();

        return [
            'teachers' => $teachers,
            'stats'    => compact('total', 'active', 'male', 'female'),
            'subjects' => Subject::where('school_id', $schoolId)->orderBy('name')->get(),
        ];
    }
};
?>

<div>
    <x-header title="Enseignants" separator progress-indicator>
        <x-slot:actions>
            <x-button label="Nouvel enseignant" icon="o-plus"
                      :link="route('admin.teachers.create')"
                      class="btn-primary" wire:navigate />
        </x-slot:actions>
    </x-header>

    {{-- Stats --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <div class="rounded-2xl bg-linear-to-br from-primary to-primary/70 text-primary-content p-4">
            <p class="text-sm opacity-80">Total</p>
            <p class="text-3xl font-black mt-1">{{ $stats['total'] }}</p>
        </div>
        <button wire:click="$set('statusFilter', '{{ $statusFilter === 'active' ? '' : 'active' }}')"
                class="rounded-2xl bg-linear-to-br from-success to-success/70 text-success-content p-4 text-left transition-all hover:shadow-md
                       {{ $statusFilter === 'active' ? 'ring-2 ring-offset-2 ring-primary' : '' }}">
            <p class="text-sm opacity-80">Actifs</p>
            <p class="text-3xl font-black mt-1">{{ $stats['active'] }}</p>
        </button>
        <div class="rounded-2xl bg-linear-to-br from-info to-info/70 text-info-content p-4">
            <p class="text-sm opacity-80">Hommes</p>
            <p class="text-3xl font-black mt-1">{{ $stats['male'] }}</p>
        </div>
        <div class="rounded-2xl bg-linear-to-br from-secondary to-secondary/70 text-secondary-content p-4">
            <p class="text-sm opacity-80">Femmes</p>
            <p class="text-3xl font-black mt-1">{{ $stats['female'] }}</p>
        </div>
    </div>

    {{-- Toolbar --}}
    <div class="flex items-center gap-3 mb-4">
        <x-input wire:model.live.debounce="search" placeholder="Rechercher (nom, email, référence)..."
                 icon="o-magnifying-glass" clearable class="flex-1" />
        <x-button icon="o-adjustments-horizontal" wire:click="$set('showFilters', true)"
                  class="btn-outline" tooltip="Filtres"
                  :badge="($subjectFilter || $statusFilter !== '') ? '●' : null" badge-classes="badge-primary badge-xs" />
    </div>

    {{-- Table --}}
    <x-card>
        <div class="overflow-x-auto"><table class="table w-full">
            <thead><tr>
                <th>
                    <button wire:click="sortBy('name')" class="flex items-center gap-1 hover:text-primary font-semibold">
                        Enseignant
                        @if($sortBy === 'name')
                        <x-icon name="{{ $sortDir === 'asc' ? 'o-chevron-up' : 'o-chevron-down' }}" class="w-3 h-3"/>
                        @endif
                    </button>
                </th>
                <th>Contact</th>
                <th>Matières</th>
                <th>Classes</th>
                <th>
                    <button wire:click="sortBy('hire_date')" class="flex items-center gap-1 hover:text-primary font-semibold">
                        Embauché le
                        @if($sortBy === 'hire_date')
                        <x-icon name="{{ $sortDir === 'asc' ? 'o-chevron-up' : 'o-chevron-down' }}" class="w-3 h-3"/>
                        @endif
                    </button>
                </th>
                <th>Statut</th>
                <th class="w-24">Actions</th>
            </tr></thead><tbody>

            @forelse($teachers as $teacher)
            <tr wire:key="teacher-{{ $teacher->id }}" class="hover">
                <td>
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full overflow-hidden bg-primary/10 flex items-center justify-center font-bold text-primary text-sm shrink-0">
                            @if($teacher->photo_url)
                            <img src="{{ $teacher->photo_url }}" alt="{{ $teacher->full_name }}" class="w-full h-full object-cover" />
                            @else
                            {{ strtoupper(substr($teacher->name, 0, 1)) }}
                            @endif
                        </div>
                        <div>
                            <a href="{{ route('admin.teachers.show', $teacher->uuid) }}"
                               wire:navigate class="font-semibold hover:text-primary text-sm">
                                {{ $teacher->full_name }}
                            </a>
                            <p class="text-xs font-mono text-base-content/40">{{ $teacher->reference ?? '—' }}</p>
                        </div>
                    </div>
                </td>
                <td>
                    <div class="text-sm space-y-0.5">
                        @if($teacher->email)
                        <p class="text-base-content/70 flex items-center gap-1">
                            <x-icon name="o-envelope" class="w-3 h-3 shrink-0"/>{{ $teacher->email }}
                        </p>
                        @endif
                        @if($teacher->phone)
                        <p class="text-base-content/50 flex items-center gap-1">
                            <x-icon name="o-phone" class="w-3 h-3 shrink-0"/>{{ $teacher->phone }}
                        </p>
                        @endif
                    </div>
                </td>
                <td>
                    <div class="flex flex-wrap gap-1">
                        @foreach($teacher->subjects->take(3) as $subject)
                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold"
                              style="background-color: {{ $subject->color ?? '#6366f1' }}20; color: {{ $subject->color ?? '#6366f1' }}">
                            {{ $subject->code ?? $subject->name }}
                        </span>
                        @endforeach
                        @if($teacher->subjects->count() > 3)
                        <x-badge value="+{{ $teacher->subjects->count() - 3 }}" class="badge-ghost badge-xs" />
                        @endif
                        @if($teacher->subjects->isEmpty())
                        <span class="text-base-content/30 text-xs">—</span>
                        @endif
                    </div>
                </td>
                <td>
                    @if($teacher->schoolClasses->count())
                    <x-badge value="{{ $teacher->schoolClasses->count() }} classe(s)" class="badge-outline badge-sm" />
                    @else
                    <span class="text-base-content/30 text-sm">—</span>
                    @endif
                </td>
                <td class="text-sm text-base-content/60">
                    {{ $teacher->hire_date ? $teacher->hire_date->format('d/m/Y') : '—' }}
                </td>
                <td>
                    <button wire:click="toggleActive({{ $teacher->id }})"
                            class="badge {{ $teacher->is_active ? 'badge-success' : 'badge-ghost' }} badge-sm cursor-pointer hover:opacity-70 transition-opacity">
                        {{ $teacher->is_active ? 'Actif' : 'Inactif' }}
                    </button>
                </td>
                <td>
                    <div class="flex gap-1">
                        <x-button icon="o-eye"
                                  :link="route('admin.teachers.show', $teacher->uuid)"
                                  class="btn-ghost btn-xs" tooltip="Voir" wire:navigate />
                        <x-button icon="o-pencil"
                                  :link="route('admin.teachers.edit', $teacher->uuid)"
                                  class="btn-ghost btn-xs" tooltip="Modifier" wire:navigate />
                        <x-button icon="o-trash"
                                  wire:click="deleteTeacher({{ $teacher->id }})"
                                  wire:confirm="Supprimer cet enseignant définitivement ?"
                                  class="btn-ghost btn-xs text-error" tooltip="Supprimer" />
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" class="text-center py-12 text-base-content/40">
                    <x-icon name="o-user-group" class="w-12 h-12 mx-auto mb-2 opacity-20" />
                    <p>Aucun enseignant trouvé</p>
                    @if($search || $subjectFilter || $statusFilter !== '')
                    <x-button label="Effacer les filtres"
                              wire:click="$set('search',''); $set('subjectFilter', 0); $set('statusFilter','')"
                              class="btn-ghost btn-sm mt-2" />
                    @endif
                </td>
            </tr>
            @endforelse
        </tbody></table></div>
        <div class="mt-4">{{ $teachers->links() }}</div>
    </x-card>

    {{-- Filters drawer --}}
    <x-drawer wire:model="showFilters" title="Filtres" position="right" class="w-72">
        <div class="p-4 space-y-4">
            <x-select
                label="Matière"
                wire:model.live="subjectFilter"
                :options="$subjects"
                option-value="id"
                option-label="name"
                placeholder="Toutes les matières"
                placeholder-value="0"
            />
            <x-select
                label="Statut"
                wire:model.live="statusFilter"
                :options="[['id'=>'active','name'=>'Actifs'],['id'=>'inactive','name'=>'Inactifs']]"
                option-value="id"
                option-label="name"
                placeholder="Tous"
                placeholder-value=""
            />
        </div>
        <x-slot:actions>
            <x-button label="Réinitialiser"
                      wire:click="$set('subjectFilter', 0); $set('statusFilter', ''); $set('showFilters', false)"
                      class="btn-ghost" />
            <x-button label="Fermer" wire:click="$set('showFilters', false)" class="btn-primary" />
        </x-slot:actions>
    </x-drawer>
</div>
