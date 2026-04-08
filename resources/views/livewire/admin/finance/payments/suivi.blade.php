<?php
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use App\Models\DmoneyTransaction;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    public string $tab         = 'dmoney';
    public string $search      = '';
    public string $dateFrom    = '';
    public string $dateTo      = '';
    public string $statusFilter = '';
    public string $methodFilter = '';

    public function updatingSearch(): void    { $this->resetPage(); }
    public function updatingDateFrom(): void  { $this->resetPage(); }
    public function updatingDateTo(): void    { $this->resetPage(); }
    public function updatingStatusFilter(): void { $this->resetPage(); }
    public function updatingMethodFilter(): void { $this->resetPage(); }
    public function switchTab(string $tab): void
    {
        $this->tab = $tab;
        $this->resetPage();
        $this->reset(['search', 'statusFilter', 'methodFilter']);
    }

    public function with(): array
    {
        $schoolId = auth()->user()->school_id;

        // ── Stats ───────────────────────────────────────────────────────────
        $dmoneyTotal     = DmoneyTransaction::where('school_id', $schoolId)->completed()->sum('amount');
        $dmoneyPending   = DmoneyTransaction::where('school_id', $schoolId)->pending()->count();
        $manualTotal     = Payment::where('school_id', $schoolId)->sum('amount');
        $todayTotal      = Payment::where('school_id', $schoolId)->whereDate('payment_date', today())->sum('amount')
                         + DmoneyTransaction::where('school_id', $schoolId)->completed()->whereDate('completed_at', today())->sum('amount');

        // ── D-Money transactions ─────────────────────────────────────────────
        $dmoneyQuery = DmoneyTransaction::where('school_id', $schoolId)
            ->with(['invoice.student', 'user'])
            ->latest();

        if ($this->search) {
            $dmoneyQuery->where(function ($q) {
                $q->where('order_id', 'like', "%{$this->search}%")
                  ->orWhereHas('invoice', fn($q2) => $q2->where('reference', 'like', "%{$this->search}%"))
                  ->orWhereHas('invoice.student', fn($q2) => $q2->where('name', 'like', "%{$this->search}%")
                      ->orWhere('reference', 'like', "%{$this->search}%"));
            });
        }
        if ($this->statusFilter) {
            $dmoneyQuery->where('status', $this->statusFilter);
        }
        if ($this->dateFrom) {
            $dmoneyQuery->whereDate('created_at', '>=', $this->dateFrom);
        }
        if ($this->dateTo) {
            $dmoneyQuery->whereDate('created_at', '<=', $this->dateTo);
        }

        // ── Manual payments ──────────────────────────────────────────────────
        $manualQuery = Payment::where('school_id', $schoolId)
            ->with(['student', 'receivedBy', 'paymentAllocations.invoice'])
            ->latest('payment_date');

        if ($this->search) {
            $manualQuery->where(function ($q) {
                $q->where('reference', 'like', "%{$this->search}%")
                  ->orWhereHas('student', fn($q2) => $q2->where('name', 'like', "%{$this->search}%")
                      ->orWhere('reference', 'like', "%{$this->search}%"));
            });
        }
        if ($this->methodFilter) {
            $manualQuery->where('payment_method', $this->methodFilter);
        }
        if ($this->dateFrom) {
            $manualQuery->whereDate('payment_date', '>=', $this->dateFrom);
        }
        if ($this->dateTo) {
            $manualQuery->whereDate('payment_date', '<=', $this->dateTo);
        }

        return [
            'dmoneyTotal'   => $dmoneyTotal,
            'dmoneyPending' => $dmoneyPending,
            'manualTotal'   => $manualTotal,
            'todayTotal'    => $todayTotal,
            'dmoneyTx'      => $this->tab === 'dmoney' ? $dmoneyQuery->paginate(20) : collect(),
            'manualPayments'=> $this->tab === 'manual' ? $manualQuery->paginate(20) : collect(),
        ];
    }
};
?>

<div class="p-4 lg:p-6 space-y-6">
    <x-header title="Suivi des paiements" subtitle="Encaissements D-Money et physiques" separator>
        <x-slot:actions>
            <x-button icon="o-arrow-path" wire:click="$refresh" class="btn-ghost btn-sm" tooltip="Actualiser" />
        </x-slot:actions>
    </x-header>

    {{-- Stats --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="stat bg-base-100 rounded-2xl shadow-sm border border-base-200 py-4">
            <div class="stat-figure text-success">
                <x-icon name="o-device-phone-mobile" class="w-8 h-8" />
            </div>
            <div class="stat-title text-xs">D-Money encaissé</div>
            <div class="stat-value text-success text-xl">{{ number_format($dmoneyTotal, 0, ',', ' ') }}</div>
            <div class="stat-desc">DJF — paiements confirmés</div>
        </div>
        <div class="stat bg-base-100 rounded-2xl shadow-sm border border-base-200 py-4">
            <div class="stat-figure text-warning">
                <x-icon name="o-clock" class="w-8 h-8" />
            </div>
            <div class="stat-title text-xs">D-Money en attente</div>
            <div class="stat-value text-warning text-xl">{{ $dmoneyPending }}</div>
            <div class="stat-desc">transactions non finalisées</div>
        </div>
        <div class="stat bg-base-100 rounded-2xl shadow-sm border border-base-200 py-4">
            <div class="stat-figure text-primary">
                <x-icon name="o-banknotes" class="w-8 h-8" />
            </div>
            <div class="stat-title text-xs">Paiements physiques</div>
            <div class="stat-value text-primary text-xl">{{ number_format($manualTotal, 0, ',', ' ') }}</div>
            <div class="stat-desc">DJF — caisse</div>
        </div>
        <div class="stat bg-base-100 rounded-2xl shadow-sm border border-base-200 py-4">
            <div class="stat-figure text-cyan-500">
                <x-icon name="o-calendar-days" class="w-8 h-8" />
            </div>
            <div class="stat-title text-xs">Encaissé aujourd'hui</div>
            <div class="stat-value text-cyan-600 text-xl">{{ number_format($todayTotal, 0, ',', ' ') }}</div>
            <div class="stat-desc">DJF — tous modes</div>
        </div>
    </div>

    {{-- Tabs --}}
    <div class="tabs tabs-boxed bg-base-200 w-fit">
        <button wire:click="switchTab('dmoney')" class="tab {{ $tab === 'dmoney' ? 'tab-active' : '' }}">
            <x-icon name="o-device-phone-mobile" class="w-4 h-4 mr-1" /> D-Money
        </button>
        <button wire:click="switchTab('manual')" class="tab {{ $tab === 'manual' ? 'tab-active' : '' }}">
            <x-icon name="o-banknotes" class="w-4 h-4 mr-1" /> Caisse physique
        </button>
    </div>

    {{-- Filters --}}
    <div class="flex flex-wrap gap-3 items-end">
        <x-input wire:model.live.debounce="search" placeholder="Référence, élève..." icon="o-magnifying-glass" class="input-sm w-64" />

        <div class="flex gap-2 items-center">
            <x-input type="date" wire:model.live="dateFrom" label="Du" class="input-sm" />
            <x-input type="date" wire:model.live="dateTo" label="Au" class="input-sm" />
        </div>

        @if($tab === 'dmoney')
        <x-select wire:model.live="statusFilter" placeholder="Tous les statuts" :options="[
            ['id' => '',          'name' => 'Tous les statuts'],
            ['id' => 'pending',   'name' => 'En attente'],
            ['id' => 'completed', 'name' => 'Confirmé'],
            ['id' => 'failed',    'name' => 'Échoué'],
            ['id' => 'cancelled', 'name' => 'Annulé'],
        ]" class="select-sm" />
        @else
        <x-select wire:model.live="methodFilter" placeholder="Tous les modes" :options="[
            ['id' => '',               'name' => 'Tous les modes'],
            ['id' => 'cash',           'name' => 'Espèces'],
            ['id' => 'bank_transfer',  'name' => 'Virement'],
            ['id' => 'check',          'name' => 'Chèque'],
            ['id' => 'mobile_money',   'name' => 'Mobile Money'],
        ]" class="select-sm" />
        @endif

        @if($search || $dateFrom || $dateTo || $statusFilter || $methodFilter)
        <x-button icon="o-x-mark" wire:click="$set('search', ''); $set('dateFrom', ''); $set('dateTo', ''); $set('statusFilter', ''); $set('methodFilter', '');" class="btn-ghost btn-sm" label="Effacer" />
        @endif
    </div>

    {{-- D-Money Tab --}}
    @if($tab === 'dmoney')
    <x-card shadow class="border-0 overflow-x-auto">
        @if($dmoneyTx->isEmpty())
        <div class="py-12 text-center text-base-content/40">
            <x-icon name="o-device-phone-mobile" class="w-12 h-12 mx-auto mb-3 opacity-30" />
            <p>Aucune transaction D-Money trouvée.</p>
        </div>
        @else
        <table class="table table-sm w-full">
            <thead>
                <tr class="text-xs text-base-content/60 uppercase">
                    <th>Date</th>
                    <th>Order ID</th>
                    <th>Élève</th>
                    <th>Facture</th>
                    <th class="text-right">Montant</th>
                    <th>Statut</th>
                    <th>Opérateur</th>
                </tr>
            </thead>
            <tbody>
                @foreach($dmoneyTx as $tx)
                <tr class="hover">
                    <td class="text-xs text-base-content/60">
                        {{ $tx->created_at->format('d/m/Y H:i') }}
                        @if($tx->completed_at)
                        <br><span class="text-success text-xs">✓ {{ $tx->completed_at->format('H:i') }}</span>
                        @endif
                    </td>
                    <td>
                        <span class="font-mono text-xs">{{ $tx->order_id }}</span>
                        @if($tx->webhook_payload['trade_no'] ?? null)
                        <br><span class="text-xs text-base-content/50">{{ $tx->webhook_payload['trade_no'] }}</span>
                        @endif
                    </td>
                    <td>
                        @if($tx->invoice?->student)
                        <span class="font-medium text-sm">{{ $tx->invoice->student->full_name }}</span>
                        <br><span class="text-xs text-base-content/50">{{ $tx->invoice->student->reference }}</span>
                        @else
                        <span class="text-base-content/40 text-xs">—</span>
                        @endif
                    </td>
                    <td>
                        @if($tx->invoice)
                        <span class="font-mono text-xs">{{ $tx->invoice->reference }}</span>
                        @else
                        <span class="text-base-content/40 text-xs">—</span>
                        @endif
                    </td>
                    <td class="text-right font-semibold">
                        {{ number_format($tx->amount, 0, ',', ' ') }} DJF
                    </td>
                    <td>
                        <x-badge :value="$tx->statusLabel()" :class="'badge-'.$tx->statusColor()" />
                    </td>
                    <td class="text-xs text-base-content/60">
                        {{ $tx->user?->name ?? ($tx->guardian_phone ?? '—') }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div class="mt-4">
            {{ $dmoneyTx->links() }}
        </div>
        @endif
    </x-card>
    @endif

    {{-- Manual payments Tab --}}
    @if($tab === 'manual')
    <x-card shadow class="border-0 overflow-x-auto">
        @if($manualPayments->isEmpty())
        <div class="py-12 text-center text-base-content/40">
            <x-icon name="o-banknotes" class="w-12 h-12 mx-auto mb-3 opacity-30" />
            <p>Aucun paiement physique trouvé.</p>
        </div>
        @else
        <table class="table table-sm w-full">
            <thead>
                <tr class="text-xs text-base-content/60 uppercase">
                    <th>Date</th>
                    <th>Référence</th>
                    <th>Élève</th>
                    <th>Facture(s)</th>
                    <th class="text-right">Montant</th>
                    <th>Mode</th>
                    <th>Encaissé par</th>
                    <th>Preuve</th>
                </tr>
            </thead>
            <tbody>
                @foreach($manualPayments as $pay)
                <tr class="hover">
                    <td class="text-xs text-base-content/60">{{ $pay->payment_date?->format('d/m/Y') }}</td>
                    <td><span class="font-mono text-xs">{{ $pay->reference }}</span></td>
                    <td>
                        @if($pay->student)
                        <span class="font-medium text-sm">{{ $pay->student->full_name }}</span>
                        <br><span class="text-xs text-base-content/50">{{ $pay->student->reference }}</span>
                        @else
                        <span class="text-base-content/40 text-xs">—</span>
                        @endif
                    </td>
                    <td class="text-xs">
                        @foreach($pay->paymentAllocations as $alloc)
                            @if($alloc->invoice)
                            <span class="font-mono">{{ $alloc->invoice->reference }}</span>
                            ({{ number_format($alloc->amount, 0, ',', ' ') }} DJF)<br>
                            @endif
                        @endforeach
                    </td>
                    <td class="text-right font-semibold">
                        {{ number_format($pay->amount, 0, ',', ' ') }} DJF
                    </td>
                    <td>
                        @php
                            $methodLabel = match($pay->payment_method) {
                                'cash'          => 'Espèces',
                                'bank_transfer' => 'Virement',
                                'check'         => 'Chèque',
                                'mobile_money'  => 'Mobile Money',
                                default         => $pay->payment_method,
                            };
                        @endphp
                        <x-badge :value="$methodLabel" class="badge-ghost badge-sm" />
                    </td>
                    <td class="text-xs text-base-content/60">{{ $pay->receivedBy?->name ?? '—' }}</td>
                    <td>
                        @if($pay->meta['proof_screenshot'] ?? null)
                        <a href="{{ Storage::url($pay->meta['proof_screenshot']) }}" target="_blank"
                           class="btn btn-xs btn-ghost text-primary" title="Voir la preuve">
                            <x-icon name="o-photo" class="w-4 h-4" />
                        </a>
                        @else
                        <span class="text-base-content/30 text-xs">—</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div class="mt-4">
            {{ $manualPayments->links() }}
        </div>
        @endif
    </x-card>
    @endif
</div>
