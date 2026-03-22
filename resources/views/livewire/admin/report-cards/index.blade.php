<?php
use App\Models\ReportCard;
use App\Models\SchoolClass;
use App\Models\AcademicYear;
use App\Enums\ReportPeriod;
use App\Services\ReportCardService;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new #[Layout('layouts.app')] class extends Component {
    use Toast, WithPagination;

    public string $search        = '';
    public int    $classFilter   = 0;
    public string $periodFilter  = '';
    public int    $yearFilter    = 0;
    public bool   $showGenerate  = false;

    // Generate form
    public int    $gen_classId = 0;
    public string $gen_period  = 'trimester_1';
    public int    $gen_yearId  = 0;

    public function mount(): void
    {
        $current = AcademicYear::where('school_id', auth()->user()->school_id)
            ->where('is_current', true)->first();
        if ($current) {
            $this->yearFilter = $current->id;
            $this->gen_yearId = $current->id;
        }
    }

    public function updatingSearch(): void       { $this->resetPage(); }
    public function updatingClassFilter(): void  { $this->resetPage(); }
    public function updatingPeriodFilter(): void { $this->resetPage(); }

    public function generateReportCards(): void
    {
        $this->validate([
            'gen_classId' => 'required|integer|min:1',
            'gen_period'  => 'required|string',
            'gen_yearId'  => 'required|integer|min:1',
        ]);

        $class  = SchoolClass::findOrFail($this->gen_classId);
        $period = ReportPeriod::from($this->gen_period);
        $year   = AcademicYear::findOrFail($this->gen_yearId);

        $count = app(ReportCardService::class)->generateForClass($class, $period, $year);

        $this->reset(['showGenerate', 'gen_classId', 'gen_period']);
        $this->success("{$count} bulletin(s) générés pour {$class->name}.", position: 'toast-top toast-end', icon: 'o-plus-circle', css: 'alert-success', timeout: 3000);
    }

    public function publishAll(): void
    {
        if (!$this->periodFilter || !$this->classFilter) {
            $this->error('Sélectionnez une classe et une période pour publier en masse.', position: 'toast-top toast-center', icon: 'o-exclamation-circle', css: 'alert-error', timeout: 4000);
            return;
        }

        $count = ReportCard::whereHas('enrollment', fn($q) =>
                $q->where('school_class_id', $this->classFilter)
                  ->when($this->yearFilter, fn($e) => $e->where('academic_year_id', $this->yearFilter))
            )
            ->where('period', $this->periodFilter)
            ->where('is_published', false)
            ->update(['is_published' => true, 'published_at' => now()]);

        $this->success("{$count} bulletin(s) publiés.", position: 'toast-top toast-end', icon: 'o-paper-airplane', css: 'alert-success', timeout: 3000);
    }

    public function publish(int $id): void
    {
        ReportCard::findOrFail($id)->update(['is_published' => true, 'published_at' => now()]);
        $this->success('Bulletin publié.', position: 'toast-top toast-end', icon: 'o-paper-airplane', css: 'alert-success', timeout: 3000);
    }

    public function unpublish(int $id): void
    {
        ReportCard::findOrFail($id)->update(['is_published' => false, 'published_at' => null]);
        $this->success('Publication annulée.', position: 'toast-top toast-end', icon: 'o-x-mark', css: 'alert-success', timeout: 3000);
    }

    public function with(): array
    {
        $schoolId = auth()->user()->school_id;

        $reportCards = ReportCard::whereHas('enrollment.student', fn($q) => $q->where('school_id', $schoolId))
            ->with(['enrollment.student', 'enrollment.schoolClass.grade', 'enrollment.academicYear'])
            ->when($this->search, fn($q) =>
                $q->whereHas('enrollment.student', fn($s) =>
                    $s->where('name', 'like', "%{$this->search}%")
                )
            )
            ->when($this->classFilter, fn($q) =>
                $q->whereHas('enrollment', fn($e) => $e->where('school_class_id', $this->classFilter))
            )
            ->when($this->periodFilter, fn($q) => $q->where('period', $this->periodFilter))
            ->when($this->yearFilter, fn($q) =>
                $q->whereHas('enrollment', fn($e) => $e->where('academic_year_id', $this->yearFilter))
            )
            ->orderByDesc('created_at')
            ->paginate(25);

        // Stats scoped to current filters
        $baseQuery = fn() => ReportCard::whereHas('enrollment.student', fn($q) => $q->where('school_id', $schoolId))
            ->when($this->yearFilter, fn($q) =>
                $q->whereHas('enrollment', fn($e) => $e->where('academic_year_id', $this->yearFilter))
            );

        $totalCount     = $baseQuery()->count();
        $publishedCount = $baseQuery()->where('is_published', true)->count();
        $draftCount     = $totalCount - $publishedCount;
        $avgGeneral     = $baseQuery()->whereNotNull('average')->avg('average');

        $classes = SchoolClass::where('school_id', $schoolId)
            ->when($this->yearFilter, fn($q) => $q->where('academic_year_id', $this->yearFilter))
            ->with('grade')->orderBy('name')->get();

        return [
            'reportCards'    => $reportCards,
            'totalCount'     => $totalCount,
            'publishedCount' => $publishedCount,
            'draftCount'     => $draftCount,
            'avgGeneral'     => $avgGeneral ? round($avgGeneral, 2) : null,
            'classes'        => $classes,
            'academicYears'  => AcademicYear::where('school_id', $schoolId)->orderByDesc('start_date')->get(),
            'periods'        => collect(ReportPeriod::cases())->map(fn($p) => ['id' => $p->value, 'name' => $p->label()])->all(),
        ];
    }
};
?>

<div>
    <x-header title="Bulletins scolaires" subtitle="Générez, consultez et publiez les bulletins" separator progress-indicator>
        <x-slot:actions>
            <x-button label="Modèle" icon="o-cog-6-tooth"
                      :link="route('admin.report-cards.template')" wire:navigate
                      class="btn-ghost btn-sm" />
            <x-button label="Générer des bulletins" icon="o-document-plus"
                      wire:click="$set('showGenerate', true)"
                      class="btn-primary" />
        </x-slot:actions>
    </x-header>

    {{-- Stats strip --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <div class="rounded-2xl bg-linear-to-br from-primary to-primary/70 p-4 text-primary-content">
            <div class="flex items-center gap-2 opacity-80 mb-1">
                <x-icon name="o-document-text" class="w-4 h-4" />
                <span class="text-xs">Total</span>
            </div>
            <p class="text-3xl font-black">{{ $totalCount }}</p>
        </div>
        <div class="rounded-2xl bg-linear-to-br from-success to-success/70 p-4 text-success-content">
            <div class="flex items-center gap-2 opacity-80 mb-1">
                <x-icon name="o-eye" class="w-4 h-4" />
                <span class="text-xs">Publiés</span>
            </div>
            <p class="text-3xl font-black">{{ $publishedCount }}</p>
        </div>
        <div class="rounded-2xl bg-base-200 p-4">
            <div class="flex items-center gap-2 text-base-content/60 mb-1">
                <x-icon name="o-pencil" class="w-4 h-4" />
                <span class="text-xs">Brouillons</span>
            </div>
            <p class="text-3xl font-black">{{ $draftCount }}</p>
        </div>
        <div class="rounded-2xl bg-linear-to-br from-info to-info/70 p-4 text-info-content">
            <div class="flex items-center gap-2 opacity-80 mb-1">
                <x-icon name="o-chart-bar" class="w-4 h-4" />
                <span class="text-xs">Moyenne générale</span>
            </div>
            <p class="text-3xl font-black">{{ $avgGeneral ? number_format($avgGeneral, 2) : '—' }}</p>
        </div>
    </div>

    {{-- Filters row --}}
    <div class="flex items-center gap-3 mb-4 flex-wrap">
        <x-input wire:model.live.debounce="search" placeholder="Rechercher un élève..."
                 icon="o-magnifying-glass" clearable class="input-sm w-52" />
        <x-select wire:model.live="yearFilter"
                  :options="$academicYears" option-value="id" option-label="name"
                  placeholder="Toutes les années" placeholder-value="0" class="select-sm w-44" />
        <x-select wire:model.live="classFilter"
                  :options="$classes" option-value="id" option-label="name"
                  placeholder="Toutes les classes" placeholder-value="0" class="select-sm w-44" />
        <x-select wire:model.live="periodFilter"
                  :options="$periods" option-value="id" option-label="name"
                  placeholder="Toutes les périodes" placeholder-value="" class="select-sm w-44" />

        @if($classFilter && $periodFilter)
        <x-button label="Publier tout" icon="o-eye" wire:click="publishAll"
                  wire:confirm="Publier tous les bulletins brouillons de cette classe/période ?"
                  class="btn-success btn-sm" />
        @endif

        @if($search || $classFilter || $periodFilter)
        <x-button icon="o-x-mark" wire:click="$set('search',''); $set('classFilter',0); $set('periodFilter','')"
                  class="btn-ghost btn-sm" tooltip="Réinitialiser les filtres" />
        @endif
    </div>

    {{-- Table --}}
    <x-card>
        <div class="overflow-x-auto"><table class="table w-full">
            <thead><tr>
                <th>Élève</th>
                <th>Classe</th>
                <th>Période</th>
                <th class="text-center">Moyenne</th>
                <th class="text-center">Rang</th>
                <th class="text-center">Moy. classe</th>
                <th>Statut</th>
                <th class="w-28">Actions</th>
            </tr></thead><tbody>

            @forelse($reportCards as $rc)
            @php
                $avg = $rc->average !== null ? (float)$rc->average : null;
                $mention = match(true) {
                    $avg === null    => ['—',             'badge-ghost'],
                    $avg >= 16       => ['Très Bien',     'badge-success'],
                    $avg >= 14       => ['Bien',           'badge-info'],
                    $avg >= 12       => ['Assez Bien',    'badge-primary'],
                    $avg >= 10       => ['Passable',       'badge-warning'],
                    default          => ['Insuffisant',   'badge-error'],
                };
            @endphp
            <tr wire:key="rc-{{ $rc->id }}" class="hover">
                <td>
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center font-bold text-primary text-sm shrink-0">
                            {{ strtoupper(substr($rc->enrollment?->student?->name ?? '?', 0, 1)) }}
                        </div>
                        <a href="{{ route('admin.report-cards.show', $rc->uuid) }}"
                           wire:navigate class="font-semibold hover:text-primary text-sm">
                            {{ $rc->enrollment?->student?->full_name }}
                        </a>
                    </div>
                </td>
                <td class="text-sm">
                    <div>{{ $rc->enrollment?->schoolClass?->name }}</div>
                    <div class="text-xs text-base-content/40">{{ $rc->enrollment?->schoolClass?->grade?->name }}</div>
                </td>
                <td>
                    <x-badge :value="$rc->period?->label() ?? $rc->period" class="badge-outline badge-sm" />
                </td>
                <td class="text-center">
                    @if($avg !== null)
                    <div class="flex flex-col items-center gap-0.5">
                        <span class="font-black text-lg {{ $avg >= 10 ? 'text-success' : 'text-error' }}">
                            {{ number_format($avg, 2) }}
                        </span>
                        <x-badge :value="$mention[0]" class="{{ $mention[1] }} badge-xs" />
                    </div>
                    @else
                    <span class="text-base-content/30">—</span>
                    @endif
                </td>
                <td class="text-center text-sm">
                    @if($rc->rank)
                    <span class="font-bold">{{ $rc->rank }}</span>
                    @if($rc->class_size)
                    <span class="text-xs text-base-content/40">/{{ $rc->class_size }}</span>
                    @endif
                    @else
                    <span class="text-base-content/30">—</span>
                    @endif
                </td>
                <td class="text-center text-sm text-base-content/60">
                    {{ $rc->class_average !== null ? number_format($rc->class_average, 2) : '—' }}
                </td>
                <td>
                    @if($rc->is_published)
                    <x-badge value="Publié" class="badge-success badge-sm" />
                    @else
                    <x-badge value="Brouillon" class="badge-ghost badge-sm" />
                    @endif
                </td>
                <td>
                    <div class="flex gap-1">
                        <x-button icon="o-eye" :link="route('admin.report-cards.show', $rc->uuid)"
                                  class="btn-ghost btn-xs" wire:navigate tooltip="Voir" />
                        @if($rc->is_published)
                        <x-button icon="o-eye-slash" wire:click="unpublish({{ $rc->id }})"
                                  class="btn-ghost btn-xs text-warning" tooltip="Dépublier" />
                        @else
                        <x-button icon="o-paper-airplane" wire:click="publish({{ $rc->id }})"
                                  class="btn-ghost btn-xs text-success" tooltip="Publier" />
                        @endif
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="8" class="text-center py-16 text-base-content/40">
                    <x-icon name="o-document-text" class="w-14 h-14 mx-auto mb-3 opacity-20" />
                    <p class="font-semibold">Aucun bulletin trouvé</p>
                    <p class="text-sm mt-1">Cliquez sur "Générer des bulletins" pour commencer.</p>
                </td>
            </tr>
            @endforelse
        </tbody></table></div>
        <div class="mt-4">{{ $reportCards->links() }}</div>
    </x-card>

    {{-- Generate modal --}}
    <x-modal wire:model="showGenerate" title="Générer les bulletins" separator>
        <x-form wire:submit="generateReportCards" class="space-y-4">
            <x-alert icon="o-information-circle" class="alert-info text-sm">
                Les bulletins seront calculés à partir des notes saisies et publiées. Les bulletins existants seront recalculés.
            </x-alert>
            <x-select label="Année scolaire *" wire:model="gen_yearId"
                      :options="$academicYears" option-value="id" option-label="name" required />
            <x-select label="Classe *" wire:model="gen_classId"
                      :options="$classes" option-value="id" option-label="name"
                      placeholder="Choisir une classe..." placeholder-value="0" required />
            <x-select label="Période *" wire:model="gen_period"
                      :options="$periods" option-value="id" option-label="name" required />
            <x-slot:actions>
                <x-button label="Annuler" @click="$wire.showGenerate = false" class="btn-ghost" />
                <x-button label="Générer" type="submit" icon="o-document-plus"
                          class="btn-primary" spinner="generateReportCards" />
            </x-slot:actions>
        </x-form>
    </x-modal>
</div>
