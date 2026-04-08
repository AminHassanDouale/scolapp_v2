<?php
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Mary\Traits\Toast;
use App\Models\AcademicYear;
use App\Models\DmoneyTransaction;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\School;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Actions\SyncDmoneyPayment;
use App\Services\BillingApiService;
use Illuminate\Support\Facades\Log;

new #[Layout('layouts.guardian')] class extends Component {
    use Toast;

    public ?string $studentUuid     = null;
    public ?int    $academicYearId  = null;
    public string  $statusFilter    = '';
    public bool    $showPayModal    = false;
    public ?int    $payInvoiceId    = null;
    public string  $payMethod       = 'waafi';
    public float   $payAmount       = 0;


    #[Rule('required|string|min:4|max:100')]
    public string $payTransactionRef = '';

    #[Rule('required|string|min:8|max:20')]
    public string $payPhone = '';

    public function mount(?string $student = null): void
    {
        $this->studentUuid = $student;
        $this->syncPendingDmoney();
    }

    public function resetFilters(): void
    {
        $this->academicYearId = null;
        $this->statusFilter   = '';
        $this->studentUuid    = null;
    }

    public function openPayModal(int $invoiceId): void
    {
        $invoice = Invoice::find($invoiceId);
        if (! $invoice || $invoice->status === InvoiceStatus::PAID || $invoice->status === InvoiceStatus::CANCELLED) {
            return;
        }
        $this->payInvoiceId      = $invoiceId;
        $this->payAmount         = (float) $invoice->balance_due;
        $this->payTransactionRef = '';
        $this->payPhone          = '';
        $this->payMethod         = 'waafi';
        $this->showPayModal      = true;
        $this->resetErrorBag();
    }

    public function closePayModal(): void
    {
        $this->showPayModal = false;
        $this->reset(['payInvoiceId', 'payTransactionRef', 'payPhone', 'payAmount']);
        $this->resetErrorBag();
    }

    // ── D-Money Polling ────────────────────────────────────────────────────────

    public function syncPendingDmoney(): void
    {
        $guardian   = Guardian::where('user_id', auth()->id())->with('students')->first();
        $studentIds = $guardian?->students->pluck('id') ?? collect();

        $pending = DmoneyTransaction::whereIn('student_id', $studentIds)
            ->pending()
            ->get();

        if ($pending->isEmpty()) {
            return;
        }

        $syncer = app(SyncDmoneyPayment::class);
        foreach ($pending as $tx) {
            try {
                $syncer->handle($tx->order_id);
            } catch (\Throwable) {
                // silent — will retry on next poll
            }
        }
    }

    // ── D-Money Online Payment ─────────────────────────────────────────────────

    public function initiateDMoney(int $invoiceId): void
    {
        $invoice = Invoice::find($invoiceId);

        if (! $invoice || in_array($invoice->status, [InvoiceStatus::PAID, InvoiceStatus::CANCELLED])) {
            $this->error('Facture non disponible.', position: 'toast-top toast-center');
            return;
        }

        if ($invoice->balance_due <= 0) {
            $this->error('Aucun montant dû sur cette facture.', position: 'toast-top toast-center');
            return;
        }

        // Block if a pending D-Money transaction already exists for this invoice
        $existing = DmoneyTransaction::where('invoice_id', $invoiceId)
            ->where('status', 'pending')
            ->exists();

        if ($existing) {
            $this->warning(
                'Un paiement D-Money est déjà en cours pour cette facture.',
                'Veuillez attendre la confirmation.',
                position: 'toast-top toast-center',
                timeout: 5000
            );
            return;
        }

        try {
            $orderId    = 'SCL' . str_pad((string) $invoice->id, 6, '0', STR_PAD_LEFT) . strtoupper(substr(uniqid(), -4));
            $title      = 'Facture ' . $invoice->reference;
            $redirectUrl = route('guardian.dmoney.success');

            /** @var BillingApiService $billing */
            $billing = app(BillingApiService::class);
            $result  = $billing->createInvoicePayment(
                (int) $invoice->balance_due,
                $title,
                $orderId,
                $redirectUrl
            );

            DmoneyTransaction::create([
                'school_id'              => $invoice->school_id,
                'invoice_id'             => $invoice->id,
                'student_id'             => $invoice->student_id,
                'user_id'                => auth()->id(),
                'billing_subscription_id'=> (string) ($result['prepay_id'] ?? ''),
                'order_id'               => $result['order_id'],
                'checkout_url'           => $result['checkout_url'],
                'amount'                 => (int) $invoice->balance_due,
                'currency'               => 'DJF',
                'status'                 => 'pending',
            ]);

            $this->redirect($result['checkout_url']);

        } catch (\Throwable $e) {
            Log::error('D-Money payment initiation failed', [
                'invoice_id' => $invoiceId,
                'error'      => $e->getMessage(),
            ]);
            $this->error(
                'Paiement D-Money indisponible',
                'Veuillez réessayer ou contacter le support.',
                position: 'toast-top toast-center',
                timeout: 6000
            );
        }
    }

    // ── Manual Payment ─────────────────────────────────────────────────────────

    public function submitPayment(): void
    {
        $this->validate([
            'payTransactionRef' => 'required|string|min:4|max:100',
            'payPhone'          => 'required|string|min:8|max:20',
            'payMethod'         => 'required|in:waafi,cac_pay,e_dahab',
        ]);

        $invoice = Invoice::with('enrollment')->find($this->payInvoiceId);

        if (! $invoice || in_array($invoice->status, [InvoiceStatus::PAID, InvoiceStatus::CANCELLED])) {
            $this->error('Facture non disponible.', position: 'toast-top toast-center');
            return;
        }

        // Prevent duplicate transaction ref
        if (Payment::where('transaction_ref', $this->payTransactionRef)->exists()) {
            $this->addError('payTransactionRef', 'Cette référence de transaction a déjà été soumise.');
            return;
        }

        $methodLabels = [
            'd_money' => 'D-Money',
            'waafi'   => 'Waafi',
            'cac_pay' => 'Cac Pay',
            'e_dahab' => 'E-Dahab',
        ];

        $payment = Payment::create([
            'school_id'       => $invoice->school_id,
            'student_id'      => $invoice->student_id,
            'enrollment_id'   => $invoice->enrollment_id,
            'status'          => PaymentStatus::PENDING->value,
            'payment_method'  => 'mobile_money',
            'amount'          => $this->payAmount,
            'payment_date'    => now()->toDateString(),
            'transaction_ref' => $this->payTransactionRef,
            'notes'           => 'Paiement en ligne — ' . ($methodLabels[$this->payMethod] ?? $this->payMethod),
            'meta'            => [
                'channel'  => 'guardian_portal',
                'provider' => $this->payMethod,
                'phone'    => $this->payPhone,
            ],
        ]);

        PaymentAllocation::create([
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'amount'     => $this->payAmount,
        ]);

        $this->showPayModal = false;
        $this->reset(['payInvoiceId', 'payTransactionRef', 'payPhone', 'payAmount']);

        $this->success(
            'Paiement soumis avec succès — en attente de confirmation',
            'Réf : ' . $payment->reference,
            position: 'toast-top toast-end',
            icon: 'o-clock',
            css: 'alert-info',
            timeout: 6000
        );
    }

    public function with(): array
    {
        $guardian   = Guardian::where('user_id', auth()->id())->with('students')->first();
        $students   = $guardian?->students ?? collect();
        $studentIds = $students->pluck('id');

        $baseQuery = Invoice::whereHas('enrollment.student', fn($q) => $q->whereIn('id', $studentIds));

        $invoices = (clone $baseQuery)
            ->when($this->studentUuid,    fn($q) => $q->whereHas('enrollment.student', fn($q2) => $q2->where('uuid', $this->studentUuid)))
            ->when($this->academicYearId, fn($q) => $q->where('academic_year_id', $this->academicYearId))
            ->when($this->statusFilter,   fn($q) => $q->where('status', $this->statusFilter))
            ->with(['enrollment.student', 'enrollment.schoolClass', 'payments'])
            ->orderByDesc('due_date')
            ->paginate(20);

        $totalUnpaid = (clone $baseQuery)
            ->whereIn('status', [InvoiceStatus::ISSUED->value, InvoiceStatus::OVERDUE->value, InvoiceStatus::PARTIALLY_PAID->value])
            ->sum('balance_due');

        // Academic years that have invoices for this guardian's students
        $academicYears = AcademicYear::whereHas('invoices', fn($q) => $q->whereIn('student_id', $studentIds))
            ->where('school_id', auth()->user()->school_id)
            ->orderByDesc('start_date')
            ->get(['id', 'name']);

        $statusOptions = collect(InvoiceStatus::cases())->map(fn($s) => [
            'id'   => $s->value,
            'name' => $s->label(),
        ])->prepend(['id' => '', 'name' => 'Tous les statuts']);

        $academicYearOptions = $academicYears->map(fn($y) => [
            'id'   => $y->id,
            'name' => $y->name,
        ])->prepend(['id' => '', 'name' => 'Toutes les années']);

        $school = School::find(auth()->user()->school_id);

        $filtersActive = $this->academicYearId || $this->statusFilter || $this->studentUuid;

        $hasPendingDmoney = \App\Models\DmoneyTransaction::whereIn('invoice_id', $invoices->pluck('id'))
            ->where('status', 'pending')
            ->exists();

        return compact('students', 'invoices', 'totalUnpaid', 'school', 'academicYears', 'statusOptions', 'academicYearOptions', 'filtersActive', 'hasPendingDmoney');
    }
};
?>

<div class="p-4 lg:p-6 space-y-6"
     wire:poll.30000ms
     x-data="{ pending: {{ $hasPendingDmoney ? 'true' : 'false' }}, fastPoll: null }"
     x-init="if (pending) { fastPoll = setInterval(() => $wire.syncPendingDmoney(), 6000) }"
     x-effect="if (!pending && fastPoll) { clearInterval(fastPoll); fastPoll = null }">

    <x-header title="Mes Factures" subtitle="Suivi des paiements" separator>
        <x-slot:actions>
            <x-button icon="o-arrow-left" link="{{ route('guardian.dashboard') }}" wire:navigate class="btn-ghost btn-sm" />
        </x-slot:actions>
    </x-header>

    {{-- Unpaid alert --}}
    @if($totalUnpaid > 0)
    <x-alert icon="o-exclamation-triangle" class="alert-warning">
        <div class="flex items-center justify-between w-full flex-wrap gap-2">
            <span>Solde total impayé : <strong>{{ number_format($totalUnpaid, 0, ',', ' ') }} DJF</strong></span>
            <span class="text-xs opacity-70">Mis à jour automatiquement toutes les 30 secondes</span>
        </div>
    </x-alert>
    @endif

    {{-- Filters --}}
    <x-card shadow class="border-0 p-3">
        <div class="flex flex-wrap gap-3 items-end">

            {{-- Student filter (only if multiple children) --}}
            @if($students->count() > 1)
            <div class="flex-1 min-w-32">
                <label class="text-xs font-semibold text-base-content/50 uppercase tracking-wider mb-1 block">Enfant</label>
                <div class="flex gap-1.5 flex-wrap">
                    <button wire:click="$set('studentUuid', null)"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-all
                            {{ !$studentUuid ? 'bg-success text-white shadow-sm' : 'bg-base-200 text-base-content/60 hover:bg-base-300' }}">
                        Tous
                    </button>
                    @foreach($students as $s)
                    <button wire:click="$set('studentUuid', '{{ $s->uuid }}')"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-all
                            {{ $studentUuid === $s->uuid ? 'bg-success text-white shadow-sm' : 'bg-base-200 text-base-content/60 hover:bg-base-300' }}">
                        {{ $s->full_name }}
                    </button>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Academic year --}}
            @if($academicYears->count() > 0)
            <div class="min-w-40">
                <label class="text-xs font-semibold text-base-content/50 uppercase tracking-wider mb-1 block">Année scolaire</label>
                <x-select wire:model.live="academicYearId" :options="$academicYearOptions" option-value="id" option-label="name" class="select-sm" />
            </div>
            @endif

            {{-- Status --}}
            <div class="min-w-44">
                <label class="text-xs font-semibold text-base-content/50 uppercase tracking-wider mb-1 block">Statut</label>
                <x-select wire:model.live="statusFilter" :options="$statusOptions" option-value="id" option-label="name" class="select-sm" />
            </div>

            {{-- Reset --}}
            @if($filtersActive)
            <x-button wire:click="resetFilters" icon="o-x-mark" label="Réinitialiser" class="btn-ghost btn-sm self-end" />
            @endif

        </div>
    </x-card>

    {{-- Invoice cards --}}
    @if($invoices->isEmpty())
    <x-card shadow class="border-0">
        <div class="text-center py-16 text-base-content/40">
            <x-icon name="o-document-text" class="w-12 h-12 mx-auto mb-3 opacity-30" />
            <p class="text-sm font-medium">Aucune facture trouvée</p>
        </div>
    </x-card>
    @else
    <div class="space-y-4">
    @foreach($invoices as $invoice)
    @php
        $isReg     = $invoice->invoice_type?->value === 'registration';
        $isPaid    = $invoice->status === \App\Enums\InvoiceStatus::PAID;
        $isCancelled = $invoice->status === \App\Enums\InvoiceStatus::CANCELLED;
        $isOverdue = $invoice->status === \App\Enums\InvoiceStatus::OVERDUE
                     || ($invoice->due_date?->isPast() && ! $isPaid && ! $isCancelled);
        $canPay    = ! $isPaid && ! $isCancelled && $invoice->balance_due > 0;
        $pendingPayments = $invoice->payments->filter(fn($p) => $p->status === \App\Enums\PaymentStatus::PENDING);
        $confirmedPayments = $invoice->payments->filter(fn($p) => $p->status === \App\Enums\PaymentStatus::CONFIRMED);

        $borderColor = $isPaid ? 'border-success/30 bg-success/3' : ($isOverdue ? 'border-error/30 bg-error/3' : ($isReg ? 'border-warning/30 bg-warning/3' : 'border-base-200'));
    @endphp

    <x-card shadow class="border {{ $borderColor }} overflow-hidden p-0">

        {{-- Card header --}}
        <div class="flex items-start justify-between gap-3 p-4 pb-3">
            <div class="flex items-start gap-3">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0
                    {{ $isPaid ? 'bg-success/15' : ($isOverdue ? 'bg-error/15' : ($isReg ? 'bg-warning/15' : 'bg-primary/10')) }}">
                    @if($isPaid)
                        <x-icon name="o-check-badge" class="w-5 h-5 text-success" />
                    @elseif($isOverdue)
                        <x-icon name="o-exclamation-triangle" class="w-5 h-5 text-error" />
                    @elseif($isReg)
                        <x-icon name="o-bolt" class="w-5 h-5 text-warning" />
                    @else
                        <x-icon name="o-document-text" class="w-5 h-5 text-primary" />
                    @endif
                </div>
                <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="font-bold font-mono text-sm">{{ $invoice->reference }}</span>
                        <x-badge :value="$invoice->status?->label()" class="badge-{{ $invoice->status?->color() }} badge-xs" />
                        @if($isReg)
                        <x-badge value="Inscription" class="badge-warning badge-xs" />
                        @endif
                    </div>
                    <p class="text-xs text-base-content/50 mt-0.5">
                        {{ $invoice->enrollment?->student?->full_name }}
                        @if($invoice->enrollment?->schoolClass)
                            · {{ $invoice->enrollment->schoolClass->name }}
                        @endif
                    </p>
                    <p class="text-xs text-base-content/50">
                        {{ $invoice->invoice_type?->label() }}
                        @if($invoice->installment_number) · Versement nº{{ $invoice->installment_number }} @endif
                        · Échéance : <span class="{{ $isOverdue ? 'text-error font-semibold' : '' }}">{{ $invoice->due_date?->format('d/m/Y') }}</span>
                        @if($isOverdue) <span class="text-error">(En retard)</span> @endif
                    </p>
                </div>
            </div>

            {{-- Amount summary --}}
            <div class="text-right shrink-0">
                <p class="font-black text-lg tabular-nums">{{ number_format($invoice->total, 0, ',', ' ') }} <span class="text-xs font-normal text-base-content/50">DJF</span></p>
                @if($invoice->paid_total > 0)
                <p class="text-xs text-success tabular-nums">Payé : {{ number_format($invoice->paid_total, 0, ',', ' ') }}</p>
                @endif
                @if($invoice->balance_due > 0 && ! $isCancelled)
                <p class="text-xs text-error font-semibold tabular-nums">Reste : {{ number_format($invoice->balance_due, 0, ',', ' ') }}</p>
                @endif
            </div>
        </div>

        {{-- Progress bar --}}
        @if(! $isCancelled && $invoice->total > 0)
        <div class="px-4 pb-2">
            <progress class="progress {{ $isPaid ? 'progress-success' : ($isOverdue ? 'progress-error' : 'progress-primary') }} h-1.5 w-full"
                      value="{{ $invoice->paid_total }}" max="{{ $invoice->total }}"></progress>
        </div>
        @endif

        {{-- Pending payments warning --}}
        @if($pendingPayments->isNotEmpty())
        <div class="mx-4 mb-2 px-3 py-2 rounded-lg bg-info/10 border border-info/30 flex items-center gap-2 text-xs text-info">
            <x-icon name="o-clock" class="w-4 h-4 shrink-0" />
            <span>{{ $pendingPayments->count() }} paiement(s) en attente de confirmation par l'administration</span>
        </div>
        @endif

        {{-- Actions --}}
        <div class="flex items-center gap-2 px-4 pb-4 pt-1 flex-wrap">
            @if($canPay)
            {{-- D-Money Online (API) --}}
            @php
                $hasPendingDmoney = \App\Models\DmoneyTransaction::where('invoice_id', $invoice->id)
                    ->where('status', 'pending')->exists();
            @endphp
            @if($hasPendingDmoney)
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-warning/10 text-warning border border-warning/30">
                <x-icon name="o-clock" class="w-3.5 h-3.5" />
                D-Money en attente…
            </span>
            @else
            <x-button
                label="Payer via D-Money"
                icon="o-device-phone-mobile"
                wire:click="initiateDMoney({{ $invoice->id }})"
                wire:loading.attr="disabled"
                wire:target="initiateDMoney({{ $invoice->id }})"
                class="btn-success btn-sm"
                spinner="initiateDMoney({{ $invoice->id }})"
            />
            @endif

            {{-- Other providers — manual flow --}}
            <x-button
                label="Autre paiement"
                icon="o-credit-card"
                wire:click="openPayModal({{ $invoice->id }})"
                class="btn-ghost btn-sm"
            />
            @endif

            {{-- Print button --}}
            <a href="{{ route('guardian.invoices.print', $invoice->uuid) }}" target="_blank">
                <x-button label="Imprimer" icon="o-printer" class="btn-ghost btn-sm" />
            </a>

            {{-- Payment history toggle --}}
            @if($invoice->payments->isNotEmpty())
            <details class="ml-auto">
                <summary class="cursor-pointer text-xs text-base-content/50 hover:text-primary flex items-center gap-1 list-none">
                    <x-icon name="o-clock" class="w-3.5 h-3.5" />
                    {{ $invoice->payments->count() }} paiement(s)
                </summary>
                <div class="mt-2 space-y-1.5 border-t border-base-200 pt-2">
                    @foreach($invoice->payments as $pmt)
                    <div class="flex items-center justify-between text-xs px-1 py-1.5 rounded-lg
                        {{ $pmt->status === \App\Enums\PaymentStatus::PENDING ? 'bg-warning/10' : ($pmt->status === \App\Enums\PaymentStatus::CONFIRMED ? 'bg-success/8' : 'bg-base-100') }}">
                        <div class="flex items-center gap-2">
                            @if($pmt->status === \App\Enums\PaymentStatus::PENDING)
                                <x-icon name="o-clock" class="w-3.5 h-3.5 text-warning shrink-0" />
                            @elseif($pmt->status === \App\Enums\PaymentStatus::CONFIRMED)
                                <x-icon name="o-check-circle" class="w-3.5 h-3.5 text-success shrink-0" />
                            @else
                                <x-icon name="o-x-circle" class="w-3.5 h-3.5 text-error shrink-0" />
                            @endif
                            <div>
                                <p class="font-medium">{{ $pmt->reference }}</p>
                                <p class="text-base-content/50">
                                    {{ $pmt->payment_date?->format('d/m/Y') }}
                                    @if($pmt->transaction_ref) · Réf : {{ $pmt->transaction_ref }} @endif
                                </p>
                                @if($pmt->notes)
                                <p class="text-base-content/40 italic">{{ $pmt->notes }}</p>
                                @endif
                            </div>
                        </div>
                        <div class="text-right shrink-0 ml-3">
                            <p class="font-bold tabular-nums {{ $pmt->status === \App\Enums\PaymentStatus::CONFIRMED ? 'text-success' : ($pmt->status === \App\Enums\PaymentStatus::PENDING ? 'text-warning' : 'text-error line-through') }}">
                                {{ number_format($pmt->pivot->amount ?? $pmt->amount, 0, ',', ' ') }} DJF
                            </p>
                            <x-badge :value="$pmt->status?->label()" class="badge-{{ $pmt->status?->color() }} badge-xs" />
                        </div>
                    </div>
                    @endforeach
                </div>
            </details>
            @endif
        </div>

    </x-card>
    @endforeach
    </div>

    <div>{{ $invoices->links() }}</div>
    @endif

    {{-- ── Payment modal ── --}}
    <x-modal wire:model="showPayModal" title="Payer en ligne" separator>
        @if($payInvoiceId)
        @php $payInvoice = $invoices->getCollection()->firstWhere('id', $payInvoiceId) ?? \App\Models\Invoice::find($payInvoiceId); @endphp
        @if($payInvoice)

        {{-- Amount banner --}}
        <div class="text-center mb-5 p-4 rounded-2xl bg-primary/10">
            <p class="text-xs text-base-content/50 mb-1">Montant à payer</p>
            <p class="text-3xl font-black text-primary tabular-nums">{{ number_format($payInvoice->balance_due, 0, ',', ' ') }} <span class="text-lg">DJF</span></p>
            <p class="text-xs text-base-content/50 mt-1">{{ $payInvoice->reference }} · {{ $payInvoice->invoice_type?->label() }}</p>
        </div>

        {{-- D-Money info banner in manual modal --}}
        <div class="mb-4 p-3 rounded-xl flex items-start gap-2 bg-success/8 border border-success/25 text-xs text-success">
            <x-icon name="o-information-circle" class="w-4 h-4 shrink-0 mt-0.5" />
            <span>Pour <strong>D-Money</strong>, utilisez le bouton <strong>"Payer via D-Money"</strong> directement sur la facture — paiement en ligne automatique.</span>
        </div>

        {{-- Step 1: Choose method --}}
        <p class="text-xs font-bold text-base-content/50 uppercase tracking-wider mb-2">1. Choisissez votre opérateur</p>
        <div class="grid grid-cols-2 gap-2 mb-4">
            @foreach(['waafi' => ['label' => 'Waafi', 'icon' => 'o-device-phone-mobile', 'color' => 'from-green-500 to-green-700'], 'cac_pay' => ['label' => 'Cac Pay', 'icon' => 'o-credit-card', 'color' => 'from-red-500 to-red-700'], 'e_dahab' => ['label' => 'E-Dahab', 'icon' => 'o-banknotes', 'color' => 'from-yellow-500 to-amber-600']] as $key => $info)
            <button wire:click="$set('payMethod', '{{ $key }}')"
                class="flex items-center gap-2 p-3 rounded-xl border-2 transition-all
                    {{ $payMethod === $key ? 'border-primary bg-primary/10 shadow-sm' : 'border-base-200 hover:border-primary/40' }}">
                <div class="w-8 h-8 rounded-lg bg-linear-to-br {{ $info['color'] }} flex items-center justify-center shrink-0">
                    <x-icon :name="$info['icon']" class="w-4 h-4 text-white" />
                </div>
                <span class="font-semibold text-sm">{{ $info['label'] }}</span>
                @if($payMethod === $key)
                <x-icon name="o-check-circle" class="w-4 h-4 text-primary ml-auto" />
                @endif
            </button>
            @endforeach
        </div>

        {{-- Step 2: Instructions --}}
        @php
        $instructions = [
            'd_money' => ['number' => $school?->phone ?? '+253 77 XX XX XX', 'name' => 'D-Money Dahabshiil', 'steps' => ['Ouvrez votre application D-Money', 'Sélectionnez "Payer un marchand"', 'Entrez le numéro : <strong>' . ($school?->phone ?? '+253 77 XX XX XX') . '</strong>', 'Montant : <strong>' . number_format($payInvoice->balance_due, 0, ',', ' ') . ' DJF</strong>', 'Confirmez avec votre code PIN']],
            'waafi'   => ['number' => $school?->phone ?? '+253 63 XX XX XX', 'name' => 'Waafi Telesom', 'steps' => ['Composez le <strong>*843#</strong> sur votre téléphone', 'Sélectionnez "Paiement marchand"', 'Entrez le numéro : <strong>' . ($school?->phone ?? '+253 63 XX XX XX') . '</strong>', 'Montant : <strong>' . number_format($payInvoice->balance_due, 0, ',', ' ') . ' DJF</strong>', 'Validez votre paiement']],
            'cac_pay' => ['number' => 'CAC-' . ($school?->id ?? 'XXXX'), 'name' => 'Cac Pay', 'steps' => ['Ouvrez votre application Cac Pay', 'Sélectionnez "Payer"', 'Entrez le code marchand : <strong>CAC-' . ($school?->id ?? 'XXXX') . '</strong>', 'Montant : <strong>' . number_format($payInvoice->balance_due, 0, ',', ' ') . ' DJF</strong>', 'Confirmez avec votre code PIN']],
            'e_dahab' => ['number' => $school?->phone ?? '+253 XX XX XX XX', 'name' => 'E-Dahab IBS', 'steps' => ['Ouvrez votre application E-Dahab', 'Sélectionnez "Transfert / Paiement"', 'Entrez le numéro : <strong>' . ($school?->phone ?? '+253 XX XX XX XX') . '</strong>', 'Montant : <strong>' . number_format($payInvoice->balance_due, 0, ',', ' ') . ' DJF</strong>', 'Validez le paiement']],
        ];
        $inst = $instructions[$payMethod];
        @endphp
        <div class="mb-4 p-3 bg-base-200/60 rounded-xl border border-base-200">
            <p class="text-xs font-bold text-base-content/60 uppercase tracking-wider mb-2">Instructions {{ $inst['name'] }}</p>
            <ol class="text-sm space-y-1.5 list-none">
                @foreach($inst['steps'] as $i => $step)
                <li class="flex items-start gap-2">
                    <span class="w-5 h-5 rounded-full bg-primary/15 text-primary font-bold text-xs flex items-center justify-center shrink-0 mt-0.5">{{ $i + 1 }}</span>
                    <span class="text-base-content/70">{!! $step !!}</span>
                </li>
                @endforeach
            </ol>
        </div>

        {{-- Step 3: Form --}}
        <p class="text-xs font-bold text-base-content/50 uppercase tracking-wider mb-2">3. Confirmez votre paiement</p>
        <x-form wire:submit="submitPayment">
            <x-input
                label="Votre numéro de téléphone"
                wire:model="payPhone"
                placeholder="+253 77 XX XX XX"
                icon="o-phone"
            />
            <x-input
                label="Référence de transaction"
                wire:model="payTransactionRef"
                placeholder="Ex : TRX2026032100001"
                icon="o-hashtag"
                hint="Fournie par votre opérateur après le paiement"
            />

            <x-slot:actions>
                <x-button label="Annuler" wire:click="closePayModal" class="btn-ghost" />
                <x-button
                    label="Soumettre le paiement"
                    type="submit"
                    icon="o-paper-airplane"
                    class="btn-primary"
                    spinner="submitPayment"
                />
            </x-slot:actions>
        </x-form>
        @endif
        @endif
    </x-modal>

</div>
