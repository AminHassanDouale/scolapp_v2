<?php
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Mary\Traits\Toast;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Student;

new #[Layout('layouts.caissier')] class extends Component {
    use Toast;

    public string $invoiceUuid    = '';
    public ?Invoice $invoice      = null;
    public string $studentSearch  = '';
    public array $students        = [];
    public ?int $selectedStudentId = null;
    public array $studentInvoices = [];

    #[Rule('required|numeric|min:1')]
    public float $amount = 0;

    #[Rule('required|in:cash,bank_transfer,check,mobile_money')]
    public string $paymentMethod = 'cash';

    #[Rule('nullable|string|max:500')]
    public string $notes = '';

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
            ->with(['enrollment.student'])
            ->first();

        if ($this->invoice) {
            $this->amount = $this->invoice->amount - $this->invoice->payments()->sum('amount');
        }
    }

    public function updatedStudentSearch(): void
    {
        if (strlen($this->studentSearch) >= 2) {
            $schoolId = auth()->user()->school_id;
            $this->students = Student::where('school_id', $schoolId)
                ->where(function($q) {
                    $q->where('name', 'like', "%{$this->studentSearch}%")
                      ->orWhere('reference', 'like', "%{$this->studentSearch}%");
                })
                ->limit(10)
                ->get()
                ->map(fn($s) => ['id' => $s->id, 'name' => $s->full_name . ' (' . $s->reference . ')'])
                ->toArray();
        } else {
            $this->students = [];
        }
    }

    public function selectStudent(int $studentId): void
    {
        $this->selectedStudentId = $studentId;
        $this->studentSearch     = '';
        $this->students          = [];

        $student = Student::find($studentId);
        $this->studentInvoices = Invoice::whereHas('enrollment', fn($q) => $q->where('student_id', $studentId))
            ->where('school_id', auth()->user()->school_id)
            ->where('status', '!=', 'paid')
            ->with(['enrollment.schoolClass'])
            ->orderBy('due_date')
            ->get()
            ->map(fn($inv) => ['id' => $inv->id, 'name' => $inv->reference . ' - ' . number_format($inv->amount, 0, ',', ' ') . ' DJF (' . $inv->status . ')'])
            ->toArray();
    }

    public function selectInvoice(int $invoiceId): void
    {
        $this->invoice = Invoice::with(['enrollment.student'])->find($invoiceId);
        if ($this->invoice) {
            $this->amount = $this->invoice->amount - $this->invoice->payments()->sum('amount');
        }
    }

    public function save(): void
    {
        $this->validate();

        if (!$this->invoice) {
            $this->error('Sélectionnez une facture.', position: 'toast-top toast-center', timeout: 3000);
            return;
        }

        $remaining = $this->invoice->amount - $this->invoice->payments()->sum('amount');
        if ($this->amount > $remaining) {
            $this->error("Le montant dépasse le solde restant ({$remaining} DJF).", position: 'toast-top toast-center', timeout: 4000);
            return;
        }

        Payment::create([
            'uuid'           => (string) \Illuminate\Support\Str::uuid(),
            'school_id'      => auth()->user()->school_id,
            'invoice_id'     => $this->invoice->id,
            'amount'         => $this->amount,
            'payment_method' => $this->paymentMethod,
            'payment_date'   => now(),
            'notes'          => $this->notes,
            'recorded_by'    => auth()->id(),
        ]);

        // Update invoice status
        $totalPaid = $this->invoice->payments()->sum('amount') + $this->amount;
        $newStatus = $totalPaid >= $this->invoice->amount ? 'paid' : 'partial';
        $this->invoice->update(['status' => $newStatus]);

        $this->success(
            "Paiement de " . number_format($this->amount, 0, ',', ' ') . " DJF enregistré !",
            "Facture : {$this->invoice->reference}",
            position: 'toast-top toast-end', icon: 'o-banknotes', css: 'alert-success', timeout: 4000
        );

        $this->reset(['invoice', 'invoiceUuid', 'amount', 'notes', 'selectedStudentId', 'studentInvoices']);
        $this->paymentMethod = 'cash';
    }

    public function with(): array
    {
        return [];
    }
};
?>

<div class="p-4 lg:p-6 space-y-6">
    <x-header title="{{ __('navigation.record_payment') }}" subtitle="Enregistrer un encaissement" separator>
        <x-slot:actions>
            <x-button icon="o-arrow-left" link="{{ route('caissier.dashboard') }}" wire:navigate class="btn-ghost btn-sm" />
        </x-slot:actions>
    </x-header>

    <div class="max-w-2xl mx-auto space-y-6">
        {{-- Step 1: Search student --}}
        @if(!$invoice)
        <x-card title="1. Rechercher l'élève" shadow class="border-0">
            <x-input wire:model.live.debounce="studentSearch" placeholder="Nom, prénom ou référence de l'élève..." icon="o-magnifying-glass" />

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
            <div class="mb-4 p-4 bg-cyan-50 rounded-xl">
                <p class="font-bold text-cyan-800">{{ $invoice->enrollment?->student?->full_name }}</p>
                <p class="text-sm text-cyan-600">Facture : {{ $invoice->reference }}</p>
                @php $remaining = $invoice->amount - $invoice->payments()->sum('amount'); @endphp
                <p class="text-sm text-cyan-700 mt-1">Solde restant : <strong>{{ number_format($remaining, 0, ',', ' ') }} DJF</strong></p>
            </div>

            <x-form wire:submit="save">
                <x-input type="number" label="Montant (DJF)" wire:model="amount" placeholder="Montant à encaisser" step="1" min="1" />

                <x-select label="Mode de paiement" wire:model="paymentMethod" :options="[
                    ['id' => 'cash',          'name' => 'Espèces'],
                    ['id' => 'bank_transfer', 'name' => 'Virement bancaire'],
                    ['id' => 'check',         'name' => 'Chèque'],
                    ['id' => 'mobile_money',  'name' => 'Mobile Money'],
                ]" />

                <x-textarea label="Notes (facultatif)" wire:model="notes" rows="2" placeholder="Remarques..." />

                <x-slot:actions>
                    <x-button label="Annuler" wire:click="$set('invoice', null)" class="btn-ghost" />
                    <x-button label="Enregistrer le paiement" type="submit" icon="o-credit-card" class="btn-primary" wire:loading.attr="disabled" spinner="save" />
                </x-slot:actions>
            </x-form>
        </x-card>
        @endif
    </div>
</div>
