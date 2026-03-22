<?php
use App\Models\Student;
use App\Models\Enrollment;
use App\Models\AcademicYear;
use App\Models\FeeSchedule;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Mary\Traits\Toast;

new #[Layout('layouts.app')] class extends Component {
    use Toast;

    // Form
    public int    $studentId    = 0;
    public int    $yearId       = 0;
    public string $invoiceType  = 'tuition';
    public string $issueDate    = '';
    public string $dueDate      = '';
    public int    $subtotal     = 0;
    public float  $vatRate      = 0;
    public string $notes        = '';
    public int    $installmentNumber = 0;

    private function schoolId(): int
    {
        return auth()->user()->school_id;
    }

    #[Computed]
    public function vatAmount(): int
    {
        return (int) round($this->subtotal * ($this->vatRate / 100));
    }

    #[Computed]
    public function total(): int
    {
        return $this->subtotal + $this->vatAmount;
    }

    public function mount(): void
    {
        $this->issueDate = now()->format('Y-m-d');
        $this->dueDate   = now()->addDays(30)->format('Y-m-d');
        $this->vatRate   = auth()->user()->school->vat_rate ?? 0;
    }

    public function updatedStudentId(): void
    {
        // Auto-fill year from current enrollment
        $enrollment = Enrollment::where('student_id', $this->studentId)
            ->whereHas('academicYear', fn($q) => $q->where('is_current', true))
            ->first();
        if ($enrollment) {
            $this->yearId = $enrollment->academic_year_id;
        }
    }

    public function save(): void
    {
        $this->validate([
            'studentId'   => 'required|integer|min:1',
            'yearId'      => 'required|integer|min:1',
            'invoiceType' => 'required|string',
            'issueDate'   => 'required|date',
            'dueDate'     => 'required|date|after_or_equal:issueDate',
            'subtotal'    => 'required|integer|min:0',
        ]);

        $vatAmount = $this->vatAmount;
        $total     = $this->total;
        $enrollment = Enrollment::where('student_id', $this->studentId)
            ->where('academic_year_id', $this->yearId)
            ->first();

        $invoice = \App\Models\Invoice::create([
            'reference'          => \App\Models\Invoice::generateReference(),
            'school_id'          => $this->schoolId(),
            'student_id'         => $this->studentId,
            'enrollment_id'      => $enrollment?->id,
            'academic_year_id'   => $this->yearId,
            'invoice_type'       => $this->invoiceType,
            'status'             => InvoiceStatus::ISSUED,
            'issue_date'         => $this->issueDate,
            'due_date'           => $this->dueDate,
            'subtotal'           => $this->subtotal,
            'vat_rate'           => $this->vatRate,
            'vat_amount'         => $vatAmount,
            'total'              => $total,
            'paid_total'         => 0,
            'balance_due'        => $total,
            'installment_number' => $this->installmentNumber ?: null,
            'notes'              => $this->notes ?: null,
        ]);

        $this->success('Facture créée avec succès.', position: 'toast-top toast-end', icon: 'o-plus-circle', css: 'alert-success', timeout: 3000);
        $this->redirect(route('admin.finance.invoices.show', $invoice->uuid), navigate: true);
    }

    public function with(): array
    {
        return [
            'students' => Student::where('school_id', $this->schoolId())
                ->where('is_active', true)->orderBy('name')
                ->get()->map(fn($s) => ['id' => $s->id, 'name' => $s->full_name])->all(),
            'years' => AcademicYear::where('school_id', $this->schoolId())
                ->orderByDesc('start_date')
                ->get()->map(fn($y) => ['id' => $y->id, 'name' => $y->name])->all(),
            'types' => [
                ['id' => 'registration', 'name' => 'Inscription'],
                ['id' => 'tuition',      'name' => 'Scolarité'],
            ],
        ];
    }
};
?>

<div>
    <x-header title="Nouvelle facture" separator progress-indicator>
        <x-slot:actions>
            <x-button label="Annuler" icon="o-x-mark" :link="route('admin.finance.invoices.index')" class="btn-ghost" wire:navigate />
        </x-slot:actions>
    </x-header>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <x-card title="Informations de la facture" separator>
                <x-form wire:submit="save" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <x-select
                            label="Élève *"
                            wire:model.live="studentId"
                            :options="$students"
                            option-value="id"
                            option-label="name"
                            placeholder="Sélectionner un élève"
                            placeholder-value="0"
                            required
                        />
                        <x-select
                            label="Année académique *"
                            wire:model="yearId"
                            :options="$years"
                            option-value="id"
                            option-label="name"
                            placeholder="Sélectionner"
                            placeholder-value="0"
                            required
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <x-select
                            label="Type de facture *"
                            wire:model="invoiceType"
                            :options="$types"
                            option-value="id"
                            option-label="name"
                            required
                        />
                        <x-input
                            label="N° d'installation"
                            wire:model="installmentNumber"
                            type="number"
                            min="0"
                            placeholder="Laisser vide si unique"
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <x-datepicker label="Date d'émission *" wire:model="issueDate" icon="o-calendar" required :config="['dateFormat' => 'Y-m-d', 'altFormat' => 'd/m/Y', 'altInput' => true, 'locale' => ['firstDayOfWeek' => 1]]" />
                        <x-datepicker label="Échéance *" wire:model="dueDate" icon="o-calendar" required :config="['dateFormat' => 'Y-m-d', 'altFormat' => 'd/m/Y', 'altInput' => true, 'locale' => ['firstDayOfWeek' => 1]]" />
                    </div>

                    <div class="divider text-xs text-base-content/40">Montants</div>

                    <div class="grid grid-cols-2 gap-4">
                        <x-input
                            label="Montant HT (DJF) *"
                            wire:model.live="subtotal"
                            type="number"
                            min="0"
                            required
                        />
                        <x-input
                            label="Taux TVA (%)"
                            wire:model.live="vatRate"
                            type="number"
                            min="0"
                            max="100"
                            step="0.01"
                        />
                    </div>

                    <x-textarea label="Notes" wire:model="notes" rows="2" />

                    <x-slot:actions>
                        <x-button label="Créer la facture" type="submit" icon="o-document-plus" class="btn-primary" spinner />
                    </x-slot:actions>
                </x-form>
            </x-card>
        </div>

        {{-- Live preview --}}
        <div class="space-y-4">
            <x-card title="Récapitulatif" separator>
                <div class="space-y-3">
                    <div class="flex justify-between py-2 border-b border-base-200">
                        <span class="text-base-content/60">Sous-total</span>
                        <span class="font-bold">{{ number_format($subtotal) }} DJF</span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-base-200">
                        <span class="text-base-content/60">TVA ({{ $vatRate }}%)</span>
                        <span class="font-bold">{{ number_format($this->vatAmount) }} DJF</span>
                    </div>
                    <div class="flex justify-between py-2">
                        <span class="font-bold text-lg">Total</span>
                        <span class="font-black text-2xl text-primary">{{ number_format($this->total) }} DJF</span>
                    </div>
                </div>
            </x-card>

            <x-alert icon="o-information-circle" class="alert-info text-sm">
                La facture sera créée avec le statut <strong>Émise</strong> et sera immédiatement visible par les responsables.
            </x-alert>
        </div>
    </div>
</div>
