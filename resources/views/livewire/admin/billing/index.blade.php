<?php
use App\Models\DmoneyTransaction;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new #[Layout('layouts.app')] class extends Component {
    use Toast, WithPagination;

    public string $search       = '';
    public string $statusFilter = '';
    public string $sortBy       = 'created_at';
    public string $sortDir      = 'desc';

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingStatusFilter(): void { $this->resetPage(); }

    public function markCancelled(int $id): void
    {
        $tx = DmoneyTransaction::where('school_id', auth()->user()->school_id)->findOrFail($id);
        if ($tx->isPending()) {
            $tx->update(['status' => 'cancelled', 'cancelled_at' => now()]);
            $this->success('Transaction marquée annulée.', position: 'toast-top toast-end', timeout: 3000);
        } else {
            $this->error('Seules les transactions en attente peuvent être annulées.', position: 'toast-top toast-end', timeout: 3000);
        }
    }

    public function with(): array
    {
        $schoolId = auth()->user()->school_id;

        $transactions = DmoneyTransaction::where('school_id', $schoolId)
            ->with(['invoice', 'student', 'user'])
            ->when($this->search, fn($q) =>
                $q->where('order_id', 'like', "%{$this->search}%")
                  ->orWhereHas('student', fn($s) => $s->where('name', 'like', "%{$this->search}%"))
                  ->orWhereHas('user', fn($u) => $u->where('name', 'like', "%{$this->search}%"))
            )
            ->when($this->statusFilter, fn($q) => $q->where('status', $this->statusFilter))
            ->orderBy($this->sortBy, $this->sortDir)
            ->paginate(20);

        $totalCompleted  = DmoneyTransaction::where('school_id', $schoolId)->where('status', 'completed')->sum('amount');
        $totalPending    = DmoneyTransaction::where('school_id', $schoolId)->where('status', 'pending')->count();
        $totalCancelled  = DmoneyTransaction::where('school_id', $schoolId)->where('status', 'cancelled')->count();
        $countCompleted  = DmoneyTransaction::where('school_id', $schoolId)->where('status', 'completed')->count();

        $statuses = [
            ['id' => '',          'name' => 'Tous les statuts'],
            ['id' => 'pending',   'name' => 'En attente'],
            ['id' => 'completed', 'name' => 'Confirmé'],
            ['id' => 'failed',    'name' => 'Échoué'],
            ['id' => 'cancelled', 'name' => 'Annulé'],
        ];

        return compact('transactions', 'totalCompleted', 'totalPending', 'totalCancelled', 'countCompleted', 'statuses');
    }
};
?>

<div>
    <x-header title="Transactions D-Money" separator>
        <x-slot:middle class="!justify-end">
            <x-input placeholder="Rechercher (order_id, élève, parent)…" wire:model.live.debounce.300ms="search"
                     icon="o-magnifying-glass" clearable />
        </x-slot:middle>
        <x-slot:actions>
            <x-select :options="$statuses" wire:model.live="statusFilter" option-value="id" option-label="name"
                      placeholder="Statut" class="select-sm w-40" />
            <x-button label="Paramètres API" icon="o-cog-6-tooth"
                      link="{{ route('admin.settings.billing-api') }}" class="btn-ghost btn-sm" />
        </x-slot:actions>
    </x-header>

    {{-- Stats --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <x-stat title="Montant encaissé" :value="number_format($totalCompleted, 0, ',', ' ') . ' DJF'"
                icon="o-banknotes" color="text-success" />
        <x-stat title="Transactions confirmées" :value="$countCompleted"
                icon="o-check-circle" color="text-success" />
        <x-stat title="En attente" :value="$totalPending"
                icon="o-clock" color="text-warning" />
        <x-stat title="Annulées / Échouées" :value="$totalCancelled"
                icon="o-x-circle" color="text-error" />
    </div>

    {{-- Table --}}
    <x-card>
        @if($transactions->isEmpty())
            <x-icon name="o-inbox" class="w-12 h-12 mx-auto text-base-content/30 mt-8 mb-4" />
            <p class="text-center text-base-content/50 pb-8">Aucune transaction D-Money trouvée.</p>
        @else
        <x-table>
            <x-slot:headers>
                <x-table.header label="Date" sortable wire:click="$set('sortBy','created_at')" />
                <x-table.header label="Order ID" />
                <x-table.header label="Élève" />
                <x-table.header label="Parent" />
                <x-table.header label="Facture" />
                <x-table.header label="Montant" sortable wire:click="$set('sortBy','amount')" />
                <x-table.header label="Statut" />
                <x-table.header label="Actions" />
            </x-slot:headers>

            @foreach($transactions as $tx)
            <x-table.row>
                <x-table.cell>
                    <span class="text-sm">{{ $tx->created_at->format('d/m/Y H:i') }}</span>
                    @if($tx->completed_at)
                        <br><span class="text-xs text-success">Confirmé {{ $tx->completed_at->format('H:i') }}</span>
                    @endif
                </x-table.cell>

                <x-table.cell>
                    @if($tx->order_id)
                        <code class="text-xs bg-base-200 px-1 rounded">{{ $tx->order_id }}</code>
                    @else
                        <span class="text-base-content/40 text-xs">—</span>
                    @endif
                </x-table.cell>

                <x-table.cell>
                    {{ $tx->student?->name ?? '—' }}
                </x-table.cell>

                <x-table.cell>
                    <div class="text-sm">{{ $tx->user?->name ?? '—' }}</div>
                    @if($tx->guardian_phone)
                        <div class="text-xs text-base-content/50">{{ $tx->guardian_phone }}</div>
                    @endif
                </x-table.cell>

                <x-table.cell>
                    @if($tx->invoice)
                        <a href="{{ route('admin.finance.invoices.show', $tx->invoice->uuid) }}"
                           class="link link-primary text-sm">{{ $tx->invoice->number ?? '#'.$tx->invoice_id }}</a>
                    @else
                        <span class="text-base-content/40 text-xs">—</span>
                    @endif
                </x-table.cell>

                <x-table.cell>
                    <span class="font-semibold">{{ number_format($tx->amount, 0, ',', ' ') }} DJF</span>
                </x-table.cell>

                <x-table.cell>
                    <x-badge :value="$tx->statusLabel()" :color="$tx->statusColor()" />
                </x-table.cell>

                <x-table.cell>
                    @if($tx->isPending())
                        <x-button icon="o-x-mark" wire:click="markCancelled({{ $tx->id }})"
                                  class="btn-ghost btn-xs text-error"
                                  wire:confirm="Annuler cette transaction ?" tooltip="Annuler" />
                    @endif
                    @if($tx->checkout_url)
                        <x-button icon="o-arrow-top-right-on-square"
                                  link="{{ $tx->checkout_url }}" target="_blank"
                                  class="btn-ghost btn-xs" tooltip="Ouvrir checkout" />
                    @endif
                </x-table.cell>
            </x-table.row>
            @endforeach
        </x-table>

        <div class="mt-4">
            {{ $transactions->links() }}
        </div>
        @endif
    </x-card>
</div>
