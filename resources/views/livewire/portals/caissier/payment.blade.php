<?php
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Student;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\AcademicYear;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

new #[Layout('layouts.caissier')] class extends Component {
    use Toast, WithFileUploads;

    public string $invoiceUuid     = '';
    public ?Invoice $invoice       = null;
    public string $studentSearch   = '';
    public ?int $filterGradeId     = null;
    public ?int $filterClassId     = null;
    public array $students         = [];
    public ?int $selectedStudentId = null;
    public array $studentInvoices  = [];

    #[Rule('required|numeric|min:1')]
    public float $amount = 0;

    #[Rule('required|in:cash,bank_transfer,check,mobile_money')]
    public string $paymentMethod = 'cash';

    #[Rule('nullable|string|max:500')]
    public string $notes = '';

    #[Rule('nullable|image|max:5120')]
    public $proofScreenshot = null;

    public function mount(?string $invoice = null): void
    {
        if ($invoice) {
            $this->invoiceUuid = $invoice;
            $this->loadInvoice();
        }
    }

    private function loadInvoice(): void
    {
        $this->invoice = Invoice::where('uuid', $this->invoiceUuid)
            ->where('school_id', auth()->user()->school_id)
            ->with(['student', 'enrollment'])
            ->first();

        if ($this->invoice) {
            $this->amount = max(0, (float) $this->invoice->balance_due);
        }
    }

    public function updatedStudentSearch(): void  { $this->searchStudents(); }
    public function updatedFilterClassId(): void  { $this->searchStudents(); }

    public function updatedFilterGradeId(): void
    {
        $this->filterClassId = null;   // reset class when the niveau changes
        $this->searchStudents();
    }

    public function searchStudents(): void
    {
        $schoolId = auth()->user()->school_id;
        $search   = trim($this->studentSearch);

        // Need at least one criterion (text ≥ 2 chars, or a class/grade)
        if (strlen($search) < 2 && ! $this->filterClassId && ! $this->filterGradeId) {
            $this->students = [];
            return;
        }

        $year = AcademicYear::where('school_id', $schoolId)->where('is_current', true)->first();

        $this->students = Student::where('school_id', $schoolId)
            ->when(strlen($search) >= 2, fn ($q) => $q->where(fn ($w) =>
                $w->where('name', 'like', "%{$search}%")->orWhere('reference', 'like', "%{$search}%")))
            ->when($this->filterClassId || $this->filterGradeId, fn ($q) => $q->whereHas('enrollments', fn ($e) =>
                $e->where('status', 'confirmed')
                  ->when($year, fn ($x) => $x->where('academic_year_id', $year->id))
                  ->when($this->filterClassId, fn ($x) => $x->where('school_class_id', $this->filterClassId))
                  ->when($this->filterGradeId && ! $this->filterClassId, fn ($x) => $x->where('grade_id', $this->filterGradeId))))
            ->orderBy('name')
            ->limit(30)
            ->get()
            ->map(fn ($s) => ['id' => $s->id, 'name' => $s->full_name . ' (' . $s->reference . ')'])
            ->toArray();
    }

    public function selectStudent(int $studentId): void
    {
        $this->selectedStudentId = $studentId;
        $this->studentSearch     = '';
        $this->students          = [];

        $this->studentInvoices = Invoice::where('student_id', $studentId)
            ->where('school_id', auth()->user()->school_id)
            ->whereNotIn('status', ['paid', 'cancelled'])
            ->with(['enrollment.schoolClass'])
            ->orderBy('due_date')
            ->get()
            ->map(fn ($inv) => [
                'id'   => $inv->id,
                'name' => $inv->reference . ' — ' . number_format($inv->balance_due ?? $inv->total, 0, ',', ' ') . ' DJF restant (' . ($inv->invoice_type?->value ?? $inv->status?->value) . ')',
            ])
            ->toArray();
    }

    public function selectInvoice(int $invoiceId): void
    {
        $this->invoice = Invoice::with(['student', 'enrollment'])->find($invoiceId);
        if ($this->invoice) {
            $this->amount = max(0, (float) ($this->invoice->balance_due ?? $this->invoice->total));
        }
    }

    public function save(): void
    {
        $this->validate();

        if (! $this->invoice) {
            $this->error('Sélectionnez une facture.', position: 'toast-top toast-center', timeout: 3000);
            return;
        }

        $balance = (float) ($this->invoice->balance_due ?? $this->invoice->total);
        if ($this->amount > $balance + 0.01) {
            $this->error(
                "Le montant dépasse le solde restant (" . number_format($balance, 0, ',', ' ') . " DJF).",
                position: 'toast-top toast-center',
                timeout: 4000
            );
            return;
        }

        // Upload proof screenshot if provided
        $screenshotPath = null;
        if ($this->proofScreenshot) {
            $screenshotPath = $this->proofScreenshot->store('payment-proofs', 'public');
        }

        DB::transaction(function () use ($screenshotPath) {
            $payment = Payment::create([
                'uuid'           => (string) Str::uuid(),
                'reference'      => Payment::generateReference(),
                'school_id'      => auth()->user()->school_id,
                'student_id'     => $this->invoice->student_id,
                'enrollment_id'  => $this->invoice->enrollment_id,
                'received_by'    => auth()->id(),
                'amount'         => $this->amount,
                'payment_method' => $this->paymentMethod,
                'payment_date'   => today(),
                'notes'          => $this->notes ?: null,
                'status'         => 'confirmed',
                'meta'           => $screenshotPath ? ['proof_screenshot' => $screenshotPath] : null,
            ]);

            PaymentAllocation::create([
                'payment_id' => $payment->id,
                'invoice_id' => $this->invoice->id,
                'amount'     => $this->amount,
            ]);

            // Update invoice paid_total, balance_due, and status
            $invoice      = $this->invoice->fresh();
            $newPaidTotal = (float) $invoice->paid_total + $this->amount;
            $newBalance   = max(0, (float) $invoice->total - $newPaidTotal);
            $newStatus    = $newBalance <= 0.01 ? 'paid' : 'partially_paid';

            $invoice->update([
                'paid_total'  => $newPaidTotal,
                'balance_due' => $newBalance,
                'status'      => $newStatus,
            ]);
        });

        $this->success(
            "Paiement de " . number_format($this->amount, 0, ',', ' ') . " DJF enregistré !",
            "Facture : {$this->invoice->reference}",
            position: 'toast-top toast-end', icon: 'o-banknotes', css: 'alert-success', timeout: 4000
        );

        $this->reset(['invoice', 'invoiceUuid', 'amount', 'notes', 'selectedStudentId', 'studentInvoices', 'proofScreenshot']);
        $this->paymentMethod = 'cash';
    }

    public function with(): array
    {
        $schoolId = auth()->user()->school_id;

        return [
            'grades' => Grade::where('school_id', $schoolId)->where('is_active', true)
                ->orderBy('order')->get()
                ->map(fn ($g) => ['id' => $g->id, 'name' => $g->name])->all(),
            'classes' => SchoolClass::where('school_id', $schoolId)->where('is_active', true)
                ->when($this->filterGradeId, fn ($q) => $q->where('grade_id', $this->filterGradeId))
                ->orderBy('name')->get()
                ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->all(),
        ];
    }
};
?>

<div class="p-4 lg:p-6 space-y-6">
    <x-header title="{{ __('navigation.record_payment') }}" subtitle="Enregistrer un encaissement physique" separator>
        <x-slot:actions>
            <x-button icon="o-arrow-left" link="{{ route('caissier.dashboard') }}" wire:navigate class="btn-ghost btn-sm" />
        </x-slot:actions>
    </x-header>

    <div class="max-w-2xl mx-auto space-y-6">

        {{-- Step 1: Search student --}}
        @if(!$invoice)
        <x-card title="1. Rechercher l'élève" shadow class="border-0">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-3">
                <x-select wire:model.live="filterGradeId" :options="$grades" placeholder="Niveau (tous)" icon="o-academic-cap" />
                <x-select wire:model.live="filterClassId" :options="$classes" placeholder="Classe (toutes)" icon="o-building-office" />
            </div>
            <x-input wire:model.live.debounce.400ms="studentSearch"
                     placeholder="Nom, prénom ou n° de l'élève…" icon="o-magnifying-glass" clearable />
            <p class="text-xs text-base-content/50 mt-1">Filtrez par niveau/classe, ou tapez au moins 2 caractères.</p>

            @if(!empty($students))
            <div class="mt-2 border border-base-200 rounded-xl overflow-hidden shadow-sm">
                @foreach($students as $s)
                <button wire:click="selectStudent({{ $s['id'] }})"
                    class="w-full text-left px-4 py-3 hover:bg-cyan-50 border-b border-base-100 last:border-0 transition-colors flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-cyan-100 flex items-center justify-center">
                        <x-icon name="o-user" class="w-4 h-4 text-cyan-600" />
                    </div>
                    <span class="text-sm font-medium">{{ $s['name'] }}</span>
                </button>
                @endforeach
            </div>
            @elseif(strlen($studentSearch) >= 2 || $filterClassId || $filterGradeId)
            <p class="mt-3 text-sm text-base-content/40 text-center py-4">Aucun élève trouvé pour ces critères.</p>
            @endif
        </x-card>

        {{-- Step 2: Select invoice --}}
        @if($selectedStudentId && !empty($studentInvoices))
        <x-card title="2. Choisir la facture" shadow class="border-0">
            <div class="space-y-2">
                @foreach($studentInvoices as $inv)
                <button wire:click="selectInvoice({{ $inv['id'] }})"
                    class="w-full text-left px-4 py-3 border border-base-200 rounded-xl hover:border-cyan-400 hover:bg-cyan-50 transition-all flex items-center gap-3">
                    <x-icon name="o-document-currency-dollar" class="w-5 h-5 text-cyan-600 flex-shrink-0" />
                    <span class="text-sm">{{ $inv['name'] }}</span>
                </button>
                @endforeach
            </div>
        </x-card>
        @elseif($selectedStudentId)
        <x-alert icon="o-check-circle" class="alert-success">Cet élève n'a pas de facture impayée.</x-alert>
        @endif
        @endif

        {{-- Step 3: Payment form --}}
        @if($invoice)
        <x-card shadow class="border-0 border-l-4 border-l-cyan-500">
            {{-- Invoice summary --}}
            <div class="mb-5 p-4 bg-cyan-50 rounded-xl space-y-1">
                <p class="font-bold text-cyan-800">{{ $invoice->student?->full_name }}</p>
                <p class="text-sm text-cyan-600">Facture : <span class="font-mono">{{ $invoice->reference }}</span></p>
                @php $balance = max(0, (float) ($invoice->balance_due ?? $invoice->total)); @endphp
                <p class="text-sm text-cyan-700">
                    Solde restant : <strong>{{ number_format($balance, 0, ',', ' ') }} DJF</strong>
                    <span class="text-xs text-cyan-500 ml-1">(total : {{ number_format($invoice->total, 0, ',', ' ') }} DJF)</span>
                </p>
            </div>

            <x-form wire:submit="save">
                <x-input type="number"
                    label="Montant encaissé (DJF)"
                    wire:model="amount"
                    placeholder="Montant"
                    step="1" min="1"
                    :max="$balance" />

                <x-select label="Mode de paiement" wire:model="paymentMethod" :options="[
                    ['id' => 'cash',          'name' => 'Espèces'],
                    ['id' => 'bank_transfer', 'name' => 'Virement bancaire'],
                    ['id' => 'check',         'name' => 'Chèque'],
                    ['id' => 'mobile_money',  'name' => 'Mobile Money'],
                ]" />

                <x-textarea label="Notes (facultatif)" wire:model="notes" rows="2" placeholder="Remarques, numéro de chèque..." />

                {{-- Screenshot upload --}}
                <div>
                    <label class="label">
                        <span class="label-text text-sm font-medium">Preuve de paiement (optionnel)</span>
                        <span class="label-text-alt text-xs text-base-content/50">Photo depuis le téléphone du parent</span>
                    </label>
                    <input type="file" wire:model="proofScreenshot" accept="image/*"
                        class="file-input file-input-bordered file-input-sm w-full" />
                    @error('proofScreenshot')
                    <p class="text-error text-xs mt-1">{{ $message }}</p>
                    @enderror
                    @if($proofScreenshot)
                    <div class="mt-2">
                        <img src="{{ $proofScreenshot->temporaryUrl() }}" alt="Aperçu" class="max-h-32 rounded-xl border border-base-200 shadow-sm" />
                    </div>
                    @endif
                </div>

                <x-slot:actions>
                    <x-button label="Annuler" wire:click="$set('invoice', null)" class="btn-ghost" />
                    <x-button label="Enregistrer le paiement" type="submit" icon="o-credit-card" class="btn-primary" wire:loading.attr="disabled" spinner="save" />
                </x-slot:actions>
            </x-form>
        </x-card>
        @endif
    </div>
</div>
