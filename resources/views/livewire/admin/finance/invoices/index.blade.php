<?php
use App\Models\Invoice;
use App\Models\AcademicYear;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Mary\Traits\Toast;
use Illuminate\Support\Facades\DB;

new #[Layout('layouts.app')] class extends Component {
    use Toast, WithPagination;

    public string $search      = '';
    public string $statusFilter = '';
    public string $typeFilter   = '';
    public int    $yearFilter   = 0;
    public bool   $showFilters  = false;
    public bool   $showExport   = false;
    public array  $selected     = [];
    public bool   $selectAll    = false;
    public string $sortBy       = 'created_at';
    public string $sortDir      = 'desc';
    public int    $perPage      = 20;

    // Export form
    public string $exportFormat = 'xlsx';
    public string $exportScope  = 'filtered';

    public function updatingSearch(): void    { $this->resetPage(); }
    public function updatingStatusFilter(): void { $this->resetPage(); }
    public function updatingYearFilter(): void   { $this->resetPage(); }

    public function sortBy(string $col): void
    {
        if ($this->sortBy === $col) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy  = $col;
            $this->sortDir = 'asc';
        }
    }

    public function toggleSelect(int $id): void
    {
        if (in_array($id, $this->selected)) {
            $this->selected = array_values(array_filter($this->selected, fn($v) => $v !== $id));
        } else {
            $this->selected[] = $id;
        }
    }

    public function toggleSelectAll(): void
    {
        if ($this->selectAll) {
            $this->selected  = $this->buildQuery()->pluck('id')->toArray();
        } else {
            $this->selected = [];
        }
    }

    public function deleteSelected(): void
    {
        Invoice::whereIn('id', $this->selected)
            ->where('school_id', auth()->user()->school_id)
            ->whereIn('status', [InvoiceStatus::DRAFT->value, InvoiceStatus::CANCELLED->value])
            ->delete();

        $this->selected  = [];
        $this->selectAll = false;
        $this->success('Factures supprimées.', position: 'toast-top toast-end', icon: 'o-trash', css: 'alert-success', timeout: 3000);
    }

    public function filterByStatus(string $status): void
    {
        $this->statusFilter = $this->statusFilter === $status ? '' : $status;
        $this->resetPage();
    }

    private function buildQuery()
    {
        $schoolId = auth()->user()->school_id;

        return Invoice::where('school_id', $schoolId)
            ->with(['student', 'academicYear'])
            ->when($this->search, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('reference', 'like', "%{$this->search}%")
                        ->orWhereHas('student', fn($s) => $s->where('name', 'like', "%{$this->search}%"));
                });
            })
            ->when($this->statusFilter, fn($q) => $q->where('status', $this->statusFilter))
            ->when($this->typeFilter,   fn($q) => $q->where('invoice_type', $this->typeFilter))
            ->when($this->yearFilter,   fn($q) => $q->where('academic_year_id', $this->yearFilter))
            ->orderBy($this->sortBy, $this->sortDir);
    }

    public function activeFiltersCount(): int
    {
        return (int)(bool)$this->statusFilter
            + (int)(bool)$this->typeFilter
            + (int)(bool)$this->yearFilter;
    }

    public function with(): array
    {
        $schoolId = auth()->user()->school_id;

        $stats = Invoice::where('school_id', $schoolId)
            ->selectRaw('status, COUNT(*) as cnt, SUM(total) as total_amount, SUM(paid_total) as paid_amount, SUM(balance_due) as balance')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $overdueCount = Invoice::where('school_id', $schoolId)
            ->where('status', InvoiceStatus::OVERDUE->value)
            ->count();

        return [
            'invoices'       => $this->buildQuery()->paginate($this->perPage),
            'stats'          => $stats,
            'overdueCount'   => $overdueCount,
            'academicYears'  => AcademicYear::where('school_id', $schoolId)->orderByDesc('start_date')->get(),
            'statusOptions'  => collect(InvoiceStatus::cases())->map(fn($s) => ['id' => $s->value, 'name' => $s->label()])->all(),
            'typeOptions'    => collect(InvoiceType::cases())->map(fn($t) => ['id' => $t->value, 'name' => $t->label()])->all(),
            'activeFilters'  => $this->activeFiltersCount(),
        ];
    }
};
?>

<div>
    <x-header title="Factures" separator progress-indicator>
        <x-slot:actions>
            <x-button label="Exporter" icon="o-arrow-down-tray"
                      wire:click="$set('showExport', true)"
                      class="btn-ghost" />
            <x-button label="Nouvelle facture" icon="o-plus"
                      :link="route('admin.finance.invoices.create')"
                      class="btn-primary" wire:navigate />
        </x-slot:actions>
    </x-header>

    {{-- Revenue summary --}}
    @php
        $totalAll     = $stats->sum('total_amount');
        $totalPaid    = $stats->sum('paid_amount');
        $totalBalance = $stats->sum('balance');
    @endphp
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-5">
        <div class="rounded-2xl bg-linear-to-br from-primary to-primary/70 p-4 text-primary-content">
            <div class="flex items-center gap-2 opacity-75 mb-1">
                <x-icon name="o-document-text" class="w-4 h-4"/>
                <span class="text-xs font-semibold uppercase tracking-wide">Facturation totale</span>
            </div>
            <p class="text-2xl font-black">{{ number_format($totalAll, 0, ',', ' ') }} <span class="text-sm font-normal opacity-70">DJF</span></p>
            <p class="text-xs opacity-60 mt-1">{{ $stats->sum('cnt') }} factures</p>
        </div>
        <div class="rounded-2xl bg-linear-to-br from-success to-success/70 p-4 text-success-content">
            <div class="flex items-center gap-2 opacity-75 mb-1">
                <x-icon name="o-check-circle" class="w-4 h-4"/>
                <span class="text-xs font-semibold uppercase tracking-wide">Montant encaissé</span>
            </div>
            <p class="text-2xl font-black">{{ number_format($totalPaid, 0, ',', ' ') }} <span class="text-sm font-normal opacity-70">DJF</span></p>
            @if($totalAll > 0)
            <p class="text-xs opacity-60 mt-1">{{ number_format(($totalPaid / $totalAll) * 100, 0) }}% du total facturé</p>
            @endif
        </div>
        <div class="rounded-2xl bg-linear-to-br from-error to-error/70 p-4 text-error-content">
            <div class="flex items-center gap-2 opacity-75 mb-1">
                <x-icon name="o-exclamation-circle" class="w-4 h-4"/>
                <span class="text-xs font-semibold uppercase tracking-wide">Solde dû total</span>
            </div>
            <p class="text-2xl font-black">{{ number_format($totalBalance, 0, ',', ' ') }} <span class="text-sm font-normal opacity-70">DJF</span></p>
            <p class="text-xs opacity-60 mt-1">Reste à percevoir</p>
        </div>
    </div>

    {{-- Status filter chips --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
        @foreach(InvoiceStatus::cases() as $status)
        @php
            $s     = $stats[$status->value] ?? null;
            $cnt   = $s?->cnt ?? 0;
            $amt   = $s?->total_amount ?? 0;
            $color = match($status) {
                InvoiceStatus::DRAFT          => 'bg-base-200 border-base-300',
                InvoiceStatus::ISSUED         => 'bg-info/10 border-info/30',
                InvoiceStatus::PARTIALLY_PAID => 'bg-warning/10 border-warning/30',
                InvoiceStatus::PAID           => 'bg-success/10 border-success/30',
                InvoiceStatus::CANCELLED      => 'bg-base-200 border-base-300',
                InvoiceStatus::OVERDUE        => 'bg-error/10 border-error/30',
            };
            $textColor = match($status) {
                InvoiceStatus::DRAFT          => 'text-base-content/60',
                InvoiceStatus::ISSUED         => 'text-info',
                InvoiceStatus::PARTIALLY_PAID => 'text-warning',
                InvoiceStatus::PAID           => 'text-success',
                InvoiceStatus::CANCELLED      => 'text-base-content/40',
                InvoiceStatus::OVERDUE        => 'text-error',
            };
        @endphp
        <button wire:click="filterByStatus('{{ $status->value }}')"
                class="rounded-xl border p-3 text-left transition-all hover:shadow-md {{ $color }}
                       {{ $statusFilter === $status->value ? 'ring-2 ring-offset-1 ring-primary scale-95' : '' }}">
            <p class="text-[11px] text-base-content/60 mb-0.5">{{ $status->label() }}</p>
            <p class="text-2xl font-black {{ $textColor }}">{{ $cnt }}</p>
            @if($amt > 0)
            <p class="text-[10px] {{ $textColor }} opacity-60 mt-0.5 truncate">{{ number_format($amt, 0, ',', ' ') }} DJF</p>
            @endif
        </button>
        @endforeach
    </div>

    {{-- Toolbar --}}
    <div class="flex items-center gap-3 mb-4 flex-wrap">
        <x-input wire:model.live.debounce="search" placeholder="Rechercher (réf, élève)..."
                 icon="o-magnifying-glass" clearable class="flex-1 min-w-48" />

        <x-button icon="o-adjustments-horizontal"
                  wire:click="$set('showFilters', true)"
                  class="btn-outline {{ $activeFilters > 0 ? 'btn-primary' : '' }}">
            Filtres
            @if($activeFilters > 0)
            <x-badge value="{{ $activeFilters }}" class="badge-primary badge-xs ml-1" />
            @endif
        </x-button>

        <x-select wire:model.live="perPage" :options="[['id'=>10,'name'=>'10'],['id'=>20,'name'=>'20'],['id'=>50,'name'=>'50'],['id'=>100,'name'=>'100']]"
                  option-value="id" option-label="name" class="select-sm w-20" />
    </div>

    {{-- Bulk action bar --}}
    @if(count($selected) > 0)
    <div class="flex items-center gap-3 mb-3 p-3 bg-primary/10 rounded-xl border border-primary/20">
        <span class="text-sm font-semibold text-primary">{{ count($selected) }} sélectionné(s)</span>
        <x-button label="Supprimer" icon="o-trash"
                  wire:click="deleteSelected"
                  wire:confirm="Supprimer les factures sélectionnées (brouillons/annulées uniquement) ?"
                  class="btn-error btn-sm" />
        <x-button label="Désélectionner" wire:click="$set('selected', [])" class="btn-ghost btn-sm" />
    </div>
    @endif

    {{-- Table --}}
    <x-card>
        <div class="overflow-x-auto"><table class="table w-full">
            <thead><tr>
                <th class="w-10">
                    <x-checkbox wire:model.live="selectAll" wire:change="toggleSelectAll" />
                </th>
                <th>
                    <button wire:click="sortBy('reference')" class="flex items-center gap-1 hover:text-primary">
                        Référence
                        @if($sortBy==='reference') <x-icon name="{{ $sortDir==='asc' ? 'o-chevron-up' : 'o-chevron-down' }}" class="w-3 h-3"/> @endif
                    </button>
                </th>
                <th>
                    <button wire:click="sortBy('student_id')" class="flex items-center gap-1 hover:text-primary">
                        Élève
                    </button>
                </th>
                <th>Type</th>
                <th class="text-right">
                    <button wire:click="sortBy('total')" class="flex items-center gap-1 hover:text-primary ml-auto">
                        Montant
                        @if($sortBy==='total') <x-icon name="{{ $sortDir==='asc' ? 'o-chevron-up' : 'o-chevron-down' }}" class="w-3 h-3"/> @endif
                    </button>
                </th>
                <th class="text-right">Payé</th>
                <th class="text-right">Solde</th>
                <th>Statut</th>
                <th>
                    <button wire:click="sortBy('due_date')" class="flex items-center gap-1 hover:text-primary">
                        Échéance
                        @if($sortBy==='due_date') <x-icon name="{{ $sortDir==='asc' ? 'o-chevron-up' : 'o-chevron-down' }}" class="w-3 h-3"/> @endif
                    </button>
                </th>
                <th class="w-24">Actions</th>
            </tr></thead><tbody>

            @forelse($invoices as $invoice)
            @php
                $statusClass = match($invoice->status) {
                    InvoiceStatus::DRAFT         => 'badge-ghost',
                    InvoiceStatus::ISSUED        => 'badge-info',
                    InvoiceStatus::PARTIALLY_PAID => 'badge-warning',
                    InvoiceStatus::PAID          => 'badge-success',
                    InvoiceStatus::CANCELLED     => 'badge-ghost opacity-50',
                    InvoiceStatus::OVERDUE       => 'badge-error',
                    default => 'badge-ghost',
                };
                $isOverdue = $invoice->due_date && $invoice->due_date->isPast()
                             && !in_array($invoice->status->value ?? $invoice->status, ['paid','cancelled']);
            @endphp
            <tr wire:key="invoice-{{ $invoice->id }}" class="hover">
                <td>
                    <x-checkbox wire:model.live="selected" value="{{ $invoice->id }}"
                                wire:click="toggleSelect({{ $invoice->id }})" />
                </td>
                <td>
                    <a href="{{ route('admin.finance.invoices.show', $invoice->uuid) }}"
                       wire:navigate class="font-mono text-sm font-bold hover:text-primary">
                        {{ $invoice->reference }}
                    </a>
                </td>
                <td>
                    <div>
                        <p class="font-semibold text-sm">{{ $invoice->student?->full_name }}</p>
                        <p class="text-xs text-base-content/50">{{ $invoice->academicYear?->name }}</p>
                    </div>
                </td>
                <td>
                    <x-badge value="{{ $invoice->invoice_type?->label() ?? $invoice->invoice_type }}" class="badge-outline badge-sm" />
                </td>
                <td class="text-right font-semibold">{{ number_format($invoice->total, 0, ',', ' ') }} DJF</td>
                <td class="text-right text-success">{{ number_format($invoice->paid_total, 0, ',', ' ') }} DJF</td>
                <td class="text-right {{ $invoice->balance_due > 0 ? 'text-error font-semibold' : 'text-base-content/40' }}">
                    {{ number_format($invoice->balance_due, 0, ',', ' ') }} DJF
                </td>
                <td>
                    <x-badge value="{{ $invoice->status?->label() ?? $invoice->status }}" class="{{ $statusClass }} badge-sm" />
                </td>
                <td class="{{ $isOverdue ? 'text-error font-semibold' : '' }}">
                    {{ $invoice->due_date?->format('d/m/Y') ?? '—' }}
                </td>
                <td>
                    <div class="flex gap-1">
                        <x-button icon="o-eye" :link="route('admin.finance.invoices.show', $invoice->uuid)"
                                  class="btn-ghost btn-xs" tooltip="Voir" wire:navigate />
                        <x-button icon="o-printer" :link="route('admin.finance.invoices.print', $invoice->uuid)"
                                  class="btn-ghost btn-xs" tooltip="Imprimer" />
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="10" class="text-center py-12 text-base-content/40">
                    <x-icon name="o-document-text" class="w-12 h-12 mx-auto mb-2 opacity-20" />
                    <p>Aucune facture trouvée</p>
                </td>
            </tr>
            @endforelse
        </tbody></table></div>

        <div class="mt-4 px-2">
            {{ $invoices->links() }}
        </div>
    </x-card>

    {{-- Filters drawer --}}
    <x-drawer wire:model="showFilters" title="Filtres" position="right" class="w-80">
        <div class="space-y-4 p-4">
            <x-select
                label="Statut"
                wire:model.live="statusFilter"
                :options="$statusOptions"
                option-value="id"
                option-label="name"
                placeholder="Tous les statuts"
                placeholder-value=""
            />
            <x-select
                label="Type"
                wire:model.live="typeFilter"
                :options="$typeOptions"
                option-value="id"
                option-label="name"
                placeholder="Tous les types"
                placeholder-value=""
            />
            <x-select
                label="Année scolaire"
                wire:model.live="yearFilter"
                :options="$academicYears"
                option-value="id"
                option-label="name"
                placeholder="Toutes les années"
                placeholder-value="0"
            />
        </div>
        <x-slot:actions>
            <x-button label="Réinitialiser"
                      wire:click="$set('statusFilter', ''); $set('typeFilter', ''); $set('yearFilter', 0); $set('showFilters', false)"
                      class="btn-ghost" />
            <x-button label="Fermer" wire:click="$set('showFilters', false)" class="btn-primary" />
        </x-slot:actions>
    </x-drawer>

    {{-- Export modal --}}
    <x-modal wire:model="showExport" title="Exporter les factures" separator>
        <div class="space-y-4">
            <div>
                <p class="label-text mb-2 font-semibold">Format</p>
                <div class="flex gap-3">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" wire:model="exportFormat" value="pdf" class="radio radio-primary radio-sm" />
                        <span>PDF</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" wire:model="exportFormat" value="xlsx" class="radio radio-primary radio-sm" />
                        <span>Excel (.xlsx)</span>
                    </label>
                </div>
            </div>
            <div>
                <p class="label-text mb-2 font-semibold">Périmètre</p>
                <div class="space-y-2">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" wire:model="exportScope" value="filtered" class="radio radio-primary radio-sm" />
                        <span>Filtres actuels ({{ $invoices->total() }} factures)</span>
                    </label>
                    @if(count($selected) > 0)
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" wire:model="exportScope" value="selected" class="radio radio-primary radio-sm" />
                        <span>Sélection ({{ count($selected) }} factures)</span>
                    </label>
                    @endif
                </div>
            </div>
        </div>
        <x-slot:actions>
            <x-button label="Annuler" wire:click="$set('showExport', false)" class="btn-ghost" />
            <a href="#"
               x-data
               @click.prevent="
                   let fmt = $wire.exportFormat;
                   let scope = $wire.exportScope;
                   let baseUrl = fmt === 'pdf' ? '{{ route('admin.finance.invoices.export.pdf') }}' : '{{ route('admin.finance.invoices.export.xlsx') }}';
                   let url = baseUrl + '?';
                   if (scope === 'selected') url += 'ids={{ implode(',', $selected) }}';
                   else url += 'status={{ $statusFilter }}&type={{ $typeFilter }}&year={{ $yearFilter }}';
                   window.open(url, '_blank');
                   $wire.set('showExport', false);
               "
               class="btn btn-primary">
                <x-icon name="o-arrow-down-tray" class="w-4 h-4 mr-1" />
                Télécharger
            </a>
        </x-slot:actions>
    </x-modal>
</div>
