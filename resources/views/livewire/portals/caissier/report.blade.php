<?php
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Mary\Traits\Toast;
use App\Models\Payment;
use App\Exports\ArrayExport;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;

new #[Layout('layouts.caissier')] class extends Component {
    use Toast;

    public string $reportDate = '';
    public string $reportType = 'daily';
    public bool   $onlyMine   = true;   // ma caisse vs toute la caisse

    public function mount(): void
    {
        $this->reportDate = today()->format('Y-m-d');
    }

    private function baseQuery()
    {
        $schoolId = auth()->user()->school_id;
        $date     = Carbon::parse($this->reportDate);

        return Payment::where('school_id', $schoolId)
            ->when($this->onlyMine, fn ($q) => $q->where('received_by', auth()->id()))
            ->when($this->reportType === 'daily',   fn ($q) => $q->whereDate('payment_date', $date))
            ->when($this->reportType === 'monthly', fn ($q) => $q->whereMonth('payment_date', $date->month)->whereYear('payment_date', $date->year))
            ->when($this->reportType === 'yearly',  fn ($q) => $q->whereYear('payment_date', $date->year));
    }

    /** Payments in chronological order, each carrying a running balance. */
    private function journal()
    {
        $running = 0.0;

        return $this->baseQuery()
            ->with(['student', 'enrollment.schoolClass', 'receivedBy'])
            ->orderBy('payment_date')->orderBy('id')
            ->get()
            ->map(function (Payment $p) use (&$running) {
                $before = $running;
                $running += (float) $p->amount;
                $p->setAttribute('balance_before', $before);
                $p->setAttribute('balance_after', $running);
                return $p;
            });
    }

    public function exportCaisse()
    {
        $rows = $this->journal()->map(fn (Payment $p) => [
            $p->payment_date?->format('d/m/Y H:i'),
            $p->reference,
            $p->student?->full_name,
            $p->enrollment?->schoolClass?->name,
            match ($p->payment_method) { 'cash' => 'Espèces', 'bank_transfer' => 'Virement', 'check' => 'Chèque', 'mobile_money' => 'Mobile Money', default => $p->payment_method },
            (int) $p->balance_before,
            (int) $p->amount,
            (int) $p->balance_after,
            $p->receivedBy?->name,
        ])->all();

        return Excel::download(new ArrayExport(
            ['Date', 'Référence', 'Élève', 'Classe', 'Mode', 'Solde avant', 'Montant', 'Solde après', 'Caissier'],
            $rows, 'Journal de caisse'
        ), 'journal-caisse-' . $this->reportDate . '.xlsx');
    }

    public function with(): array
    {
        $date    = Carbon::parse($this->reportDate);
        $journal = $this->journal();

        $periodLabel = match ($this->reportType) {
            'daily'   => $date->format('d/m/Y'),
            'monthly' => $date->translatedFormat('F Y'),
            default   => $date->format('Y'),
        };

        return [
            'journal'       => $journal,
            'totalAmount'   => (float) $journal->sum('amount'),
            'openingBalance'=> 0.0,
            'closingBalance'=> (float) $journal->sum('amount'),
            'countByMethod' => $journal->groupBy('payment_method')->map->sum('amount'),
            'methods'       => [
                'cash' => 'Espèces', 'bank_transfer' => 'Virement', 'check' => 'Chèque', 'mobile_money' => 'Mobile Money',
            ],
            'periodLabel'   => $periodLabel,
        ];
    }
};
?>

<div class="p-4 lg:p-6 space-y-6">
    <x-header title="Journal de caisse" subtitle="Suivi des encaissements — solde avant / après" separator>
        <x-slot:actions>
            <x-button label="Exporter Excel" icon="o-arrow-down-tray" wire:click="exportCaisse" spinner="exportCaisse" class="btn-success btn-sm" />
            <x-button icon="o-arrow-left" link="{{ route('caissier.dashboard') }}" wire:navigate class="btn-ghost btn-sm" />
        </x-slot:actions>
    </x-header>

    {{-- Filters --}}
    <x-card shadow class="border-0">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <x-select label="Période" wire:model.live="reportType" :options="[
                ['id' => 'daily',   'name' => 'Journalier'],
                ['id' => 'monthly', 'name' => 'Mensuel'],
                ['id' => 'yearly',  'name' => 'Annuel'],
            ]" />
            <x-input type="date" label="Date de référence" wire:model.live="reportDate" />
            <x-select label="Périmètre" wire:model.live="onlyMine" :options="[
                ['id' => 1, 'name' => 'Ma caisse'],
                ['id' => 0, 'name' => 'Toute la caisse'],
            ]" />
        </div>
    </x-card>

    {{-- Summary --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <x-card class="border-0 shadow-sm bg-gradient-to-br from-slate-50 to-white">
            <p class="text-xs text-base-content/60 mb-1">Solde d'ouverture</p>
            <p class="text-2xl font-black text-slate-700">{{ number_format($openingBalance, 0, ',', ' ') }}</p>
            <p class="text-xs text-base-content/40">DJF</p>
        </x-card>
        <x-card class="border-0 shadow-sm bg-gradient-to-br from-cyan-50 to-white">
            <p class="text-xs text-base-content/60 mb-1">Total encaissé · {{ $periodLabel }}</p>
            <p class="text-2xl font-black text-cyan-700">{{ number_format($totalAmount, 0, ',', ' ') }}</p>
            <p class="text-xs text-base-content/40">{{ $journal->count() }} paiement(s)</p>
        </x-card>
        <x-card class="border-0 shadow-sm bg-gradient-to-br from-emerald-50 to-white">
            <p class="text-xs text-base-content/60 mb-1">Solde de clôture</p>
            <p class="text-2xl font-black text-emerald-700">{{ number_format($closingBalance, 0, ',', ' ') }}</p>
            <p class="text-xs text-base-content/40">DJF</p>
        </x-card>
        <x-card class="border-0 shadow-sm">
            <p class="text-xs text-base-content/60 mb-1">Par mode</p>
            <div class="space-y-0.5 mt-1">
                @foreach($countByMethod as $method => $amount)
                <div class="flex justify-between text-xs">
                    <span class="text-base-content/60">{{ $methods[$method] ?? $method }}</span>
                    <span class="font-bold">{{ number_format($amount, 0, ',', ' ') }}</span>
                </div>
                @endforeach
            </div>
        </x-card>
    </div>

    {{-- Cash journal with running balance --}}
    <x-card shadow class="border-0 p-0 overflow-x-auto">
        <div class="p-4 border-b border-base-200">
            <h3 class="font-bold">Détail chronologique</h3>
        </div>
        <table class="table table-sm w-full">
            <thead class="bg-base-200">
                <tr>
                    <th>Date / Heure</th>
                    <th>Référence</th>
                    <th>Élève</th>
                    <th>Mode</th>
                    <th class="text-right">Solde avant</th>
                    <th class="text-right">Montant</th>
                    <th class="text-right">Solde après</th>
                </tr>
            </thead>
            <tbody>
                @forelse($journal as $payment)
                <tr class="hover border-b border-base-100">
                    <td class="text-sm text-base-content/60 font-mono">{{ $payment->payment_date?->format('d/m/Y H:i') }}</td>
                    <td class="font-mono text-xs">{{ $payment->reference }}</td>
                    <td class="font-medium text-sm">{{ $payment->student?->full_name ?? '—' }}</td>
                    <td>
                        @php $badge = match($payment->payment_method) { 'cash' => 'badge-success', 'bank_transfer' => 'badge-info', 'check' => 'badge-warning', 'mobile_money' => 'badge-secondary', default => 'badge-ghost' }; @endphp
                        <x-badge :value="$methods[$payment->payment_method] ?? $payment->payment_method" class="{{ $badge }} badge-sm" />
                    </td>
                    <td class="text-right text-sm text-base-content/50">{{ number_format($payment->balance_before, 0, ',', ' ') }}</td>
                    <td class="text-right font-bold text-cyan-700">+{{ number_format($payment->amount, 0, ',', ' ') }}</td>
                    <td class="text-right font-black text-emerald-700">{{ number_format($payment->balance_after, 0, ',', ' ') }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center py-12 text-base-content/40">
                        <x-icon name="o-banknotes" class="w-10 h-10 mx-auto mb-2" />
                        <p class="text-sm">Aucun paiement sur cette période</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
            @if($journal->isNotEmpty())
            <tfoot class="bg-cyan-50">
                <tr>
                    <td colspan="5" class="py-3 px-4 font-bold text-right">Total encaissé :</td>
                    <td class="py-3 px-4 font-black text-right text-cyan-700">{{ number_format($totalAmount, 0, ',', ' ') }}</td>
                    <td class="py-3 px-4 font-black text-right text-emerald-700">{{ number_format($closingBalance, 0, ',', ' ') }}</td>
                </tr>
            </tfoot>
            @endif
        </table>
    </x-card>
</div>
