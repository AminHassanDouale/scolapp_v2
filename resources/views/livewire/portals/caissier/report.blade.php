<?php
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Mary\Traits\Toast;
use App\Models\Payment;
use App\Models\Invoice;
use Carbon\Carbon;

new #[Layout('layouts.caissier')] class extends Component {
    use Toast;

    public string $reportDate = '';
    public string $reportType = 'daily';

    public function mount(): void
    {
        $this->reportDate = today()->format('Y-m-d');
    }

    public function with(): array
    {
        $schoolId = auth()->user()->school_id;
        $date     = Carbon::parse($this->reportDate);

        $query = Payment::where('school_id', $schoolId);

        if ($this->reportType === 'daily') {
            $query->whereDate('payment_date', $date);
            $periodLabel = $date->format('d/m/Y');
        } elseif ($this->reportType === 'monthly') {
            $query->whereMonth('payment_date', $date->month)->whereYear('payment_date', $date->year);
            $periodLabel = $date->format('F Y');
        } else {
            $query->whereYear('payment_date', $date->year);
            $periodLabel = $date->format('Y');
        }

        $payments = $query->with(['student', 'enrollment.schoolClass'])
            ->orderByDesc('payment_date')
            ->get();

        $totalAmount  = $payments->sum('amount');
        $countByMethod = $payments->groupBy('payment_method')->map->sum('amount');

        $methods = [
            'cash'          => 'Espèces',
            'bank_transfer' => 'Virement',
            'check'         => 'Chèque',
            'mobile_money'  => 'Mobile Money',
        ];

        return compact('payments', 'totalAmount', 'countByMethod', 'methods', 'periodLabel');
    }
};
?>

<div class="p-4 lg:p-6 space-y-6">
    <x-header title="{{ __('navigation.daily_report') }}" subtitle="Rapport d'encaissement" separator>
        <x-slot:actions>
            <x-button icon="o-arrow-left" link="{{ route('caissier.dashboard') }}" wire:navigate class="btn-ghost btn-sm" />
        </x-slot:actions>
    </x-header>

    {{-- Filters --}}
    <x-card shadow class="border-0">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <x-select label="Période" wire:model.live="reportType" :options="[
                ['id' => 'daily',   'name' => 'Journalier'],
                ['id' => 'monthly', 'name' => 'Mensuel'],
                ['id' => 'yearly',  'name' => 'Annuel'],
            ]" />
            <x-input type="date" label="Date de référence" wire:model.live="reportDate" />
        </div>
    </x-card>

    {{-- Summary --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <x-card class="col-span-2 border-0 shadow-sm bg-gradient-to-br from-cyan-50 to-white">
            <p class="text-xs text-base-content/60 mb-1">Total encaissé · {{ $periodLabel }}</p>
            <p class="text-3xl font-black text-cyan-700">{{ number_format($totalAmount, 0, ',', ' ') }} DJF</p>
            <p class="text-sm text-base-content/50 mt-1">{{ $payments->count() }} paiement(s)</p>
        </x-card>

        @foreach($countByMethod as $method => $amount)
        <x-card class="border-0 shadow-sm">
            <p class="text-xs text-base-content/60 mb-1">{{ $methods[$method] ?? $method }}</p>
            <p class="text-xl font-black text-teal-700">{{ number_format($amount, 0, ',', ' ') }}</p>
        </x-card>
        @endforeach
    </div>

    {{-- Payments table --}}
    <x-card shadow class="border-0 p-0 overflow-hidden">
        <div class="p-4 border-b border-base-200">
            <h3 class="font-bold">Détail des encaissements</h3>
        </div>
        <table class="table table-sm w-full">
            <thead class="bg-base-200">
                <tr>
                    <th>Heure</th>
                    <th>Élève</th>
                    <th>Classe</th>
                    <th>Mode</th>
                    <th class="text-right">Montant</th>
                </tr>
            </thead>
            <tbody>
                @forelse($payments as $payment)
                <tr class="hover border-b border-base-100">
                    <td class="text-sm text-base-content/60 font-mono">{{ $payment->payment_date?->format('H:i') }}</td>
                    <td class="font-medium">{{ $payment->student?->full_name }}</td>
                    <td class="text-sm">{{ $payment->enrollment?->schoolClass?->name ?? '—' }}</td>
                    <td>
                        @php $badge = match($payment->payment_method) {
                            'cash' => 'badge-success', 'bank_transfer' => 'badge-info',
                            'check' => 'badge-warning', 'mobile_money' => 'badge-secondary',
                            default => 'badge-ghost'
                        }; @endphp
                        <x-badge :value="$methods[$payment->payment_method] ?? $payment->payment_method" class="{{ $badge }} badge-sm" />
                    </td>
                    <td class="text-right font-bold text-cyan-700">{{ number_format($payment->amount, 0, ',', ' ') }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="text-center py-12 text-base-content/40">
                        <x-icon name="o-banknotes" class="w-10 h-10 mx-auto mb-2" />
                        <p class="text-sm">Aucun paiement sur cette période</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
            @if($payments->isNotEmpty())
            <tfoot class="bg-cyan-50">
                <tr>
                    <td colspan="4" class="py-3 px-4 font-bold text-right">Total :</td>
                    <td class="py-3 px-4 font-black text-right text-cyan-700">{{ number_format($totalAmount, 0, ',', ' ') }} DJF</td>
                </tr>
            </tfoot>
            @endif
        </table>
    </x-card>
</div>
