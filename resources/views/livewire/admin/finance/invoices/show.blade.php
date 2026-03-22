<?php
use App\Models\Invoice;
use App\Models\Payment;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Mail\PaymentReceivedMail;
use Illuminate\Support\Facades\Mail;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Mary\Traits\Toast;

new #[Layout('layouts.app')] class extends Component {
    use Toast;

    public Invoice $invoice;

    public function mount(string $uuid): void
    {
        $this->invoice = Invoice::where('school_id', auth()->user()->school_id)
            ->where('uuid', $uuid)
            ->with([
                'student',
                'academicYear',
                'feeSchedule',
                'enrollment.schoolClass',
                'paymentAllocations.payment',
                'school',
            ])
            ->firstOrFail();
    }

    public function cancel(): void
    {
        if (! $this->invoice->status->isCancellable()) {
            $this->error('Cette facture ne peut pas être annulée.', position: 'toast-top toast-center', icon: 'o-x-circle', css: 'alert-error', timeout: 4000);
            return;
        }
        $this->invoice->update(['status' => InvoiceStatus::CANCELLED]);
        $this->invoice->refresh();
        $this->success('Facture annulée.', position: 'toast-top toast-end', icon: 'o-x-mark', css: 'alert-success', timeout: 3000);
    }

    public function markPaid(): void
    {
        $remaining = $this->invoice->balance_due;

        $this->invoice->update([
            'status'      => InvoiceStatus::PAID,
            'paid_total'  => $this->invoice->total,
            'balance_due' => 0,
        ]);

        // Create a payment record for traceability
        $payment = Payment::create([
            'reference'      => Payment::generateReference(),
            'school_id'      => $this->invoice->school_id,
            'student_id'     => $this->invoice->student_id,
            'enrollment_id'  => $this->invoice->enrollment_id,
            'received_by'    => auth()->id(),
            'payment_method' => 'cash',
            'amount'         => $remaining,
            'payment_date'   => now()->toDateString(),
            'status'         => PaymentStatus::CONFIRMED->value,
            'confirmed_by'   => auth()->id(),
            'confirmed_at'   => now(),
            'notes'          => 'Marqué payé manuellement',
        ]);

        \App\Models\PaymentAllocation::create([
            'payment_id' => $payment->id,
            'invoice_id' => $this->invoice->id,
            'amount'     => $remaining,
        ]);

        // Send receipt to guardians
        $student = $this->invoice->student()->with('guardians')->first();
        if ($student) {
            $payment->load('paymentAllocations.invoice.academicYear', 'school', 'student');
            $student->guardians
                ->whereNotNull('email')
                ->each(fn($g) => Mail::to($g->email)->send(new PaymentReceivedMail($payment, $g)));
        }

        $this->invoice->refresh();
        $this->success('Facture marquée comme payée. Reçu envoyé aux parents.', position: 'toast-top toast-end', icon: 'o-paper-airplane', css: 'alert-success', timeout: 3000);
    }

    public function with(): array
    {
        return ['invoice' => $this->invoice];
    }
};
?>

<div>
    <x-header separator progress-indicator>
        <x-slot:title>
            <div class="flex items-center gap-2 text-sm text-base-content/60">
                <a href="{{ route('admin.finance.invoices.index') }}" wire:navigate class="hover:text-primary">Factures</a>
                <x-icon name="o-chevron-right" class="w-3 h-3"/>
                <span class="font-mono font-bold text-base-content">{{ $invoice->reference }}</span>
            </div>
        </x-slot:title>
        <x-slot:actions>
            <a href="{{ route('admin.finance.invoices.print', $invoice->uuid) }}" target="_blank"
               class="btn btn-ghost btn-sm gap-2">
                <x-icon name="o-printer" class="w-4 h-4"/> Imprimer
            </a>
            @if($invoice->status->isCancellable())
            <x-button label="Annuler" icon="o-x-circle" wire:click="cancel" wire:confirm="Annuler cette facture ?"
                      class="btn-error btn-outline btn-sm" spinner />
            @endif
            @if(in_array($invoice->status->value, ['issued','partially_paid','overdue']))
            <x-button label="Marquer payée" icon="o-check-circle" wire:click="markPaid"
                      class="btn-success btn-sm" spinner />
            @endif
        </x-slot:actions>
    </x-header>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        {{-- Invoice Main Card --}}
        <div class="lg:col-span-2 space-y-4">
            <x-card>
                {{-- Invoice Header --}}
                <div class="flex items-start justify-between mb-6">
                    <div>
                        <h2 class="text-3xl font-black font-mono text-primary">{{ $invoice->reference }}</h2>
                        <p class="text-base-content/60 mt-1">{{ $invoice->invoice_type->label() }}</p>
                        @if($invoice->installment_number)
                        <p class="text-sm text-base-content/60">Mensualité n°{{ $invoice->installment_number }}</p>
                        @endif
                    </div>
                    @php
                        $sv = $invoice->status->value;
                        $statusColors = [
                            'draft'          => 'from-gray-400 to-gray-500',
                            'issued'         => 'from-blue-500 to-blue-600',
                            'partially_paid' => 'from-orange-500 to-orange-600',
                            'paid'           => 'from-green-500 to-green-600',
                            'cancelled'      => 'from-rose-500 to-rose-600',
                            'overdue'        => 'from-red-500 to-red-600',
                        ];
                        $grad = $statusColors[$sv] ?? 'from-gray-400 to-gray-500';
                    @endphp
                    <span class="px-4 py-2 rounded-full text-white font-bold text-sm bg-linear-to-r {{ $grad }}">
                        {{ $invoice->status->label() }}
                    </span>
                </div>

                {{-- Amounts --}}
                <div class="grid grid-cols-3 gap-4 mb-6">
                    @foreach([
                        ['label' => 'Total',  'val' => $invoice->total,      'color' => 'text-purple-600'],
                        ['label' => 'Payé',   'val' => $invoice->paid_total, 'color' => 'text-green-600'],
                        ['label' => 'Solde',  'val' => $invoice->balance_due,'color' => 'text-orange-600'],
                    ] as $a)
                    <div class="p-4 bg-base-200 rounded-xl text-center">
                        <p class="text-xs text-base-content/60 mb-1">{{ $a['label'] }}</p>
                        <p class="text-2xl font-black {{ $a['color'] }}">{{ number_format($a['val']) }}</p>
                        <p class="text-xs text-base-content/40">DJF</p>
                    </div>
                    @endforeach
                </div>

                {{-- Progress bar --}}
                @if($invoice->total > 0)
                @php $pct = min(100, round(($invoice->paid_total / $invoice->total) * 100)); @endphp
                <div class="mb-6">
                    <div class="flex justify-between text-xs text-base-content/60 mb-1">
                        <span>Progression du paiement</span>
                        <span>{{ $pct }}%</span>
                    </div>
                    <div class="w-full bg-base-300 rounded-full h-3">
                        <div class="h-3 rounded-full bg-linear-to-r from-green-500 to-emerald-400 transition-all"
                             style="width: {{ $pct }}%"></div>
                    </div>
                </div>
                @endif

                {{-- Dates --}}
                <div class="grid grid-cols-2 gap-4 p-4 bg-base-200 rounded-xl">
                    <div>
                        <p class="text-xs text-base-content/60">Date d'émission</p>
                        <p class="font-bold">{{ $invoice->issue_date?->format('d/m/Y') }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-base-content/60 flex items-center gap-1">
                            Échéance
                            @if($invoice->status->value === 'overdue')
                            <x-badge value="EN RETARD" class="badge-error badge-xs" />
                            @endif
                        </p>
                        <p class="font-bold {{ $invoice->status->value === 'overdue' ? 'text-red-600' : '' }}">
                            {{ $invoice->due_date?->format('d/m/Y') }}
                        </p>
                    </div>
                </div>
            </x-card>

            {{-- Payment history --}}
            <x-card title="Historique des paiements" separator>
                @forelse($invoice->paymentAllocations as $alloc)
                <div class="flex items-center justify-between py-3 border-b border-base-200 last:border-0">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-green-100 flex items-center justify-center">
                            <x-icon name="o-banknotes" class="w-4 h-4 text-green-600"/>
                        </div>
                        <div>
                            <p class="font-mono font-bold text-sm">{{ $alloc->payment->reference }}</p>
                            <p class="text-xs text-base-content/60">
                                {{ $alloc->payment->payment_date?->format('d/m/Y') }}
                                — {{ ucfirst($alloc->payment->payment_method) }}
                            </p>
                        </div>
                    </div>
                    <span class="font-bold text-green-600">+{{ number_format($alloc->amount) }} DJF</span>
                </div>
                @empty
                <x-alert icon="o-information-circle" class="alert-info text-sm">Aucun paiement enregistré.</x-alert>
                @endforelse
            </x-card>
        </div>

        {{-- Sidebar --}}
        <div class="space-y-4">
            {{-- Student card --}}
            <x-card title="Élève" separator>
                <a href="{{ route('admin.students.show', $invoice->student->uuid) }}" wire:navigate
                   class="flex items-center gap-3 hover:bg-base-200 p-2 rounded-lg transition-colors">
                    <div class="w-12 h-12 rounded-xl bg-primary/10 flex items-center justify-center font-black text-primary">
                        {{ substr($invoice->student->name, 0, 1) }}
                    </div>
                    <div>
                        <p class="font-bold">{{ $invoice->student->full_name }}</p>
                        @if($invoice->student->student_code)
                        <p class="text-xs font-mono text-base-content/60">{{ $invoice->student->student_code }}</p>
                        @endif
                        @if($invoice->enrollment?->schoolClass)
                        <p class="text-xs text-base-content/60">{{ $invoice->enrollment->schoolClass->name }}</p>
                        @endif
                    </div>
                </a>
            </x-card>

            {{-- Invoice details --}}
            <x-card title="Détails" separator>
                <div class="space-y-2 text-sm">
                    @foreach([
                        ['label' => 'Année',        'val' => $invoice->academicYear?->name],
                        ['label' => 'Barème',       'val' => $invoice->feeSchedule?->name],
                        ['label' => 'Écheancier',   'val' => $invoice->schedule_type?->label()],
                        ['label' => 'Sous-total',   'val' => number_format($invoice->subtotal).' DJF'],
                        ['label' => 'TVA ('.$invoice->vat_rate.'%)', 'val' => number_format($invoice->vat_amount).' DJF'],
                        ['label' => 'Pénalités',    'val' => $invoice->penalty_amount > 0 ? number_format($invoice->penalty_amount).' DJF' : null],
                    ] as $row)
                    @if($row['val'])
                    <div class="flex justify-between py-1 border-b border-base-200 last:border-0">
                        <span class="text-base-content/60">{{ $row['label'] }}</span>
                        <span class="font-medium">{{ $row['val'] }}</span>
                    </div>
                    @endif
                    @endforeach
                </div>
            </x-card>
        </div>
    </div>
</div>
