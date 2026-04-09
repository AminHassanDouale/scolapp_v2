<?php
use App\Models\Payment;
use App\Enums\PaymentStatus;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Mary\Traits\Toast;
use Illuminate\Support\Carbon;

new #[Layout('layouts.app')] class extends Component {
    use Toast, WithPagination;

    public string $search       = '';
    public string $statusFilter = '';
    public string $dateFrom     = '';
    public string $dateTo       = '';
    public bool   $showFilters  = false;
    public string $sortBy       = 'created_at';
    public string $sortDir      = 'desc';
    public string $providerFilter = '';

    public function updatingSearch(): void { $this->resetPage(); }

    public function confirmPayment(int $id): void
    {
        Payment::findOrFail($id)->update([
            'status'       => PaymentStatus::CONFIRMED->value,
            'confirmed_by' => auth()->id(),
            'confirmed_at' => now(),
        ]);
        $this->success('Paiement confirmé.', position: 'toast-top toast-end', icon: 'o-banknotes', css: 'alert-success', timeout: 3000);
    }

    public function with(): array
    {
        $schoolId = auth()->user()->school_id;

        $payments = Payment::where('school_id', $schoolId)
            ->with(['student'])
            ->when($this->search, fn($q) =>
                $q->where('reference', 'like', "%{$this->search}%")
                  ->orWhereHas('student', fn($s) =>
                      $s->where('name', 'like', "%{$this->search}%")
                  )
            )
            ->when($this->statusFilter,   fn($q) => $q->where('status', $this->statusFilter))
            ->when($this->providerFilter, fn($q) => $q->where('meta->provider', $this->providerFilter))
            ->when($this->dateFrom, fn($q) => $q->whereDate('payment_date', '>=', $this->dateFrom))
            ->when($this->dateTo,   fn($q) => $q->whereDate('payment_date', '<=', $this->dateTo))
            ->orderBy($this->sortBy, $this->sortDir)
            ->paginate(20);

        // Stats
        $totalConfirmed = Payment::where('school_id', $schoolId)
            ->where('status', PaymentStatus::CONFIRMED->value)->sum('amount');
        $totalPending = Payment::where('school_id', $schoolId)
            ->where('status', PaymentStatus::PENDING->value)->sum('amount');
        $todayTotal = Payment::where('school_id', $schoolId)
            ->whereDate('payment_date', Carbon::today())->sum('amount');

        return [
            'payments'       => $payments,
            'totalConfirmed' => $totalConfirmed,
            'totalPending'   => $totalPending,
            'todayTotal'     => $todayTotal,
            'statusOptions'   => collect(PaymentStatus::cases())->map(fn($s) => ['id' => $s->value, 'name' => $s->label()])->all(),
            'providerOptions' => [
                ['id' => '',         'name' => 'Tous les opérateurs'],
                ['id' => 'd_money',  'name' => 'D-Money'],
                ['id' => 'waafi',    'name' => 'Waafi'],
                ['id' => 'cac_pay',  'name' => 'Cac Pay'],
                ['id' => 'exim_pay', 'name' => 'Exim Pay'],
                ['id' => 'saba_pay', 'name' => 'Saba Pay'],
                ['id' => 'e_dahab',  'name' => 'E-Dahab'],
            ],
        ];
    }
};
?>

<div>
    <x-header title="Paiements" separator progress-indicator>
        <x-slot:actions>
            <x-button label="Enregistrer un paiement" icon="o-plus"
                      :link="route('admin.finance.payments.create')"
                      class="btn-primary" wire:navigate />
        </x-slot:actions>
    </x-header>

    {{-- Stats --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="rounded-2xl bg-gradient-to-br from-success to-success/70 p-4 text-success-content">
            <p class="text-sm opacity-80">Confirmés</p>
            <p class="text-2xl font-black mt-1">{{ number_format($totalConfirmed, 0, ',', ' ') }}</p>
            <p class="text-xs opacity-70">DJF</p>
        </div>
        <div class="rounded-2xl bg-gradient-to-br from-warning to-warning/70 p-4 text-warning-content">
            <p class="text-sm opacity-80">En attente</p>
            <p class="text-2xl font-black mt-1">{{ number_format($totalPending, 0, ',', ' ') }}</p>
            <p class="text-xs opacity-70">DJF</p>
        </div>
        <div class="rounded-2xl bg-gradient-to-br from-primary to-primary/70 p-4 text-primary-content">
            <p class="text-sm opacity-80">Aujourd'hui</p>
            <p class="text-2xl font-black mt-1">{{ number_format($todayTotal, 0, ',', ' ') }}</p>
            <p class="text-xs opacity-70">DJF</p>
        </div>
        <div class="rounded-2xl bg-base-200 p-4">
            <p class="text-sm text-base-content/60">Total (page)</p>
            <p class="text-2xl font-black mt-1">{{ $payments->total() }}</p>
            <p class="text-xs text-base-content/50">paiement(s)</p>
        </div>
    </div>

    {{-- Toolbar --}}
    <div class="flex items-center gap-3 mb-4">
        <x-input wire:model.live.debounce="search" placeholder="Rechercher (réf, élève)..."
                 icon="o-magnifying-glass" clearable class="flex-1" />
        <x-button icon="o-adjustments-horizontal" wire:click="$set('showFilters', true)"
                  class="btn-outline" tooltip="Filtres" />
    </div>

    {{-- Table --}}
    <x-card>
        <div class="overflow-x-auto"><table class="table w-full">
            <thead><tr>
                <th>Référence</th>
                <th>Élève</th>
                <th class="text-right">Montant</th>
                <th>Méthode</th>
                <th>Statut</th>
                <th>Date</th>
                <th class="w-20">Actions</th>
            </tr></thead><tbody>

            @forelse($payments as $payment)
            @php
                $statusClass = match($payment->status->value ?? '') {
                    'confirmed' => 'badge-success',
                    'pending'   => 'badge-warning',
                    'cancelled' => 'badge-ghost',
                    'refunded'  => 'badge-info',
                    default     => 'badge-ghost',
                };
                $provider = $payment->meta['provider'] ?? null;
                $methodLabel = match($payment->payment_method ?? '') {
                    'cash'          => 'Espèces',
                    'bank_transfer' => 'Virement',
                    'check'         => 'Chèque',
                    'mobile_money'  => match($provider) {
                        'd_money'  => 'D-Money',
                        'waafi'    => 'Waafi',
                        'cac_pay'  => 'Cac Pay',
                        'exim_pay' => 'Exim Pay',
                        'saba_pay' => 'Saba Pay',
                        'e_dahab'  => 'E-Dahab',
                        default    => 'Mobile Money',
                    },
                    default         => $payment->payment_method ?? '—',
                };
                $methodColor = match($provider ?? '') {
                    'd_money'  => 'bg-emerald-100 text-emerald-700',
                    'waafi'    => 'bg-green-100 text-green-700',
                    'cac_pay'  => 'bg-red-100 text-red-700',
                    'exim_pay' => 'bg-blue-100 text-blue-700',
                    'saba_pay' => 'bg-orange-100 text-orange-700',
                    'e_dahab'  => 'bg-yellow-100 text-yellow-700',
                    default    => 'bg-base-200 text-base-content/60',
                };
            @endphp
            <tr wire:key="payment-{{ $payment->id }}" class="hover">
                <td>
                    <a href="{{ route('admin.finance.payments.show', $payment->uuid) }}"
                       wire:navigate class="font-mono font-bold hover:text-primary text-sm">
                        {{ $payment->reference }}
                    </a>
                </td>
                <td class="font-semibold text-sm">{{ $payment->student?->full_name }}</td>
                <td class="text-right font-bold">{{ number_format($payment->amount, 0, ',', ' ') }} DJF</td>
                <td>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-bold {{ $methodColor }}">
                        {{ $methodLabel }}
                    </span>
                </td>
                <td><x-badge :value="$payment->status->label()" class="{{ $statusClass }} badge-sm" /></td>
                <td class="text-sm">{{ $payment->payment_date instanceof \Illuminate\Support\Carbon ? $payment->payment_date->format('d/m/Y') : $payment->payment_date }}</td>
                <td>
                    <div class="flex gap-1">
                        <x-button icon="o-eye" :link="route('admin.finance.payments.show', $payment->uuid)"
                                  class="btn-ghost btn-xs" wire:navigate />
                        @if($payment->status === PaymentStatus::PENDING)
                        <x-button icon="o-check" wire:click="confirmPayment({{ $payment->id }})"
                                  wire:confirm="Confirmer ce paiement ?"
                                  class="btn-ghost btn-xs text-success" tooltip="Confirmer" />
                        @endif
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" class="text-center py-12 text-base-content/40">
                    <x-icon name="o-banknotes" class="w-12 h-12 mx-auto mb-2 opacity-20" />
                    <p>Aucun paiement trouvé</p>
                </td>
            </tr>
            @endforelse
        </tbody></table></div>
        <div class="mt-4">{{ $payments->links() }}</div>
    </x-card>

    {{-- Filters drawer --}}
    <x-drawer wire:model="showFilters" title="Filtres" position="right" class="w-72">
        <div class="p-4 space-y-4">
            <x-select label="Statut" wire:model.live="statusFilter"
                      :options="$statusOptions" option-value="id" option-label="name"
                      placeholder="Tous" placeholder-value="" />
            <x-select label="Opérateur" wire:model.live="providerFilter"
                      :options="$providerOptions" option-value="id" option-label="name" />
            <x-datepicker label="Du" wire:model.live="dateFrom" icon="o-calendar" :config="['dateFormat' => 'Y-m-d', 'altFormat' => 'd/m/Y', 'altInput' => true, 'locale' => ['firstDayOfWeek' => 1]]" />
            <x-datepicker label="Au" wire:model.live="dateTo" icon="o-calendar" :config="['dateFormat' => 'Y-m-d', 'altFormat' => 'd/m/Y', 'altInput' => true, 'locale' => ['firstDayOfWeek' => 1]]" />
        </div>
        <x-slot:actions>
            <x-button label="Réinitialiser"
                      wire:click="$set('statusFilter',''); $set('providerFilter',''); $set('dateFrom',''); $set('dateTo',''); $set('showFilters',false)"
                      class="btn-ghost" />
            <x-button label="Fermer" wire:click="$set('showFilters', false)" class="btn-primary" />
        </x-slot:actions>
    </x-drawer>
</div>
