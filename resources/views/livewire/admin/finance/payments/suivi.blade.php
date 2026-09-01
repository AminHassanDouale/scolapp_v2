<?php
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use App\Models\DmoneyTransaction;
use App\Models\Payment;
use App\Models\Invoice;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\AcademicYear;
use App\Exports\ArrayExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    public string $tab           = 'manual';   // manual (caisse physique) | dmoney (mobile money)
    public ?int   $yearId        = null;
    public ?int   $gradeId       = null;       // niveau (dependent -> classe)
    public ?int   $classId       = null;
    public string $search        = '';
    public string $dateFrom      = '';
    public string $dateTo        = '';
    public string $statusFilter  = '';         // dmoney status
    public string $methodFilter  = '';         // manual payment method
    public string $scheduleType  = '';         // monthly | bimonthly | quarterly | yearly
    public array  $installments  = [];         // échéances (multiple)

    public function mount(): void
    {
        $this->yearId = AcademicYear::where('school_id', auth()->user()->school_id)
            ->where('is_current', true)->value('id');
    }

    // Dependent dropdown: reset class when niveau changes
    public function updatedGradeId(): void   { $this->classId = null; $this->resetPage(); }
    public function updatedClassId(): void   { $this->resetPage(); }
    public function updatedYearId(): void    { $this->resetPage(); }
    public function updatedSearch(): void    { $this->resetPage(); }
    public function updatedDateFrom(): void  { $this->resetPage(); }
    public function updatedDateTo(): void    { $this->resetPage(); }
    public function updatedStatusFilter(): void { $this->resetPage(); }
    public function updatedMethodFilter(): void { $this->resetPage(); }
    public function updatedScheduleType(): void { $this->installments = []; $this->resetPage(); }

    public function switchTab(string $tab): void
    {
        $this->tab = $tab;
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['gradeId', 'classId', 'search', 'dateFrom', 'dateTo', 'statusFilter', 'methodFilter', 'scheduleType', 'installments']);
        $this->resetPage();
    }

    /** Filters that apply to the linked invoice (class / grade / mode / échéance). */
    private function invoiceConstraint($iq): void
    {
        $iq->when($this->yearId,       fn ($q) => $q->where('academic_year_id', $this->yearId))
           ->when($this->scheduleType, fn ($q) => $q->where('schedule_type', $this->scheduleType))
           ->when(! empty($this->installments), fn ($q) => $q->whereIn('installment_number', $this->installments))
           ->when($this->gradeId || $this->classId, fn ($q) => $q->whereHas('enrollment', fn ($e) => $e
               ->when($this->classId, fn ($x) => $x->where('school_class_id', $this->classId))
               ->when($this->gradeId && ! $this->classId, fn ($x) => $x->where('grade_id', $this->gradeId))));
    }

    private function dmoneyQuery()
    {
        $schoolId = auth()->user()->school_id;

        return DmoneyTransaction::where('school_id', $schoolId)
            ->with(['invoice.student', 'invoice.enrollment.schoolClass', 'user'])
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w
                ->where('order_id', 'like', "%{$this->search}%")
                ->orWhereHas('invoice', fn ($iq) => $iq->where('reference', 'like', "%{$this->search}%"))
                ->orWhereHas('invoice.student', fn ($sq) => $sq->where(fn ($s) => $s
                    ->where('name', 'like', "%{$this->search}%")
                    ->orWhere('reference', 'like', "%{$this->search}%")))))
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo,   fn ($q) => $q->whereDate('created_at', '<=', $this->dateTo))
            ->when($this->yearId || $this->gradeId || $this->classId || $this->scheduleType || ! empty($this->installments),
                fn ($q) => $q->whereHas('invoice', fn ($iq) => $this->invoiceConstraint($iq)))
            ->latest();
    }

    private function manualQuery()
    {
        $schoolId = auth()->user()->school_id;

        return Payment::where('school_id', $schoolId)
            ->with(['student', 'receivedBy', 'paymentAllocations.invoice.enrollment.schoolClass'])
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w
                ->where('reference', 'like', "%{$this->search}%")
                ->orWhereHas('student', fn ($sq) => $sq->where(fn ($s) => $s
                    ->where('name', 'like', "%{$this->search}%")
                    ->orWhere('reference', 'like', "%{$this->search}%")))))
            ->when($this->methodFilter, fn ($q) => $q->where('payment_method', $this->methodFilter))
            ->when($this->dateFrom, fn ($q) => $q->whereDate('payment_date', '>=', $this->dateFrom))
            ->when($this->dateTo,   fn ($q) => $q->whereDate('payment_date', '<=', $this->dateTo))
            ->when($this->yearId || $this->gradeId || $this->classId || $this->scheduleType || ! empty($this->installments),
                fn ($q) => $q->whereHas('paymentAllocations.invoice', fn ($iq) => $this->invoiceConstraint($iq)))
            ->latest('payment_date');
    }

    // ── Exports ──────────────────────────────────────────────────────────────
    public function exportManual()
    {
        $rows = $this->manualQuery()->get()->map(fn (Payment $p) => [
            $p->payment_date?->format('d/m/Y'),
            $p->reference,
            $p->student?->full_name,
            $p->student?->reference,
            $p->paymentAllocations->map(fn ($a) => $a->invoice?->reference)->filter()->implode(', '),
            (int) $p->amount,
            match ($p->payment_method) { 'cash' => 'Espèces', 'bank_transfer' => 'Virement', 'check' => 'Chèque', 'mobile_money' => 'Mobile Money', default => $p->payment_method },
            $p->receivedBy?->name,
        ])->all();

        return Excel::download(new ArrayExport(
            ['Date', 'Référence', 'Élève', 'Code élève', 'Facture(s)', 'Montant (DJF)', 'Mode', 'Encaissé par'],
            $rows, 'Caisse physique'
        ), 'caisse-physique-' . now()->format('Y-m-d') . '.xlsx');
    }

    public function exportDmoney()
    {
        $rows = $this->dmoneyQuery()->get()->map(fn (DmoneyTransaction $t) => [
            $t->created_at?->format('d/m/Y H:i'),
            $t->order_id,
            $t->invoice?->student?->full_name,
            $t->invoice?->reference,
            (int) $t->amount,
            $t->statusLabel(),
            $t->completed_at?->format('d/m/Y H:i'),
        ])->all();

        return Excel::download(new ArrayExport(
            ['Date', 'Order ID', 'Élève', 'Facture', 'Montant (DJF)', 'Statut', 'Confirmé le'],
            $rows, 'Mobile Money'
        ), 'mobile-money-' . now()->format('Y-m-d') . '.xlsx');
    }

    public function with(): array
    {
        $schoolId = auth()->user()->school_id;

        // Stats
        $dmoneyTotal   = DmoneyTransaction::where('school_id', $schoolId)->completed()->sum('amount');
        $dmoneyPending = DmoneyTransaction::where('school_id', $schoolId)->pending()->count();
        $manualTotal   = Payment::where('school_id', $schoolId)->sum('amount');
        $todayTotal    = Payment::where('school_id', $schoolId)->whereDate('payment_date', today())->sum('amount')
                       + DmoneyTransaction::where('school_id', $schoolId)->completed()->whereDate('completed_at', today())->sum('amount');

        // ── Predictions: expected vs collected (invoices for selected scope) ──
        $invBase = Invoice::where('school_id', $schoolId)
            ->whereNotIn('status', ['cancelled'])
            ->when($this->yearId,       fn ($q) => $q->where('academic_year_id', $this->yearId))
            ->when($this->scheduleType, fn ($q) => $q->where('schedule_type', $this->scheduleType))
            ->when(! empty($this->installments), fn ($q) => $q->whereIn('installment_number', $this->installments))
            ->when($this->gradeId || $this->classId, fn ($q) => $q->whereHas('enrollment', fn ($e) => $e
                ->when($this->classId, fn ($x) => $x->where('school_class_id', $this->classId))
                ->when($this->gradeId && ! $this->classId, fn ($x) => $x->where('grade_id', $this->gradeId))));

        $byClass = (clone $invBase)->with('enrollment.schoolClass')->get()
            ->groupBy(fn ($i) => $i->enrollment?->schoolClass?->name ?? '—')
            ->map(fn ($g, $name) => (object) [
                'name'      => $name,
                'expected'  => (float) $g->sum('total'),
                'collected' => (float) $g->sum('paid_total'),
            ])
            ->sortByDesc('expected')->values();

        $byInstallment = (clone $invBase)->get()
            ->groupBy(fn ($i) => $i->installment_number ?: 0)
            ->map(fn ($g, $n) => (object) [
                'label'     => $n ? "Échéance {$n}" : 'Sans échéance',
                'expected'  => (float) $g->sum('total'),
                'collected' => (float) $g->sum('paid_total'),
            ])
            ->sortKeys()->values();

        $expectedTotal  = (float) (clone $invBase)->sum('total');
        $collectedTotal = (float) (clone $invBase)->sum('paid_total');

        return [
            'dmoneyTotal'    => $dmoneyTotal,
            'dmoneyPending'  => $dmoneyPending,
            'manualTotal'    => $manualTotal,
            'todayTotal'     => $todayTotal,
            'dmoneyTx'       => $this->tab === 'dmoney' ? $this->dmoneyQuery()->paginate(20, ['*'], 'dmoneyPage') : collect(),
            'manualPayments' => $this->tab === 'manual' ? $this->manualQuery()->paginate(20, ['*'], 'manualPage') : collect(),
            // filter option lists
            'years'   => AcademicYear::where('school_id', $schoolId)->orderByDesc('start_date')->get()
                ->map(fn ($y) => ['id' => $y->id, 'name' => $y->name])->all(),
            'grades'  => Grade::where('school_id', $schoolId)->where('is_active', true)->orderBy('order')->get()
                ->map(fn ($g) => ['id' => $g->id, 'name' => $g->name])->all(),
            'classes' => SchoolClass::where('school_id', $schoolId)->where('is_active', true)
                ->when($this->gradeId, fn ($q) => $q->where('grade_id', $this->gradeId))
                ->orderBy('name')->get()->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->all(),
            'scheduleTypes' => [
                ['id' => 'monthly',   'name' => 'Mensuel'],
                ['id' => 'bimonthly', 'name' => 'Bimestriel'],
                ['id' => 'quarterly', 'name' => 'Trimestriel'],
                ['id' => 'yearly',    'name' => 'Annuel'],
            ],
            'installmentOptions' => collect(range(1, 10))->map(fn ($n) => ['id' => $n, 'name' => "Échéance {$n}"])->all(),
            // predictions
            'byClass'        => $byClass,
            'byInstallment'  => $byInstallment,
            'expectedTotal'  => $expectedTotal,
            'collectedTotal' => $collectedTotal,
        ];
    }
};
?>

<div class="p-4 lg:p-6 space-y-6">
    <x-header title="Suivi des encaissements" subtitle="Mobile Money & caisse physique — prévisions et recouvrement" separator>
        <x-slot:actions>
            <x-button icon="o-arrow-path" wire:click="$refresh" class="btn-ghost btn-sm" tooltip="Actualiser" />
        </x-slot:actions>
    </x-header>

    {{-- Stats --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="stat bg-base-100 rounded-2xl shadow-sm border border-base-200 py-4">
            <div class="stat-figure text-success"><x-icon name="o-device-phone-mobile" class="w-8 h-8" /></div>
            <div class="stat-title text-xs">Mobile Money encaissé</div>
            <div class="stat-value text-success text-xl">{{ number_format($dmoneyTotal, 0, ',', ' ') }}</div>
            <div class="stat-desc">DJF — confirmés</div>
        </div>
        <div class="stat bg-base-100 rounded-2xl shadow-sm border border-base-200 py-4">
            <div class="stat-figure text-warning"><x-icon name="o-clock" class="w-8 h-8" /></div>
            <div class="stat-title text-xs">Mobile Money en attente</div>
            <div class="stat-value text-warning text-xl">{{ $dmoneyPending }}</div>
            <div class="stat-desc">transactions</div>
        </div>
        <div class="stat bg-base-100 rounded-2xl shadow-sm border border-base-200 py-4">
            <div class="stat-figure text-primary"><x-icon name="o-banknotes" class="w-8 h-8" /></div>
            <div class="stat-title text-xs">Caisse physique</div>
            <div class="stat-value text-primary text-xl">{{ number_format($manualTotal, 0, ',', ' ') }}</div>
            <div class="stat-desc">DJF</div>
        </div>
        <div class="stat bg-base-100 rounded-2xl shadow-sm border border-base-200 py-4">
            <div class="stat-figure text-cyan-500"><x-icon name="o-calendar-days" class="w-8 h-8" /></div>
            <div class="stat-title text-xs">Encaissé aujourd'hui</div>
            <div class="stat-value text-cyan-600 text-xl">{{ number_format($todayTotal, 0, ',', ' ') }}</div>
            <div class="stat-desc">DJF — tous modes</div>
        </div>
    </div>

    {{-- Dependent filters --}}
    <div class="bg-base-200/50 rounded-2xl p-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <x-select label="Année" wire:model.live="yearId" :options="$years" placeholder="Toutes" placeholder-value="" />
            <x-select label="Niveau" wire:model.live="gradeId" :options="$grades" placeholder="Tous" placeholder-value="" icon="o-academic-cap" />
            <x-select label="Classe" wire:model.live="classId" :options="$classes"
                      placeholder="{{ $gradeId ? 'Toutes du niveau' : 'Toutes' }}" placeholder-value="" icon="o-building-office" />
            <x-input label="Élève" wire:model.live.debounce.400ms="search" placeholder="Nom, n°, réf…" icon="o-magnifying-glass" clearable />
            <x-select label="Mode de paiement (barème)" wire:model.live="scheduleType" :options="$scheduleTypes" placeholder="Tous" placeholder-value="" icon="o-calendar" />
            <x-select label="Échéances" wire:model.live="installments" :options="$installmentOptions" multiple
                      placeholder="{{ $scheduleType ? 'Toutes' : 'Choisir un mode' }}" :disabled="! $scheduleType" icon="o-flag" />
            <x-input type="date" label="Du" wire:model.live="dateFrom" />
            <x-input type="date" label="Au" wire:model.live="dateTo" />
        </div>
        <div class="flex justify-end mt-3">
            <x-button label="Réinitialiser les filtres" icon="o-x-mark" wire:click="resetFilters" class="btn-ghost btn-sm" />
        </div>
    </div>

    {{-- Predictions: expected vs collected --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <x-card title="Prévision par classe (attendu vs encaissé)" shadow separator>
            <div class="mb-3 flex items-center justify-between text-sm">
                <span>Attendu : <b class="text-info">{{ number_format($expectedTotal, 0, ',', ' ') }} DJF</b></span>
                <span>Encaissé : <b class="text-success">{{ number_format($collectedTotal, 0, ',', ' ') }} DJF</b></span>
            </div>
            <div class="space-y-3 max-h-72 overflow-y-auto pr-1">
                @forelse($byClass as $c)
                @php $pct = $c->expected > 0 ? min(100, round($c->collected / $c->expected * 100)) : 0; @endphp
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span class="font-semibold">{{ $c->name }}</span>
                        <span class="text-base-content/60">{{ $pct }}% · {{ number_format($c->collected, 0, ',', ' ') }} / {{ number_format($c->expected, 0, ',', ' ') }}</span>
                    </div>
                    <div class="w-full bg-base-200 rounded-full h-2">
                        <div class="{{ $pct >= 80 ? 'bg-success' : ($pct >= 50 ? 'bg-warning' : 'bg-error') }} h-2 rounded-full" style="width:{{ $pct }}%"></div>
                    </div>
                </div>
                @empty
                <p class="text-sm text-base-content/40 text-center py-6">Aucune donnée pour ces filtres.</p>
                @endforelse
            </div>
        </x-card>

        <x-card title="Prévision par échéance (attendu vs encaissé)" shadow separator>
            <div class="space-y-3 max-h-72 overflow-y-auto pr-1 mt-1">
                @forelse($byInstallment as $e)
                @php $pct = $e->expected > 0 ? min(100, round($e->collected / $e->expected * 100)) : 0; @endphp
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span class="font-semibold">{{ $e->label }}</span>
                        <span class="text-base-content/60">{{ $pct }}% · {{ number_format($e->collected, 0, ',', ' ') }} / {{ number_format($e->expected, 0, ',', ' ') }}</span>
                    </div>
                    <div class="w-full bg-base-200 rounded-full h-2">
                        <div class="{{ $pct >= 80 ? 'bg-success' : ($pct >= 50 ? 'bg-warning' : 'bg-error') }} h-2 rounded-full" style="width:{{ $pct }}%"></div>
                    </div>
                </div>
                @empty
                <p class="text-sm text-base-content/40 text-center py-6">Aucune donnée pour ces filtres.</p>
                @endforelse
            </div>
        </x-card>
    </div>

    {{-- Tabs + export --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="tabs tabs-boxed bg-base-200 w-fit">
            <button wire:click="switchTab('manual')" class="tab {{ $tab === 'manual' ? 'tab-active' : '' }}">
                <x-icon name="o-banknotes" class="w-4 h-4 mr-1" /> Caisse physique
            </button>
            <button wire:click="switchTab('dmoney')" class="tab {{ $tab === 'dmoney' ? 'tab-active' : '' }}">
                <x-icon name="o-device-phone-mobile" class="w-4 h-4 mr-1" /> Mobile Money
            </button>
        </div>
        @if($tab === 'manual')
        <x-button label="Exporter Excel" icon="o-arrow-down-tray" wire:click="exportManual" spinner="exportManual" class="btn-success btn-sm" />
        @else
        <x-button label="Exporter Excel" icon="o-arrow-down-tray" wire:click="exportDmoney" spinner="exportDmoney" class="btn-success btn-sm" />
        @endif
    </div>

    {{-- Manual (caisse physique) --}}
    @if($tab === 'manual')
    <x-card shadow class="border-0 overflow-x-auto">
        @if($manualPayments->isEmpty())
        <div class="py-12 text-center text-base-content/40">
            <x-icon name="o-banknotes" class="w-12 h-12 mx-auto mb-3 opacity-30" />
            <p>Aucun paiement pour ces critères.</p>
        </div>
        @else
        <table class="table table-sm w-full">
            <thead><tr class="text-xs text-base-content/60 uppercase">
                <th>Date</th><th>Référence</th><th>Élève</th><th>Classe</th><th>Facture(s)</th>
                <th class="text-right">Montant</th><th>Mode</th><th>Encaissé par</th><th>Preuve</th>
            </tr></thead>
            <tbody>
                @foreach($manualPayments as $pay)
                <tr class="hover">
                    <td class="text-xs text-base-content/60">{{ $pay->payment_date?->format('d/m/Y') }}</td>
                    <td><span class="font-mono text-xs">{{ $pay->reference }}</span></td>
                    <td>
                        @if($pay->student)
                        <span class="font-medium text-sm">{{ $pay->student->full_name }}</span>
                        <br><span class="text-xs text-base-content/50">{{ $pay->student->reference }}</span>
                        @else <span class="text-base-content/40 text-xs">—</span> @endif
                    </td>
                    <td class="text-xs text-base-content/60">{{ $pay->paymentAllocations->first()?->invoice?->enrollment?->schoolClass?->name ?? '—' }}</td>
                    <td class="text-xs">
                        @foreach($pay->paymentAllocations as $alloc)
                            @if($alloc->invoice)<span class="font-mono">{{ $alloc->invoice->reference }}</span> ({{ number_format($alloc->amount, 0, ',', ' ') }})<br>@endif
                        @endforeach
                    </td>
                    <td class="text-right font-semibold">{{ number_format($pay->amount, 0, ',', ' ') }} DJF</td>
                    <td>
                        @php $methodLabel = match($pay->payment_method) { 'cash' => 'Espèces', 'bank_transfer' => 'Virement', 'check' => 'Chèque', 'mobile_money' => 'Mobile Money', default => $pay->payment_method }; @endphp
                        <x-badge :value="$methodLabel" class="badge-ghost badge-sm" />
                    </td>
                    <td class="text-xs text-base-content/60">{{ $pay->receivedBy?->name ?? '—' }}</td>
                    <td>
                        @if($pay->meta['proof_screenshot'] ?? null)
                        <a href="{{ Storage::url($pay->meta['proof_screenshot']) }}" target="_blank" class="btn btn-xs btn-ghost text-primary"><x-icon name="o-photo" class="w-4 h-4" /></a>
                        @else <span class="text-base-content/30 text-xs">—</span> @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div class="mt-4">{{ $manualPayments->links() }}</div>
        @endif
    </x-card>
    @endif

    {{-- Mobile Money (D-Money gateway) --}}
    @if($tab === 'dmoney')
    <x-card shadow class="border-0 overflow-x-auto">
        @if($dmoneyTx->isEmpty())
        <div class="py-12 text-center text-base-content/40">
            <x-icon name="o-device-phone-mobile" class="w-12 h-12 mx-auto mb-3 opacity-30" />
            <p>Aucune transaction pour ces critères.</p>
        </div>
        @else
        <table class="table table-sm w-full">
            <thead><tr class="text-xs text-base-content/60 uppercase">
                <th>Date</th><th>Order ID</th><th>Élève</th><th>Classe</th><th>Facture</th>
                <th class="text-right">Montant</th><th>Statut</th>
            </tr></thead>
            <tbody>
                @foreach($dmoneyTx as $tx)
                <tr class="hover">
                    <td class="text-xs text-base-content/60">
                        {{ $tx->created_at?->format('d/m/Y H:i') }}
                        @if($tx->completed_at)<br><span class="text-success text-xs">✓ {{ $tx->completed_at->format('H:i') }}</span>@endif
                    </td>
                    <td><span class="font-mono text-xs">{{ $tx->order_id }}</span></td>
                    <td>
                        @if($tx->invoice?->student)
                        <span class="font-medium text-sm">{{ $tx->invoice->student->full_name }}</span>
                        <br><span class="text-xs text-base-content/50">{{ $tx->invoice->student->reference }}</span>
                        @else <span class="text-base-content/40 text-xs">—</span> @endif
                    </td>
                    <td class="text-xs text-base-content/60">{{ $tx->invoice?->enrollment?->schoolClass?->name ?? '—' }}</td>
                    <td>@if($tx->invoice)<span class="font-mono text-xs">{{ $tx->invoice->reference }}</span>@else<span class="text-base-content/40 text-xs">—</span>@endif</td>
                    <td class="text-right font-semibold">{{ number_format($tx->amount, 0, ',', ' ') }} DJF</td>
                    <td><x-badge :value="$tx->statusLabel()" :class="'badge-'.$tx->statusColor()" /></td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div class="mt-4">{{ $dmoneyTx->links() }}</div>
        @endif
    </x-card>
    @endif
</div>
