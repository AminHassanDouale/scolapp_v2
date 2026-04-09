<?php
use App\Models\Expense;
use App\Models\AcademicYear;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Mary\Traits\Toast;
use Illuminate\Support\Str;

new #[Layout('layouts.app')] class extends Component {
    use Toast, WithPagination;

    // ── Filters ───────────────────────────────────────────────────────────────
    public string $search         = '';
    public string $categoryFilter = '';
    public string $dateFrom       = '';
    public string $dateTo         = '';

    // ── Modal ─────────────────────────────────────────────────────────────────
    public bool   $showModal   = false;
    public ?int   $editingId   = null;

    // ── Form ─────────────────────────────────────────────────────────────────
    public string $f_label          = '';
    public string $f_category       = 'autre';
    public string $f_amount         = '';
    public string $f_expense_date   = '';
    public string $f_payment_method = 'cash';
    public string $f_reference      = '';
    public string $f_notes          = '';

    public function updatingSearch(): void { $this->resetPage(); }

    public function openCreate(): void
    {
        $this->editingId        = null;
        $this->f_label          = '';
        $this->f_category       = 'autre';
        $this->f_amount         = '';
        $this->f_expense_date   = now()->format('Y-m-d');
        $this->f_payment_method = 'cash';
        $this->f_reference      = '';
        $this->f_notes          = '';
        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $expense = Expense::findOrFail($id);
        $this->editingId        = $id;
        $this->f_label          = $expense->label;
        $this->f_category       = $expense->category;
        $this->f_amount         = (string) $expense->amount;
        $this->f_expense_date   = $expense->expense_date->format('Y-m-d');
        $this->f_payment_method = $expense->payment_method;
        $this->f_reference      = $expense->reference ?? '';
        $this->f_notes          = $expense->notes ?? '';
        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'f_label'          => 'required|string|max:200',
            'f_category'       => 'required|in:salaires,loyer,fournitures,services,maintenance,autre',
            'f_amount'         => 'required|numeric|min:1',
            'f_expense_date'   => 'required|date',
            'f_payment_method' => 'required|in:cash,bank_transfer,check',
            'f_reference'      => 'nullable|string|max:100',
            'f_notes'          => 'nullable|string|max:1000',
        ]);

        $schoolId = auth()->user()->school_id;
        $year     = AcademicYear::where('school_id', $schoolId)->where('is_current', true)->first();

        $payload = [
            'school_id'        => $schoolId,
            'academic_year_id' => $year?->id,
            'label'            => $data['f_label'],
            'category'         => $data['f_category'],
            'amount'           => $data['f_amount'],
            'expense_date'     => $data['f_expense_date'],
            'payment_method'   => $data['f_payment_method'],
            'reference'        => $data['f_reference'] ?: null,
            'notes'            => $data['f_notes'] ?: null,
        ];

        if ($this->editingId) {
            Expense::findOrFail($this->editingId)->update($payload);
            $this->success('Dépense mise à jour.', position: 'toast-top toast-end', timeout: 3000);
        } else {
            $payload['uuid']       = (string) Str::uuid();
            $payload['created_by'] = auth()->id();
            Expense::create($payload);
            $this->success('Dépense enregistrée.', position: 'toast-top toast-end', timeout: 3000);
        }

        $this->showModal = false;
    }

    public function delete(int $id): void
    {
        Expense::findOrFail($id)->delete();
        $this->success('Dépense supprimée.', position: 'toast-top toast-end', timeout: 3000);
    }

    public function with(): array
    {
        $schoolId = auth()->user()->school_id;

        $expenses = Expense::where('school_id', $schoolId)
            ->when($this->search,         fn($q) => $q->where('label', 'like', "%{$this->search}%")->orWhere('reference', 'like', "%{$this->search}%"))
            ->when($this->categoryFilter, fn($q) => $q->where('category', $this->categoryFilter))
            ->when($this->dateFrom,       fn($q) => $q->whereDate('expense_date', '>=', $this->dateFrom))
            ->when($this->dateTo,         fn($q) => $q->whereDate('expense_date', '<=', $this->dateTo))
            ->orderByDesc('expense_date')
            ->paginate(20);

        $totalExpenses = Expense::where('school_id', $schoolId)->sum('amount');
        $monthExpenses = Expense::where('school_id', $schoolId)
            ->whereMonth('expense_date', now()->month)
            ->whereYear('expense_date', now()->year)
            ->sum('amount');

        $categories = collect(Expense::categories())->map(fn($c) => [
            'id'   => $c,
            'name' => Expense::categoryLabel($c),
        ])->prepend(['id' => '', 'name' => 'Toutes les catégories'])->all();

        $paymentMethods = [
            ['id' => 'cash',          'name' => 'Espèces'],
            ['id' => 'bank_transfer', 'name' => 'Virement'],
            ['id' => 'check',         'name' => 'Chèque'],
        ];

        $categoryOptions = collect(Expense::categories())->map(fn($c) => [
            'id'   => $c,
            'name' => Expense::categoryLabel($c),
        ])->all();

        return compact('expenses', 'totalExpenses', 'monthExpenses', 'categories', 'paymentMethods', 'categoryOptions');
    }
};
?>

<div>
    <x-header title="Dépenses" subtitle="Gestion des sorties de trésorerie" separator progress-indicator>
        <x-slot:actions>
            <x-button label="Nouvelle dépense" icon="o-plus" wire:click="openCreate" class="btn-primary" />
        </x-slot:actions>
    </x-header>

    {{-- Stats --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="rounded-2xl bg-gradient-to-br from-error to-error/70 p-4 text-error-content">
            <p class="text-sm opacity-80">Total dépenses</p>
            <p class="text-2xl font-black mt-1">{{ number_format($totalExpenses, 0, ',', ' ') }}</p>
            <p class="text-xs opacity-70">DJF</p>
        </div>
        <div class="rounded-2xl bg-gradient-to-br from-warning to-warning/70 p-4 text-warning-content">
            <p class="text-sm opacity-80">Ce mois</p>
            <p class="text-2xl font-black mt-1">{{ number_format($monthExpenses, 0, ',', ' ') }}</p>
            <p class="text-xs opacity-70">DJF — {{ now()->translatedFormat('F Y') }}</p>
        </div>
        <div class="rounded-2xl bg-base-200 p-4">
            <p class="text-sm text-base-content/60">Nombre (page)</p>
            <p class="text-2xl font-black mt-1">{{ $expenses->total() }}</p>
            <p class="text-xs text-base-content/50">entrée(s)</p>
        </div>
        <div class="rounded-2xl bg-base-200 p-4 flex items-center justify-center">
            <a href="{{ route('admin.finance.comptabilite.index') }}" wire:navigate
               class="btn btn-outline btn-sm gap-2">
                <x-icon name="o-chart-bar" class="w-4 h-4" />
                Tableau comptable
            </a>
        </div>
    </div>

    {{-- Toolbar --}}
    <div class="flex flex-wrap items-center gap-3 mb-4">
        <x-input wire:model.live.debounce="search" placeholder="Rechercher (libellé, réf)..."
                 icon="o-magnifying-glass" clearable class="flex-1 min-w-48" />
        <x-select wire:model.live="categoryFilter" :options="$categories"
                  option-value="id" option-label="name" class="select-sm min-w-44" />
        <x-datepicker wire:model.live="dateFrom" placeholder="Du" icon="o-calendar" class="input-sm w-36"
                      :config="['dateFormat'=>'Y-m-d','altFormat'=>'d/m/Y','altInput'=>true]" />
        <x-datepicker wire:model.live="dateTo" placeholder="Au" icon="o-calendar" class="input-sm w-36"
                      :config="['dateFormat'=>'Y-m-d','altFormat'=>'d/m/Y','altInput'=>true]" />
        @if($search || $categoryFilter || $dateFrom || $dateTo)
        <x-button icon="o-x-mark" wire:click="$set('search',''); $set('categoryFilter',''); $set('dateFrom',''); $set('dateTo','')"
                  class="btn-ghost btn-sm" />
        @endif
    </div>

    {{-- Table --}}
    <x-card>
        <div class="overflow-x-auto">
            <table class="table w-full">
                <thead><tr>
                    <th>Date</th>
                    <th>Libellé</th>
                    <th>Catégorie</th>
                    <th class="text-right">Montant</th>
                    <th>Méthode</th>
                    <th>Réf.</th>
                    <th class="w-20">Actions</th>
                </tr></thead>
                <tbody>
                @forelse($expenses as $expense)
                @php
                    $catColor = match($expense->category) {
                        'salaires'    => 'bg-purple-100 text-purple-700',
                        'loyer'       => 'bg-blue-100 text-blue-700',
                        'fournitures' => 'bg-amber-100 text-amber-700',
                        'services'    => 'bg-cyan-100 text-cyan-700',
                        'maintenance' => 'bg-orange-100 text-orange-700',
                        default       => 'bg-base-200 text-base-content/60',
                    };
                    $methodLabel = match($expense->payment_method) {
                        'cash'          => 'Espèces',
                        'bank_transfer' => 'Virement',
                        'check'         => 'Chèque',
                        default         => $expense->payment_method,
                    };
                @endphp
                <tr wire:key="exp-{{ $expense->id }}" class="hover">
                    <td class="text-sm font-medium">{{ $expense->expense_date->format('d/m/Y') }}</td>
                    <td>
                        <p class="font-semibold text-sm">{{ $expense->label }}</p>
                        @if($expense->notes)
                        <p class="text-xs text-base-content/40 truncate max-w-48">{{ $expense->notes }}</p>
                        @endif
                    </td>
                    <td>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-bold {{ $catColor }}">
                            {{ \App\Models\Expense::categoryLabel($expense->category) }}
                        </span>
                    </td>
                    <td class="text-right font-black text-error">{{ number_format($expense->amount, 0, ',', ' ') }} <span class="text-xs font-normal text-base-content/40">DJF</span></td>
                    <td class="text-sm text-base-content/60">{{ $methodLabel }}</td>
                    <td class="text-xs font-mono text-base-content/50">{{ $expense->reference ?? '—' }}</td>
                    <td>
                        <div class="flex gap-1">
                            <x-button icon="o-pencil" wire:click="openEdit({{ $expense->id }})"
                                      class="btn-ghost btn-xs" tooltip="Modifier" />
                            <x-button icon="o-trash" wire:click="delete({{ $expense->id }})"
                                      wire:confirm="Supprimer cette dépense ?"
                                      class="btn-ghost btn-xs text-error" tooltip="Supprimer" />
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center py-12 text-base-content/40">
                        <x-icon name="o-banknotes" class="w-10 h-10 mx-auto mb-2 opacity-20" />
                        <p>Aucune dépense enregistrée</p>
                    </td>
                </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $expenses->links() }}</div>
    </x-card>

    {{-- Modal create/edit --}}
    <x-modal wire:model="showModal" :title="$editingId ? 'Modifier la dépense' : 'Nouvelle dépense'" separator>
        <x-form wire:submit="save" class="space-y-4">

            <x-input label="Libellé *" wire:model="f_label" placeholder="Ex : Salaire enseignant — avril" required />

            <div class="grid grid-cols-2 gap-4">
                <x-select label="Catégorie *" wire:model="f_category"
                          :options="$categoryOptions" option-value="id" option-label="name" />
                <x-input label="Montant (DJF) *" wire:model="f_amount" type="number" min="1" placeholder="0" required />
            </div>

            <div class="grid grid-cols-2 gap-4">
                <x-datepicker label="Date *" wire:model="f_expense_date" icon="o-calendar"
                              :config="['dateFormat'=>'Y-m-d','altFormat'=>'d/m/Y','altInput'=>true,'locale'=>['firstDayOfWeek'=>1]]" />
                <x-select label="Méthode de paiement" wire:model="f_payment_method"
                          :options="$paymentMethods" option-value="id" option-label="name" />
            </div>

            <x-input label="Référence / N° reçu" wire:model="f_reference" placeholder="REC-2026-001" />
            <x-textarea label="Notes" wire:model="f_notes" placeholder="Détails supplémentaires..." rows="2" />

            <x-slot:actions>
                <x-button label="Annuler" wire:click="$set('showModal', false)" class="btn-ghost" />
                <x-button :label="$editingId ? 'Mettre à jour' : 'Enregistrer'" type="submit"
                          icon="o-check" class="btn-primary" spinner="save" />
            </x-slot:actions>
        </x-form>
    </x-modal>
</div>
