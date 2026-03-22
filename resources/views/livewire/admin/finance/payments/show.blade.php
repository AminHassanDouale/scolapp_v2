<?php
use App\Models\Payment;
use App\Enums\PaymentStatus;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Mary\Traits\Toast;

new #[Layout('layouts.app')] class extends Component {
    use Toast;

    public Payment $payment;

    public function mount(string $uuid): void
    {
        $this->payment = Payment::where('school_id', auth()->user()->school_id)
            ->where('uuid', $uuid)
            ->with(['student', 'paymentAllocations.invoice.academicYear'])
            ->firstOrFail();
    }

    public function confirmPayment(): void
    {
        $this->payment->update([
            'status'       => PaymentStatus::CONFIRMED->value,
            'confirmed_by' => auth()->id(),
            'confirmed_at' => now(),
        ]);
        $this->payment->refresh();
        $this->success('Paiement confirmé.', position: 'toast-top toast-end', icon: 'o-banknotes', css: 'alert-success', timeout: 3000);
    }

    public function cancelPayment(): void
    {
        $this->payment->update(['status' => PaymentStatus::Cancelled->value]);
        $this->payment->refresh();
        $this->success('Paiement annulé.', position: 'toast-top toast-end', icon: 'o-x-mark', css: 'alert-success', timeout: 3000);
    }

    public function with(): array { return []; }
};
?>

<div>
    <x-header separator progress-indicator>
        <x-slot:title>
            <div class="flex items-center gap-2 text-sm text-base-content/60">
                <a href="{{ route('admin.finance.payments.index') }}" wire:navigate class="hover:text-primary">Paiements</a>
                <x-icon name="o-chevron-right" class="w-3 h-3"/>
                <span class="text-base-content font-mono">{{ $payment->reference }}</span>
            </div>
        </x-slot:title>
        <x-slot:actions>
            @if($payment->status === PaymentStatus::PENDING)
            <x-button label="Confirmer" icon="o-check"
                      wire:click="confirmPayment"
                      wire:confirm="Confirmer ce paiement ?"
                      class="btn-success" spinner />
            <x-button label="Annuler" icon="o-x-mark"
                      wire:click="cancelPayment"
                      wire:confirm="Annuler ce paiement ?"
                      class="btn-error btn-outline" />
            @endif
        </x-slot:actions>
    </x-header>

    @php
        $statusClass = match($payment->status) {
            PaymentStatus::CONFIRMED => 'alert-success',
            PaymentStatus::PENDING   => 'alert-warning',
            PaymentStatus::CANCELLED => 'alert-error',
            PaymentStatus::REFUNDED  => 'alert-info',
            default => '',
        };
        $methodLabel = match($payment->payment_method ?? '') {
            'cash'          => 'Espèces',
            'bank_transfer' => 'Virement bancaire',
            'check'         => 'Chèque',
            'mobile_money'  => 'Mobile Money',
            default         => $payment->payment_method ?? '—',
        };
        $provider = $payment->meta['provider'] ?? null;
        $providerLabel = match($provider) {
            'd_money'   => 'D-Money',
            'waafi'     => 'Waafi',
            'cac_pay'   => 'CaC Pay',
            'exim_bank' => 'Exim Bank Mobile',
            'saba_bank' => 'Saba Bank',
            default     => $provider,
        };
    @endphp

    <x-alert icon="o-banknotes" class="{{ $statusClass }} mb-4">
        <strong>{{ $payment->status->label() }}</strong>
        @if($payment->confirmed_at)
        — Confirmé le {{ \Carbon\Carbon::parse($payment->confirmed_at)->format('d/m/Y à H:i') }}
        @endif
    </x-alert>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Main --}}
        <div class="lg:col-span-2 space-y-4">
            <x-card title="Détails du paiement" separator>
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <p class="text-base-content/50 mb-1">Référence</p>
                        <p class="font-mono font-bold text-lg">{{ $payment->reference }}</p>
                    </div>
                    <div>
                        <p class="text-base-content/50 mb-1">Montant</p>
                        <p class="font-bold text-xl text-primary">{{ number_format($payment->amount, 0, ',', ' ') }} DJF</p>
                    </div>
                    <div>
                        <p class="text-base-content/50 mb-1">Méthode</p>
                        <p class="font-semibold">{{ $methodLabel }}</p>
                    </div>
                    <div>
                        <p class="text-base-content/50 mb-1">Date du paiement</p>
                        <p class="font-semibold">{{ $payment->payment_date?->format('d/m/Y') ?? $payment->payment_date }}</p>
                    </div>

                    {{-- Bank transfer details --}}
                    @if($payment->transaction_ref)
                    <div>
                        <p class="text-base-content/50 mb-1">Référence transaction</p>
                        <p class="font-mono font-semibold">{{ $payment->transaction_ref }}</p>
                    </div>
                    @endif
                    @if($payment->bank_name)
                    <div>
                        <p class="text-base-content/50 mb-1">Banque</p>
                        <p class="font-semibold">{{ $payment->bank_name }}</p>
                    </div>
                    @endif

                    {{-- Mobile money details --}}
                    @if($providerLabel)
                    <div>
                        <p class="text-base-content/50 mb-1">Opérateur</p>
                        <p class="font-semibold">{{ $providerLabel }}</p>
                    </div>
                    @endif
                    @if(!empty($payment->meta['phone']))
                    <div>
                        <p class="text-base-content/50 mb-1">Téléphone</p>
                        <p class="font-semibold">{{ $payment->meta['phone'] }}</p>
                    </div>
                    @endif

                    @if($payment->notes)
                    <div class="col-span-2">
                        <p class="text-base-content/50 mb-1">Notes</p>
                        <p class="font-semibold">{{ $payment->notes }}</p>
                    </div>
                    @endif
                    <div>
                        <p class="text-base-content/50 mb-1">Date d'enregistrement</p>
                        <p class="font-semibold">{{ $payment->created_at->format('d/m/Y H:i') }}</p>
                    </div>
                </div>
            </x-card>

            @if($payment->paymentAllocations->count())
            <x-card title="Factures réglées" separator>
                <div class="space-y-2">
                    @foreach($payment->paymentAllocations as $alloc)
                    <div class="flex items-center justify-between p-3 bg-base-200/50 rounded-xl">
                        <div>
                            <a href="{{ route('admin.finance.invoices.show', $alloc->invoice->uuid) }}"
                               wire:navigate class="font-mono font-bold text-sm hover:text-primary">
                                {{ $alloc->invoice?->reference }}
                            </a>
                            <p class="text-xs text-base-content/60">
                                {{ $alloc->invoice?->invoice_type?->label() }}
                                @if($alloc->invoice?->academicYear) — {{ $alloc->invoice->academicYear->name }}@endif
                            </p>
                        </div>
                        <p class="font-bold text-success">{{ number_format($alloc->amount, 0, ',', ' ') }} DJF</p>
                    </div>
                    @endforeach
                </div>
            </x-card>
            @endif
        </div>

        {{-- Sidebar --}}
        <div class="space-y-4">
            <x-card title="Élève">
                @if($payment->student)
                <a href="{{ route('admin.students.show', $payment->student->uuid) }}"
                   wire:navigate class="flex items-center gap-3 hover:bg-base-200 p-2 rounded-lg transition-colors">
                    <div class="w-12 h-12 rounded-full bg-primary/10 flex items-center justify-center font-black text-primary text-lg">
                        {{ substr($payment->student->name, 0, 1) }}
                    </div>
                    <div>
                        <p class="font-bold">{{ $payment->student->full_name }}</p>
                        @if($payment->student->student_code)
                        <p class="text-xs font-mono text-base-content/60">{{ $payment->student->student_code }}</p>
                        @endif
                        <p class="text-xs text-primary">Voir le profil →</p>
                    </div>
                </a>
                @else
                <p class="text-sm text-base-content/50">—</p>
                @endif
            </x-card>
        </div>
    </div>
</div>
