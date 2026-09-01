<?php
use App\Models\Invoice;
use App\Models\SchoolClass;
use App\Models\Grade;
use App\Models\AcademicYear;
use App\Enums\FeeScheduleType;
use App\Enums\InvoiceStatus;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')] class extends Component {

    public ?int   $yearId        = null;
    public ?int   $gradeId       = null;   // niveau
    public ?int   $classId       = null;   // classe (dependent on niveau)
    public string $studentSearch = '';     // nom / n° élève
    public string $scheduleType  = '';     // monthly | bimonthly | quarterly | yearly
    public ?int   $installment   = null;   // échéance (dependent on scheduleType)
    public string $status        = '';     // reason unpaid: issued | partially_paid | overdue | paid

    public function mount(): void
    {
        $this->yearId = AcademicYear::where('school_id', auth()->user()->school_id)
            ->where('is_current', true)->value('id');
    }

    /** Reset the class when the niveau changes (dependent dropdown). */
    public function updatedGradeId(): void { $this->classId = null; }

    /** Reset the installment when the payment mode changes (dependent dropdown). */
    public function updatedScheduleType(): void { $this->installment = null; }

    public function resetFilters(): void
    {
        $this->reset(['gradeId', 'classId', 'studentSearch', 'scheduleType', 'installment', 'status']);
    }

    private function baseQuery()
    {
        $schoolId = auth()->user()->school_id;
        $search   = trim($this->studentSearch);

        return Invoice::where('school_id', $schoolId)
            ->when($this->yearId,       fn ($q) => $q->where('academic_year_id', $this->yearId))
            ->when($this->scheduleType, fn ($q) => $q->where('schedule_type', $this->scheduleType))
            ->when($this->installment,  fn ($q) => $q->where('installment_number', $this->installment))
            ->when($this->status,       fn ($q) => $q->where('status', $this->status))
            ->when($this->gradeId || $this->classId, fn ($q) => $q->whereHas('enrollment', fn ($e) => $e
                ->when($this->classId, fn ($x) => $x->where('school_class_id', $this->classId))
                ->when($this->gradeId && ! $this->classId, fn ($x) => $x->where('grade_id', $this->gradeId))))
            ->when(strlen($search) >= 2, fn ($q) => $q->whereHas('student', fn ($s) => $s
                ->where('name', 'like', "%{$search}%")->orWhere('reference', 'like', "%{$search}%")));
    }

    public function with(): array
    {
        $schoolId = auth()->user()->school_id;

        $rows = $this->baseQuery()
            ->with(['student', 'enrollment.schoolClass.grade', 'academicYear'])
            ->orderBy('due_date')
            ->limit(500)
            ->get();

        // Installment options depend on the chosen payment mode
        $installmentCount = $this->scheduleType
            ? (FeeScheduleType::tryFrom($this->scheduleType)?->installments() ?? 0)
            : 0;
        $installmentOptions = collect(range(1, max(1, $installmentCount)))
            ->map(fn ($n) => ['id' => $n, 'name' => "Échéance {$n}"])->all();

        return [
            'rows'    => $rows,
            'totals'  => (object) [
                'count'   => $rows->count(),
                'billed'  => (float) $rows->sum('total'),
                'paid'    => (float) $rows->sum('paid_total'),
                'balance' => (float) $rows->sum('balance_due'),
            ],
            'years'   => AcademicYear::where('school_id', $schoolId)->orderByDesc('start_date')->get()
                ->map(fn ($y) => ['id' => $y->id, 'name' => $y->name])->all(),
            'grades'  => Grade::where('school_id', $schoolId)->where('is_active', true)->orderBy('order')->get()
                ->map(fn ($g) => ['id' => $g->id, 'name' => $g->name])->all(),
            'classes' => SchoolClass::where('school_id', $schoolId)->where('is_active', true)
                ->when($this->gradeId, fn ($q) => $q->where('grade_id', $this->gradeId))
                ->orderBy('name')->get()
                ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->all(),
            'scheduleTypes' => collect(FeeScheduleType::cases())
                ->map(fn ($t) => ['id' => $t->value, 'name' => $t->label()])->all(),
            'installmentOptions' => $installmentOptions,
            'statuses' => [
                ['id' => 'issued',         'name' => 'Émise (impayée)'],
                ['id' => 'partially_paid', 'name' => 'Partiellement payée'],
                ['id' => 'overdue',        'name' => 'En retard'],
                ['id' => 'paid',           'name' => 'Payée'],
            ],
        ];
    }
};
?>

<div>
    <x-header title="Rapport par classe" subtitle="Suivi des paiements et impayés par niveau, classe et échéance" separator progress-indicator>
        <x-slot:actions>
            <x-button label="Retour aux rapports" icon="o-arrow-left" :link="route('admin.reports.index')" wire:navigate class="btn-ghost btn-sm" />
        </x-slot:actions>
    </x-header>

    {{-- Dependent optional filters --}}
    <div class="bg-base-200/50 rounded-2xl p-4 mb-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <x-select label="Année scolaire" wire:model.live="yearId" :options="$years"
                      placeholder="Toutes" placeholder-value="" />
            <x-select label="Niveau" wire:model.live="gradeId" :options="$grades"
                      placeholder="Tous les niveaux" placeholder-value="" icon="o-academic-cap" />
            <x-select label="Classe" wire:model.live="classId" :options="$classes"
                      placeholder="{{ $gradeId ? 'Toutes les classes du niveau' : 'Toutes les classes' }}"
                      placeholder-value="" icon="o-building-office" />
            <x-input label="Nom / n° élève" wire:model.live.debounce.400ms="studentSearch"
                     placeholder="Rechercher…" icon="o-magnifying-glass" clearable />
            <x-select label="Mode de paiement" wire:model.live="scheduleType" :options="$scheduleTypes"
                      placeholder="Tous les modes" placeholder-value="" icon="o-calendar" />
            <x-select label="Échéance" wire:model.live="installment" :options="$installmentOptions"
                      placeholder="{{ $scheduleType ? 'Toutes les échéances' : 'Choisir un mode d\'abord' }}"
                      placeholder-value="" :disabled="! $scheduleType" icon="o-flag" />
            <x-select label="Motif / statut" wire:model.live="status" :options="$statuses"
                      placeholder="Tous les statuts" placeholder-value="" icon="o-funnel" />
            <div class="flex items-end">
                <x-button label="Réinitialiser" icon="o-x-mark" wire:click="resetFilters" class="btn-ghost btn-sm w-full" />
            </div>
        </div>
    </div>

    {{-- Totals --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="rounded-2xl bg-base-200 p-4">
            <p class="text-xs text-base-content/60 font-semibold uppercase tracking-wide">Factures</p>
            <p class="text-2xl font-black mt-1">{{ $totals->count }}</p>
        </div>
        <div class="rounded-2xl bg-gradient-to-br from-info to-info/60 p-4 text-info-content">
            <p class="text-xs opacity-70 font-semibold uppercase tracking-wide">Facturé</p>
            <p class="text-2xl font-black mt-1">{{ number_format($totals->billed, 0, ',', ' ') }}</p>
            <p class="text-xs opacity-60">DJF</p>
        </div>
        <div class="rounded-2xl bg-gradient-to-br from-success to-success/60 p-4 text-success-content">
            <p class="text-xs opacity-70 font-semibold uppercase tracking-wide">Payé</p>
            <p class="text-2xl font-black mt-1">{{ number_format($totals->paid, 0, ',', ' ') }}</p>
            <p class="text-xs opacity-60">DJF</p>
        </div>
        <div class="rounded-2xl bg-gradient-to-br from-warning to-warning/60 p-4 text-warning-content">
            <p class="text-xs opacity-70 font-semibold uppercase tracking-wide">Reste dû</p>
            <p class="text-2xl font-black mt-1">{{ number_format($totals->balance, 0, ',', ' ') }}</p>
            <p class="text-xs opacity-60">DJF</p>
        </div>
    </div>

    {{-- Results table --}}
    <x-card>
        <div class="overflow-x-auto">
            <table class="table table-sm w-full">
                <thead><tr>
                    <th>Élève</th>
                    <th>Classe</th>
                    <th>Facture</th>
                    <th>Mode</th>
                    <th class="text-center">Éch.</th>
                    <th>Échéance</th>
                    <th class="text-right">Total</th>
                    <th class="text-right">Payé</th>
                    <th class="text-right">Reste</th>
                    <th>Statut</th>
                    <th></th>
                </tr></thead>
                <tbody>
                @forelse($rows as $inv)
                @php
                    $statusClass = match($inv->status?->value) {
                        'paid'           => 'badge-success',
                        'partially_paid' => 'badge-warning',
                        'overdue'        => 'badge-error',
                        'issued'         => 'badge-info',
                        'cancelled'      => 'badge-ghost',
                        default          => 'badge-ghost',
                    };
                    $overdue = $inv->balance_due > 0 && $inv->due_date?->isPast();
                @endphp
                <tr class="hover">
                    <td class="font-semibold text-sm">{{ $inv->student?->full_name ?? '—' }}</td>
                    <td class="text-sm text-base-content/60">{{ $inv->enrollment?->schoolClass?->name ?? '—' }}</td>
                    <td class="font-mono text-xs">{{ $inv->reference }}</td>
                    <td class="text-sm">{{ $inv->schedule_type?->label() ?? '—' }}</td>
                    <td class="text-center text-sm">{{ $inv->installment_number ? '#'.$inv->installment_number : '—' }}</td>
                    <td class="text-sm {{ $overdue ? 'text-error font-semibold' : 'text-base-content/60' }}">
                        {{ $inv->due_date?->format('d/m/Y') ?? '—' }}{{ $overdue ? ' ⚠' : '' }}
                    </td>
                    <td class="text-right text-sm">{{ number_format((int)$inv->total, 0, ',', ' ') }}</td>
                    <td class="text-right text-sm text-success">{{ number_format((int)$inv->paid_total, 0, ',', ' ') }}</td>
                    <td class="text-right text-sm font-bold {{ $inv->balance_due > 0 ? 'text-warning' : 'text-success' }}">
                        {{ number_format((int)$inv->balance_due, 0, ',', ' ') }}
                    </td>
                    <td><x-badge :value="$inv->status?->label()" class="{{ $statusClass }} badge-sm" /></td>
                    <td>
                        <a href="{{ route('admin.finance.invoices.show', $inv->uuid) }}" wire:navigate class="btn btn-ghost btn-xs">
                            <x-icon name="o-eye" class="w-3.5 h-3.5" />
                        </a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="11" class="text-center text-base-content/40 py-10">Aucune facture pour ces critères.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</div>
