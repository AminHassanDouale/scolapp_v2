<?php
use App\Models\Payment;
use App\Models\Invoice;
use App\Models\Student;
use App\Actions\RecordPaymentAction;
use App\Enums\PaymentStatus;
use App\Mail\PaymentReceivedMail;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Mail;
use Mary\Traits\Toast;

new #[Layout('layouts.app')] class extends Component {
    use Toast;

    public int    $studentId          = 0;
    public int    $invoiceId          = 0;
    public float  $amount             = 0;
    public string $method             = 'cash';
    public string $paymentDate        = '';
    public string $notes              = '';
    public bool   $confirmImmediately = true;

    // Dynamic method fields
    public string $transactionRef  = '';  // for bank_transfer & check
    public string $bankName        = '';  // for bank_transfer
    public string $mobileProvider  = '';  // for mobile_money
    public string $mobilePhone     = '';  // for mobile_money

    public function mount(): void
    {
        $this->paymentDate = now()->format('Y-m-d');
    }

    public function updatedStudentId(): void
    {
        $this->invoiceId = 0;
        $this->amount    = 0;
    }

    public function updatedInvoiceId(): void
    {
        if ($this->invoiceId) {
            $invoice = Invoice::find($this->invoiceId);
            if ($invoice) {
                $this->amount = $invoice->balance_due;
            }
        }
    }

    public function updatedMethod(): void
    {
        $this->transactionRef = '';
        $this->bankName       = '';
        $this->mobileProvider = '';
        $this->mobilePhone    = '';
    }

    public function save(): void
    {
        $rules = [
            'studentId'   => 'required|integer|min:1',
            'invoiceId'   => 'required|integer|min:1',
            'amount'      => 'required|numeric|min:1|max:' . (Invoice::find($this->invoiceId)?->balance_due ?? PHP_INT_MAX),
            'method'      => 'required|string',
            'paymentDate' => 'required|date',
        ];

        if (in_array($this->method, ['bank_transfer', 'check'])) {
            $rules['transactionRef'] = 'required|string|max:100';
        }
        if ($this->method === 'mobile_money') {
            $rules['mobileProvider'] = 'required|string';
            $rules['mobilePhone']    = 'required|string|max:30';
        }

        $this->validate($rules, [
            'amount.max' => 'Le montant ne peut pas dépasser le solde dû (:max DJF).',
        ]);

        $invoice = Invoice::findOrFail($this->invoiceId);

        $meta = [];
        if ($this->method === 'mobile_money') {
            $meta = ['provider' => $this->mobileProvider, 'phone' => $this->mobilePhone];
        }

        $paymentData = [
            'school_id'      => auth()->user()->school_id,
            'student_id'     => $this->studentId,
            'received_by'    => auth()->id(),
            'payment_method' => $this->method,
            'amount'         => $this->amount,
            'payment_date'   => $this->paymentDate,
            'notes'          => $this->notes ?: null,
            'transaction_ref'=> $this->transactionRef ?: null,
            'bank_name'      => $this->method === 'bank_transfer' ? $this->bankName : null,
            'check_number'   => $this->method === 'check' ? $this->transactionRef : null,
            'meta'           => $meta ?: null,
        ];

        $payment = app(RecordPaymentAction::class)($paymentData, [$invoice->id]);

        if ($this->confirmImmediately) {
            $payment->update([
                'status'       => PaymentStatus::CONFIRMED->value,
                'confirmed_by' => auth()->id(),
                'confirmed_at' => now(),
            ]);
        }

        // Send receipt email to guardians
        $student = $invoice->student()->with('guardians')->first();
        if ($student) {
            $student->guardians
                ->whereNotNull('email')
                ->each(fn($g) => Mail::to($g->email)->send(new PaymentReceivedMail($payment->load('paymentAllocations.invoice.academicYear', 'school', 'student'), $g)));
        }

        $this->success('Paiement enregistré et reçu envoyé.', position: 'toast-top toast-end', icon: 'o-paper-airplane', css: 'alert-success', timeout: 3000);
        $this->redirect(route('admin.finance.payments.index'), navigate: true);
    }

    public function with(): array
    {
        $schoolId = auth()->user()->school_id;

        $students = Student::where('school_id', $schoolId)
            ->orderBy('name')
            ->get()
            ->map(fn($s) => [
                'id'   => $s->id,
                'name' => $s->full_name . ($s->student_code ? ' — ' . $s->student_code : ''),
            ])
            ->all();

        $invoices = $this->studentId
            ? Invoice::where('student_id', $this->studentId)
                ->whereNotIn('status', ['paid', 'cancelled'])
                ->orderBy('due_date')
                ->get()
                ->map(fn($i) => [
                    'id'   => $i->id,
                    'name' => $i->reference . ' — Solde: ' . number_format($i->balance_due, 0, ',', ' ') . ' DJF',
                ])
                ->all()
            : [];

        $selectedInvoice = $this->invoiceId ? Invoice::find($this->invoiceId) : null;

        $methods = [
            ['id' => 'cash',          'name' => 'Espèces'],
            ['id' => 'bank_transfer', 'name' => 'Virement bancaire'],
            ['id' => 'check',         'name' => 'Chèque'],
            ['id' => 'mobile_money',  'name' => 'Mobile Money'],
        ];

        $mobileProviders = [
            ['id' => 'd_money',   'name' => 'D-Money'],
            ['id' => 'waafi',     'name' => 'Waafi'],
            ['id' => 'cac_pay',   'name' => 'CaC Pay'],
            ['id' => 'exim_bank', 'name' => 'Exim Bank Mobile'],
            ['id' => 'saba_bank', 'name' => 'Saba Bank'],
        ];

        return [
            'students'        => $students,
            'invoices'        => $invoices,
            'selectedInvoice' => $selectedInvoice,
            'methods'         => $methods,
            'mobileProviders' => $mobileProviders,
        ];
    }
};
?>

<div>
    <x-header separator progress-indicator>
        <x-slot:title>
            <div class="flex items-center gap-2 text-sm text-base-content/60">
                <a href="{{ route('admin.finance.payments.index') }}" wire:navigate class="hover:text-primary">Paiements</a>
                <x-icon name="o-chevron-right" class="w-3 h-3"/>
                <span class="text-base-content">Nouveau paiement</span>
            </div>
        </x-slot:title>
    </x-header>

    <div class="max-w-2xl mx-auto">
        <x-card separator>
            <x-slot:title>
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center">
                        <x-icon name="o-banknotes" class="w-5 h-5 text-primary"/>
                    </div>
                    <div>
                        <p class="font-bold">Enregistrer un paiement</p>
                        <p class="text-xs text-base-content/50 font-normal">Saisissez les informations du règlement</p>
                    </div>
                </div>
            </x-slot:title>

            <x-form wire:submit="save" class="space-y-6">

                {{-- Step 1: Student & Invoice --}}
                <div>
                    <p class="text-xs font-semibold text-base-content/50 uppercase tracking-wider mb-3">1 — Identification</p>
                    <div class="space-y-4">
                        <x-select
                            label="Élève *"
                            wire:model.live="studentId"
                            :options="$students"
                            option-value="id"
                            option-label="name"
                            placeholder="Choisir un élève..."
                            placeholder-value="0"
                            icon="o-academic-cap"
                            required
                        />

                        @if($studentId)
                        <x-select
                            label="Facture *"
                            wire:model.live="invoiceId"
                            :options="$invoices"
                            option-value="id"
                            option-label="name"
                            placeholder="{{ count($invoices) ? 'Choisir une facture...' : 'Aucune facture ouverte' }}"
                            placeholder-value="0"
                            :disabled="!count($invoices)"
                            icon="o-document-text"
                            required
                        />
                        @endif
                    </div>
                </div>

                {{-- Invoice summary --}}
                @if($selectedInvoice)
                <div class="bg-linear-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-xl p-4 space-y-2 text-sm">
                    <div class="flex items-center gap-2 mb-3">
                        <x-icon name="o-document-text" class="w-4 h-4 text-blue-500"/>
                        <span class="font-semibold text-blue-800">{{ $selectedInvoice->reference }}</span>
                        <span class="badge badge-sm badge-info ml-auto">{{ $selectedInvoice->status->label() }}</span>
                    </div>
                    <div class="grid grid-cols-3 gap-3">
                        <div class="text-center p-2 bg-white rounded-lg">
                            <p class="text-xs text-base-content/50">Total</p>
                            <p class="font-black text-base-content">{{ number_format($selectedInvoice->total, 0, ',', ' ') }}</p>
                            <p class="text-xs text-base-content/40">DJF</p>
                        </div>
                        <div class="text-center p-2 bg-white rounded-lg">
                            <p class="text-xs text-base-content/50">Payé</p>
                            <p class="font-black text-success">{{ number_format($selectedInvoice->paid_total, 0, ',', ' ') }}</p>
                            <p class="text-xs text-base-content/40">DJF</p>
                        </div>
                        <div class="text-center p-2 bg-white rounded-lg">
                            <p class="text-xs text-base-content/50">Solde dû</p>
                            <p class="font-black text-error">{{ number_format($selectedInvoice->balance_due, 0, ',', ' ') }}</p>
                            <p class="text-xs text-base-content/40">DJF</p>
                        </div>
                    </div>
                    @if($amount && $amount < $selectedInvoice->balance_due)
                    <div class="flex justify-between border-t border-blue-200 pt-2 text-warning text-xs">
                        <span>Solde restant après ce paiement</span>
                        <span class="font-semibold">{{ number_format($selectedInvoice->balance_due - $amount, 0, ',', ' ') }} DJF</span>
                    </div>
                    @endif
                </div>
                @endif

                <div class="divider"></div>

                {{-- Step 2: Payment details --}}
                <div>
                    <p class="text-xs font-semibold text-base-content/50 uppercase tracking-wider mb-3">2 — Détails du paiement</p>
                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <x-input label="Montant (DJF) *" wire:model.live="amount"
                                         type="number" min="1" step="1" icon="o-currency-dollar"
                                         :max="$selectedInvoice?->balance_due ?? ''" required />
                                @if($selectedInvoice && $amount > $selectedInvoice->balance_due)
                                <p class="text-xs text-error mt-1 flex items-center gap-1">
                                    <x-icon name="o-exclamation-triangle" class="w-3 h-3"/>
                                    Max : {{ number_format($selectedInvoice->balance_due, 0, ',', ' ') }} DJF
                                </p>
                                @elseif($selectedInvoice)
                                <p class="text-xs text-base-content/40 mt-1">Max : {{ number_format($selectedInvoice->balance_due, 0, ',', ' ') }} DJF</p>
                                @endif
                            </div>
                            <x-datepicker label="Date *" wire:model="paymentDate" icon="o-calendar" required
                                          :config="['dateFormat' => 'Y-m-d', 'altFormat' => 'd/m/Y', 'altInput' => true, 'locale' => ['firstDayOfWeek' => 1]]" />
                        </div>

                        {{-- Method picker --}}
                        <div>
                            <label class="label"><span class="label-text font-medium">Méthode de paiement *</span></label>
                            <div class="grid grid-cols-4 gap-2">
                                @foreach([
                                    ['cash',          'o-banknotes',      'Espèces',          'bg-emerald-50 border-emerald-400 text-emerald-700'],
                                    ['bank_transfer',  'o-building-library','Virement',         'bg-blue-50 border-blue-400 text-blue-700'],
                                    ['check',          'o-document-check', 'Chèque',           'bg-purple-50 border-purple-400 text-purple-700'],
                                    ['mobile_money',   'o-device-phone-mobile','Mobile Money', 'bg-orange-50 border-orange-400 text-orange-700'],
                                ] as [$val, $icon, $label, $activeClass])
                                <button type="button" wire:click="$set('method', '{{ $val }}')"
                                        class="flex flex-col items-center gap-1.5 p-3 rounded-xl border-2 transition-all text-xs font-semibold
                                               {{ $method === $val ? $activeClass . ' shadow-sm' : 'border-base-300 text-base-content/50 hover:border-base-400' }}">
                                    <x-icon name="{{ $icon }}" class="w-5 h-5"/>
                                    {{ $label }}
                                </button>
                                @endforeach
                            </div>
                        </div>

                        {{-- Bank transfer fields --}}
                        @if($method === 'bank_transfer')
                        <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 space-y-3">
                            <p class="text-xs font-semibold text-blue-700 flex items-center gap-1.5">
                                <x-icon name="o-building-library" class="w-3.5 h-3.5"/> Virement bancaire
                            </p>
                            <div class="grid grid-cols-2 gap-3">
                                <x-input label="Référence virement *" wire:model="transactionRef"
                                         placeholder="ex: VIR-2024-001" icon="o-hashtag" />
                                <x-input label="Banque émettrice" wire:model="bankName"
                                         placeholder="ex: BCIMR, CAC…" icon="o-building-office" />
                            </div>
                        </div>
                        @endif

                        {{-- Check fields --}}
                        @if($method === 'check')
                        <div class="bg-purple-50 border border-purple-200 rounded-xl p-4 space-y-3">
                            <p class="text-xs font-semibold text-purple-700 flex items-center gap-1.5">
                                <x-icon name="o-document-check" class="w-3.5 h-3.5"/> Paiement par chèque
                            </p>
                            <x-input label="Numéro de chèque *" wire:model="transactionRef"
                                     placeholder="ex: 000123456" icon="o-hashtag" />
                        </div>
                        @endif

                        {{-- Mobile Money fields --}}
                        @if($method === 'mobile_money')
                        <div class="bg-orange-50 border border-orange-200 rounded-xl p-4 space-y-3">
                            <p class="text-xs font-semibold text-orange-700 flex items-center gap-1.5">
                                <x-icon name="o-device-phone-mobile" class="w-3.5 h-3.5"/> Mobile Money
                            </p>
                            <div>
                                <label class="label"><span class="label-text font-medium text-sm">Opérateur *</span></label>
                                <div class="grid grid-cols-5 gap-2">
                                    @foreach($mobileProviders as $p)
                                    <button type="button" wire:click="$set('mobileProvider', '{{ $p['id'] }}')"
                                            class="flex flex-col items-center gap-1 p-2.5 rounded-xl border-2 transition-all text-xs font-semibold
                                                   {{ $mobileProvider === $p['id'] ? 'border-orange-400 bg-orange-100 text-orange-700 shadow-sm' : 'border-base-300 text-base-content/60 hover:border-orange-300' }}">
                                        <x-icon name="o-signal" class="w-4 h-4"/>
                                        {{ $p['name'] }}
                                    </button>
                                    @endforeach
                                </div>
                            </div>
                            <x-input label="Numéro de téléphone *" wire:model="mobilePhone"
                                     placeholder="ex: 77 XX XX XX" icon="o-phone" type="tel" />
                        </div>
                        @endif

                        <x-textarea label="Notes" wire:model="notes" rows="2"
                                    placeholder="Informations complémentaires..." />

                        <div class="flex items-center gap-3 p-3 bg-base-200 rounded-xl">
                            <x-checkbox wire:model="confirmImmediately" />
                            <div>
                                <p class="text-sm font-medium">Confirmer immédiatement</p>
                                <p class="text-xs text-base-content/50">Le paiement sera validé sans passer en attente</p>
                            </div>
                        </div>
                    </div>
                </div>

                <x-slot:actions>
                    <x-button label="Annuler" :link="route('admin.finance.payments.index')"
                              class="btn-ghost" wire:navigate />
                    <x-button label="Enregistrer & envoyer reçu" type="submit" icon="o-paper-airplane"
                              class="btn-primary" spinner="save" />
                </x-slot:actions>
            </x-form>
        </x-card>
    </div>
</div>
