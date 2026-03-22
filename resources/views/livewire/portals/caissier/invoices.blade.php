<?php
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Mary\Traits\Toast;
use App\Models\Invoice;
use Livewire\WithPagination;

new #[Layout('layouts.caissier')] class extends Component {
    use Toast, WithPagination;

    public string $search       = '';
    public string $filterStatus = 'unpaid';

    public function with(): array
    {
        $schoolId = auth()->user()->school_id;

        $invoices = Invoice::where('school_id', $schoolId)
            ->when($this->filterStatus, fn($q) => $q->where('status', $this->filterStatus))
            ->when($this->search, fn($q) => $q->whereHas('enrollment.student', fn($q2) =>
                $q2->where('name', 'like', "%{$this->search}%")
                   ->orWhere('reference', 'like', "%{$this->search}%")
            ))
            ->with(['enrollment.student', 'enrollment.schoolClass', 'payments'])
            ->orderBy('due_date')
            ->paginate(20);

        return compact('invoices');
    }
};
?>

<div class="p-4 lg:p-6 space-y-6">
    <x-header title="{{ __('navigation.invoices') }}" subtitle="Gestion des factures" separator>
        <x-slot:actions>
            <x-button icon="o-arrow-left" link="{{ route('caissier.dashboard') }}" wire:navigate class="btn-ghost btn-sm" />
            <a href="{{ route('caissier.payment') }}" wire:navigate>
                <x-button label="Encaisser" icon="o-credit-card" class="btn-primary btn-sm" />
            </a>
        </x-slot:actions>
    </x-header>

    <x-card shadow class="border-0">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <x-input wire:model.live.debounce="search" placeholder="Rechercher un élève..." icon="o-magnifying-glass" />
            <x-select wire:model.live="filterStatus" label="Statut"
                :options="[['id' => '', 'name' => 'Tous'], ['id' => 'unpaid', 'name' => 'Impayées'], ['id' => 'partial', 'name' => 'Partielles'], ['id' => 'paid', 'name' => 'Payées']]" />
        </div>
    </x-card>

    <x-card shadow class="border-0 p-0 overflow-hidden">
        <table class="table table-sm w-full">
            <thead class="bg-base-200">
                <tr>
                    <th>Élève</th>
                    <th>Référence</th>
                    <th>Classe</th>
                    <th>Échéance</th>
                    <th class="text-right">Montant</th>
                    <th class="text-right">Payé</th>
                    <th class="text-center">Statut</th>
                    <th class="text-center">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($invoices as $invoice)
                @php
                    $paid = $invoice->payments->sum('amount');
                    $badge = match($invoice->status) { 'paid' => 'badge-success', 'unpaid' => 'badge-error', 'partial' => 'badge-warning', default => 'badge-ghost' };
                    $label = match($invoice->status) { 'paid' => 'Payée', 'unpaid' => 'Impayée', 'partial' => 'Partielle', default => $invoice->status };
                    $overdue = $invoice->status !== 'paid' && $invoice->due_date?->isPast();
                @endphp
                <tr class="hover border-b border-base-100 {{ $overdue ? 'bg-red-50/50' : '' }}">
                    <td class="font-medium">{{ $invoice->enrollment?->student?->full_name }}</td>
                    <td class="font-mono text-xs text-base-content/60">{{ $invoice->reference }}</td>
                    <td class="text-sm">{{ $invoice->enrollment?->schoolClass?->name ?? '—' }}</td>
                    <td class="text-sm {{ $overdue ? 'text-error font-medium' : 'text-base-content/60' }}">
                        {{ $invoice->due_date?->format('d/m/Y') }}
                        @if($overdue) <x-badge value="En retard" class="badge-error badge-xs ml-1" /> @endif
                    </td>
                    <td class="text-right font-semibold">{{ number_format($invoice->amount, 0, ',', ' ') }}</td>
                    <td class="text-right text-sm text-success">{{ number_format($paid, 0, ',', ' ') }}</td>
                    <td class="text-center"><x-badge :value="$label" class="{{ $badge }} badge-sm" /></td>
                    <td class="text-center">
                        @if($invoice->status !== 'paid')
                        <a href="{{ route('caissier.payment', ['invoice' => $invoice->uuid]) }}" wire:navigate>
                            <x-button label="Encaisser" class="btn-xs btn-primary" />
                        </a>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center py-12 text-base-content/40">
                        <x-icon name="o-document-currency-dollar" class="w-10 h-10 mx-auto mb-2" />
                        <p class="text-sm">Aucune facture trouvée</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        <div class="p-4">{{ $invoices->links() }}</div>
    </x-card>
</div>
