<?php
use App\Models\Student;
use App\Models\AcademicYear;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\SchoolClass;
use App\Models\FeeSchedule;
use App\Enums\FeeScheduleType;
use App\Enums\InvoiceType;
use App\Enums\InvoiceStatus;
use App\Actions\CreateEnrollmentAction;
use App\Actions\ConfirmEnrollmentAction;
use App\Mail\InvoiceGeneratedMail;
use App\Mail\GuardianWelcomeMail;
use Carbon\Carbon;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Mary\Traits\Toast;

new #[Layout('layouts.app')] class extends Component {
    use Toast;

    public ?int   $studentId          = null;
    public ?int   $academicYearId     = null;
    public ?int   $schoolClassId      = null;
    public string $paymentFrequency   = '';
    public int    $customInstallments = 0;
    public bool   $confirmImmediately = false;
    public bool   $studentPrefilled   = false;

    // Resolved internally
    public int $gradeId       = 0;
    public int $feeScheduleId = 0;

    public function mount(string $studentUuid = ''): void
    {
        $currentYear = AcademicYear::where('school_id', auth()->user()->school_id)
            ->where('is_current', true)->first();
        if ($currentYear) {
            $this->academicYearId = $currentYear->id;
        }

        if ($studentUuid) {
            $student = Student::where('school_id', auth()->user()->school_id)
                ->where('uuid', $studentUuid)->first();
            if ($student) {
                $this->studentId       = $student->id;
                $this->studentPrefilled = true;
            }
        }
    }

    public function updatedAcademicYearId(): void
    {
        $this->schoolClassId      = null;
        $this->gradeId            = 0;
        $this->feeScheduleId      = 0;
        $this->paymentFrequency   = '';
        $this->customInstallments = 0;
    }

    public function updatedSchoolClassId(): void
    {
        $this->gradeId            = 0;
        $this->feeScheduleId      = 0;
        $this->paymentFrequency   = '';
        $this->customInstallments = 0;

        if (! $this->schoolClassId) return;

        $class = SchoolClass::with('grade')->find($this->schoolClassId);
        if (! $class?->grade_id) return;

        $this->gradeId = $class->grade_id;

        $schedule = FeeSchedule::where('school_id', auth()->user()->school_id)
            ->where('grade_id', $this->gradeId)
            ->where('is_active', true)->first();

        $this->feeScheduleId = $schedule?->id ?? 0;
    }

    public function updatedPaymentFrequency(): void
    {
        $freq = FeeScheduleType::tryFrom($this->paymentFrequency);
        $this->customInstallments = $freq?->installments() ?? 1;
    }

    public function save(): void
    {
        $this->validate([
            'studentId'          => 'required|integer|min:1',
            'academicYearId'     => 'required|integer|min:1',
            'schoolClassId'      => 'required|integer|min:1',
            'customInstallments' => 'nullable|integer|min:1|max:36',
        ]);

        $student      = Student::findOrFail($this->studentId);
        $academicYear = AcademicYear::findOrFail($this->academicYearId);
        $schoolClass  = SchoolClass::with('grade')->findOrFail($this->schoolClassId);
        $grade        = $schoolClass->grade;

        if (! $grade) {
            $this->error('La classe sélectionnée n\'a pas de niveau associé.', position: 'toast-top toast-center', icon: 'o-exclamation-circle', css: 'alert-error', timeout: 4000);
            return;
        }

        try {
            $enrollment = app(CreateEnrollmentAction::class)($student, $academicYear, $grade, $schoolClass);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage(), position: 'toast-top toast-center', icon: 'o-x-circle', css: 'alert-error', timeout: 4000);
            return;
        }

        $generatedInvoices = [];

        try {
            if ($this->feeScheduleId) {
                $freq    = FeeScheduleType::tryFrom($this->paymentFrequency ?: 'yearly');
                $defaultN = $freq?->installments() ?? 1;
                $customN  = (int) $this->customInstallments;
                $storedN  = ($customN > 0 && $customN !== $defaultN) ? $customN : null;

                $enrollment->studentFeePlan()->create([
                    'school_id'         => auth()->user()->school_id,
                    'fee_schedule_id'   => $this->feeScheduleId,
                    'payment_frequency' => $this->paymentFrequency ?: 'yearly',
                    'installments'      => $storedN,
                ]);

                $feeSchedule       = FeeSchedule::with('feeItems')->find($this->feeScheduleId);
                $generatedInvoices = $this->generateInvoices($enrollment, $feeSchedule);
            }

            if ($this->confirmImmediately) {
                app(ConfirmEnrollmentAction::class)($enrollment);
            }

            // ── Create guardian login account (first enrollment only) ─────────
            $primaryGuardian = $student->guardians()
                ->wherePivot('is_primary', true)->first()
                ?? $student->guardians()->first();

            if ($primaryGuardian && $primaryGuardian->email && !$primaryGuardian->user_id) {
                if (!User::where('email', $primaryGuardian->email)->exists()) {
                    $guardianPassword = Str::password(12, symbols: false);
                    $guardianUser = User::create([
                        'uuid'       => (string) Str::uuid(),
                        'school_id'  => auth()->user()->school_id,
                        'name'       => $primaryGuardian->full_name,
                                        'email'      => $primaryGuardian->email,
                        'password'   => Hash::make($guardianPassword),
                        'ui_lang'    => 'fr',
                        'timezone'   => 'Africa/Djibouti',
                    ]);
                    $guardianUser->assignRole('guardian');
                    $primaryGuardian->update(['user_id' => $guardianUser->id]);

                    // Send welcome email with credentials (synchronous — credentials must arrive immediately)
                    try {
                        $school = \App\Models\School::findOrFail(auth()->user()->school_id);
                        $student->load(['enrollments.schoolClass', 'enrollments.grade']);
                        Mail::to($primaryGuardian->email)->send(
                            new GuardianWelcomeMail($primaryGuardian, $school, $student, $guardianPassword)
                        );
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::error('GuardianWelcomeMail failed: ' . $e->getMessage());
                    }
                }
            }

            // Send invoice notifications synchronously (registration always, first tuition only)
            if (! empty($generatedInvoices) && $primaryGuardian?->email) {
                $sentTypes = [];
                foreach ($generatedInvoices as $invoice) {
                    $type = $invoice->invoice_type instanceof \App\Enums\InvoiceType
                        ? $invoice->invoice_type->value
                        : (string) $invoice->invoice_type;
                    // Always send registration invoice; send only the first tuition installment
                    if ($type === 'registration' || (! in_array('tuition', $sentTypes) && $type === 'tuition')) {
                        try {
                            Mail::to($primaryGuardian->email)->send(new InvoiceGeneratedMail($invoice));
                        } catch (\Throwable $mailEx) {
                            \Illuminate\Support\Facades\Log::error('InvoiceGeneratedMail failed: ' . $mailEx->getMessage());
                        }
                        $sentTypes[] = $type;
                    }
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Enrollment post-save error: ' . $e->getMessage(), [
                'enrollment_id' => $enrollment->id,
                'trace'         => $e->getTraceAsString(),
            ]);
            session()->flash('warning', 'Inscription créée mais une erreur est survenue : ' . $e->getMessage());
            $this->redirect(route('admin.enrollments.show', $enrollment->uuid));
            return;
        }

        session()->flash('success', 'Inscription créée — ' . count($generatedInvoices) . ' facture(s) générée(s).');
        $this->redirect(route('admin.enrollments.show', $enrollment->uuid));
    }

    private function generateInvoices(\App\Models\Enrollment $enrollment, FeeSchedule $feeSchedule): array
    {
        $schoolId = auth()->user()->school_id;
        $n        = $this->customInstallments > 0
            ? $this->customInstallments
            : (FeeScheduleType::tryFrom($this->paymentFrequency)?->installments() ?? 1);

        $invoices         = [];
        $inscriptionItems = $feeSchedule->feeItems->filter(fn($i) => strtoupper($i->code ?? '') === 'INSCR');
        $regularItems     = $feeSchedule->feeItems->filter(fn($i) => strtoupper($i->code ?? '') !== 'INSCR');

        // Registration fee — due immediately
        if ($inscriptionItems->isNotEmpty()) {
            $amount    = (int) $inscriptionItems->sum(fn($i) => $i->pivot->amount);
            $invoices[] = Invoice::create([
                'school_id'          => $schoolId,
                'student_id'         => $enrollment->student_id,
                'enrollment_id'      => $enrollment->id,
                'academic_year_id'   => $enrollment->academic_year_id,
                'fee_schedule_id'    => $feeSchedule->id,
                'invoice_type'       => InvoiceType::REGISTRATION->value,
                'schedule_type'      => $this->paymentFrequency ?: 'yearly',
                'status'             => InvoiceStatus::ISSUED->value,
                'issue_date'         => now()->toDateString(),
                'due_date'           => now()->toDateString(),
                'subtotal'           => $amount,
                'vat_rate'           => 0,
                'vat_amount'         => 0,
                'total'              => $amount,
                'paid_total'         => 0,
                'balance_due'        => $amount,
                'installment_number' => null,
            ]);
        }

        // Tuition installments
        if ($regularItems->isNotEmpty()) {
            $regularTotal = (int) $regularItems->sum(fn($i) => $i->pivot->amount);
            $dueDates     = $this->computeDueDates($n);

            for ($k = 1; $k <= $n; $k++) {
                $amount  = (int) round($regularTotal / $n);
                $dueDate = isset($dueDates[$k - 1])
                    ? Carbon::createFromFormat('d/m/Y', $dueDates[$k - 1])->toDateString()
                    : now()->toDateString();

                $invoices[] = Invoice::create([
                    'school_id'          => $schoolId,
                    'student_id'         => $enrollment->student_id,
                    'enrollment_id'      => $enrollment->id,
                    'academic_year_id'   => $enrollment->academic_year_id,
                    'fee_schedule_id'    => $feeSchedule->id,
                    'invoice_type'       => InvoiceType::TUITION->value,
                    'schedule_type'      => $this->paymentFrequency ?: 'yearly',
                    'status'             => InvoiceStatus::ISSUED->value,
                    'issue_date'         => now()->toDateString(),
                    'due_date'           => $dueDate,
                    'subtotal'           => $amount,
                    'vat_rate'           => 0,
                    'vat_amount'         => 0,
                    'total'              => $amount,
                    'paid_total'         => 0,
                    'balance_due'        => $amount,
                    'installment_number' => $k,
                ]);
            }
        }

        return $invoices;
    }

    public function with(): array
    {
        $schoolId = auth()->user()->school_id;

        $students = $this->studentPrefilled ? [] : Student::where('school_id', $schoolId)
            ->orderBy('name')->get()
            ->map(fn($s) => [
                'id'   => $s->id,
                'name' => $s->full_name . ($s->student_code ? ' — ' . $s->student_code : ''),
            ])->all();

        $classes = $this->academicYearId
            ? SchoolClass::where('school_id', $schoolId)
                ->where('academic_year_id', $this->academicYearId)
                ->with('grade')->orderBy('name')->get()
                ->map(fn($c) => [
                    'id'   => $c->id,
                    'name' => $c->name . ' — ' . ($c->grade?->name ?? ''),
                ])->all()
            : [];

        $feeSchedule = $this->feeScheduleId
            ? FeeSchedule::with('feeItems')->find($this->feeScheduleId)
            : null;

        $annual            = $feeSchedule?->feeItems->sum(fn($i) => $i->pivot->amount) ?? 0;
        $tuitionAnnual     = $feeSchedule
            ? $feeSchedule->feeItems->filter(fn($i) => strtoupper($i->code ?? '') !== 'INSCR')->sum(fn($i) => $i->pivot->amount)
            : 0;
        $inscriptionAnnual = $annual - $tuitionAnnual;
        $effectiveN = $this->customInstallments > 0 ? $this->customInstallments : 1;

        $frequencies = collect(FeeScheduleType::cases())
            ->map(fn($f) => ['id' => $f->value, 'name' => $f->label(), 'n' => $f->installments()])
            ->all();

        $prefilledStudent = $this->studentPrefilled && $this->studentId
            ? Student::with(['guardians'])->find($this->studentId)
            : null;

        $selectedClass = $this->schoolClassId
            ? SchoolClass::with('grade')->find($this->schoolClassId)
            : null;

        return [
            'students'         => $students,
            'prefilledStudent' => $prefilledStudent,
            'academicYears'    => AcademicYear::where('school_id', $schoolId)->orderByDesc('start_date')->get()
                ->map(fn($y) => ['id' => $y->id, 'name' => $y->name])->all(),
            'classes'          => $classes,
            'selectedClass'    => $selectedClass,
            'feeSchedule'      => $feeSchedule,
            'annual'           => $annual,
            'tuitionAnnual'    => $tuitionAnnual,
            'inscriptionAnnual'=> $inscriptionAnnual,
            'frequencies'      => $frequencies,
            'invoicePreviews'  => $this->buildInvoicePreviews($feeSchedule, $effectiveN),
        ];
    }

    private function buildInvoicePreviews(?FeeSchedule $schedule, int $n): array
    {
        if (! $schedule || ! $this->paymentFrequency || $n <= 0) return [];

        $previews = [];

        $inscriptionItems = $schedule->feeItems->filter(fn($i) => strtoupper($i->code ?? '') === 'INSCR');
        $regularItems     = $schedule->feeItems->filter(fn($i) => strtoupper($i->code ?? '') !== 'INSCR');

        if ($inscriptionItems->isNotEmpty()) {
            $previews[] = [
                'type'     => 'immediate',
                'label'    => 'Frais d\'inscription',
                'due_date' => now()->format('d/m/Y'),
                'amount'   => $inscriptionItems->sum(fn($i) => $i->pivot->amount),
                'items'    => $inscriptionItems->map(fn($i) => [
                    'name' => $i->name, 'amount' => $i->pivot->amount,
                ])->values()->all(),
            ];
        }

        if ($regularItems->isNotEmpty()) {
            $regularTotal = $regularItems->sum(fn($i) => $i->pivot->amount);
            $dueDates     = $this->computeDueDates($n);

            for ($k = 1; $k <= $n; $k++) {
                $previews[] = [
                    'type'     => 'installment',
                    'label'    => "Versement {$k}/{$n}",
                    'due_date' => $dueDates[$k - 1] ?? '—',
                    'amount'   => round($regularTotal / $n),
                    'items'    => $regularItems->map(fn($i) => [
                        'name' => $i->name, 'amount' => round($i->pivot->amount / $n),
                    ])->values()->all(),
                ];
            }
        }

        return $previews;
    }

    private function computeDueDates(int $n): array
    {
        $year      = now()->month >= 9 ? now()->year : now()->year - 1;
        $allMonths = [9, 10, 11, 12, 1, 2, 3, 4, 5, 6];

        $freq = FeeScheduleType::tryFrom($this->paymentFrequency);
        $namedMonths = match($freq) {
            FeeScheduleType::MONTHLY   => [9, 10, 11, 12, 1, 2, 3, 4, 5, 6],
            FeeScheduleType::BIMONTHLY => [9, 11, 1, 3, 5],
            FeeScheduleType::QUARTERLY => [9, 12, 3],
            FeeScheduleType::YEARLY    => [9],
            default                    => [9],
        };

        $months = count($namedMonths) === $n
            ? $namedMonths
            : array_map(fn($i) => $allMonths[min((int) round($i * 9 / max($n - 1, 1)), 9)], range(0, $n - 1));

        return array_map(function (int $month) use ($year): string {
            $y = $month >= 9 ? $year : $year + 1;
            return \Carbon\Carbon::create($y, $month, 1)->format('d/m/Y');
        }, $months);
    }
};
?>

<div>
    {{-- Header --}}
    <x-header separator progress-indicator>
        <x-slot:title>
            <div class="flex items-center gap-2 text-sm text-base-content/60">
                <a href="{{ route('admin.enrollments.index') }}" wire:navigate class="hover:text-primary">Inscriptions</a>
                <x-icon name="o-chevron-right" class="w-3 h-3"/>
                <span class="text-base-content">Nouvelle inscription</span>
            </div>
        </x-slot:title>
    </x-header>

    <x-form wire:submit="save">
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

        {{-- ══════════════════════════════════════════════════════ --}}
        {{-- LEFT COLUMN — Form                                     --}}
        {{-- ══════════════════════════════════════════════════════ --}}
        <div class="lg:col-span-7 space-y-5">

            {{-- ── Section 1: Élève ── --}}
            <x-card separator>
                <x-slot:title>
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 rounded-full bg-primary text-primary-content flex items-center justify-center text-xs font-black">1</div>
                        <span>Élève</span>
                    </div>
                </x-slot:title>

                @if($prefilledStudent)
                {{-- Rich read-only student card --}}
                <div class="flex items-start gap-4 p-4 rounded-xl border border-primary/25 bg-primary/5">
                    {{-- Avatar --}}
                    <div class="w-14 h-14 rounded-full overflow-hidden shrink-0 border-2 border-primary/20 flex items-center justify-center
                                {{ $prefilledStudent->photo ? '' : 'bg-primary/10' }}">
                        @if($prefilledStudent->photo)
                            <img src="{{ Storage::url($prefilledStudent->photo) }}" class="w-full h-full object-cover" />
                        @else
                            <span class="text-xl font-black text-primary">{{ substr($prefilledStudent->name, 0, 1) }}</span>
                        @endif
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <p class="font-bold text-base">{{ $prefilledStudent->full_name }}</p>
                            <x-badge value="Nouvel élève" class="badge-primary badge-sm" />
                        </div>
                        @if($prefilledStudent->student_code)
                        <p class="text-xs font-mono text-base-content/50 mt-0.5">{{ $prefilledStudent->student_code }}</p>
                        @endif
                        <div class="flex flex-wrap gap-x-4 gap-y-1 mt-2 text-xs text-base-content/60">
                            @if($prefilledStudent->date_of_birth)
                            <span class="flex items-center gap-1">
                                <x-icon name="o-cake" class="w-3.5 h-3.5" />
                                {{ $prefilledStudent->date_of_birth->format('d/m/Y') }}
                            </span>
                            @endif
                            @if($prefilledStudent->nationality)
                            <span class="flex items-center gap-1">
                                <x-icon name="o-flag" class="w-3.5 h-3.5" />
                                {{ $prefilledStudent->nationality }}
                            </span>
                            @endif
                            @if($prefilledStudent->guardians->isNotEmpty())
                            <span class="flex items-center gap-1">
                                <x-icon name="o-user-circle" class="w-3.5 h-3.5" />
                                {{ $prefilledStudent->guardians->first()->full_name }}
                            </span>
                            @endif
                        </div>
                    </div>
                    <a href="{{ route('admin.students.show', $prefilledStudent->uuid) }}" wire:navigate
                       class="btn btn-ghost btn-xs shrink-0" title="Voir le profil">
                        <x-icon name="o-arrow-top-right-on-square" class="w-3.5 h-3.5" />
                    </a>
                </div>
                @else
                <x-choices label="Élève *"
                           wire:model.live="studentId"
                           :options="$students"
                           option-value="id"
                           option-label="name"
                           single clearable
                           placeholder="Sélectionner un élève..."
                           required />
                @endif
            </x-card>

            {{-- ── Section 2: Classe ── --}}
            <x-card separator>
                <x-slot:title>
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 rounded-full {{ $schoolClassId ? 'bg-success text-success-content' : 'bg-base-300 text-base-content/50' }} flex items-center justify-center text-xs font-black">2</div>
                        <span>Classe</span>
                        @if($selectedClass)
                        <x-badge value="{{ $selectedClass->name }}" class="badge-success badge-sm" />
                        @endif
                    </div>
                </x-slot:title>

                <div class="space-y-4">
                    <x-choices label="Année scolaire *"
                               wire:model.live="academicYearId"
                               :options="$academicYears"
                               option-value="id" option-label="name"
                               single
                               placeholder="Choisir une année scolaire..."
                               required />

                    @if($academicYearId)
                    <x-choices label="Classe *"
                               wire:model.live="schoolClassId"
                               :options="$classes"
                               option-value="id" option-label="name"
                               single
                               placeholder="{{ count($classes) ? 'Choisir une classe...' : 'Aucune classe disponible' }}"
                               :disabled="! count($classes)"
                               required />
                    @endif

                    {{-- Selected class info --}}
                    @if($selectedClass)
                    <div class="grid grid-cols-2 gap-3 mt-1">
                        <div class="flex items-center gap-2 p-3 rounded-xl bg-base-200/60 text-sm">
                            <x-icon name="o-academic-cap" class="w-4 h-4 text-base-content/50 shrink-0" />
                            <div>
                                <p class="text-xs text-base-content/50">Niveau</p>
                                <p class="font-semibold">{{ $selectedClass->grade?->name ?? '—' }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 p-3 rounded-xl bg-base-200/60 text-sm">
                            <x-icon name="o-user-group" class="w-4 h-4 text-base-content/50 shrink-0" />
                            <div>
                                <p class="text-xs text-base-content/50">Classe</p>
                                <p class="font-semibold">{{ $selectedClass->name }}</p>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
            </x-card>

            {{-- ── Section 3: Mode de paiement ── --}}
            @if($feeSchedule)
            <x-card separator>
                <x-slot:title>
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 rounded-full {{ $paymentFrequency ? 'bg-success text-success-content' : 'bg-base-300 text-base-content/50' }} flex items-center justify-center text-xs font-black">3</div>
                        <span>Mode de paiement</span>
                    </div>
                </x-slot:title>

                <div class="space-y-4">
                    {{-- Frequency picker --}}
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                        @foreach($frequencies as $freq)
                        @php
                            $preview = $tuitionAnnual > 0 && $freq['n'] > 0 ? round($tuitionAnnual / $freq['n']) : 0;
                            $sel     = $paymentFrequency === $freq['id'];
                        @endphp
                        <button type="button"
                                wire:click="$set('paymentFrequency', '{{ $freq['id'] }}')"
                                class="flex flex-col items-center p-3 rounded-xl border-2 transition-all
                                       {{ $sel ? 'border-primary bg-primary/8 shadow-sm' : 'border-base-300 hover:border-primary/40' }}">
                            <span class="font-bold text-sm {{ $sel ? 'text-primary' : '' }}">{{ $freq['name'] }}</span>
                            <span class="text-xs mt-0.5 {{ $sel ? 'text-primary/60' : 'text-base-content/40' }}">× {{ $freq['n'] }}</span>
                            @if($annual > 0)
                            <span class="font-black text-sm mt-1.5 {{ $sel ? 'text-primary' : 'text-base-content/70' }}">
                                {{ number_format($preview, 0, ',', ' ') }}
                                <span class="text-xs font-normal">DJF</span>
                            </span>
                            @endif
                        </button>
                        @endforeach
                    </div>

                    {{-- Custom installments --}}
                    @if($paymentFrequency)
                    <div class="flex items-center gap-4 p-3 rounded-xl border border-base-300 bg-base-200/30">
                        <div class="flex-1">
                            <p class="text-sm font-semibold">Nombre de versements</p>
                            <p class="text-xs text-base-content/50">
                                Défaut : {{ collect($frequencies)->firstWhere('id', $paymentFrequency)['n'] ?? 1 }} — modifiable entre 1 et 36
                            </p>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <button type="button"
                                    wire:click="$set('customInstallments', max(1, $customInstallments - 1))"
                                    class="btn btn-circle btn-sm btn-outline">
                                <x-icon name="o-minus" class="w-4 h-4" />
                            </button>
                            <span class="w-10 text-center font-black text-xl text-primary">{{ $customInstallments }}</span>
                            <button type="button"
                                    wire:click="$set('customInstallments', min(36, $customInstallments + 1))"
                                    class="btn btn-circle btn-sm btn-outline">
                                <x-icon name="o-plus" class="w-4 h-4" />
                            </button>
                        </div>
                    </div>
                    @endif
                </div>
            </x-card>
            @endif

            {{-- ── Options + submit ── --}}
            <x-card>
                <div class="space-y-4">
                    <label class="flex items-start gap-3 cursor-pointer p-3 rounded-xl border border-base-300 hover:bg-base-200/50 transition-colors {{ $confirmImmediately ? 'border-success/40 bg-success/5' : '' }}">
                        <input type="checkbox" wire:model.live="confirmImmediately" class="checkbox checkbox-success mt-0.5" />
                        <div>
                            <p class="font-semibold text-sm">Confirmer immédiatement l'inscription</p>
                            <p class="text-xs text-base-content/50 mt-0.5">L'élève sera directement inscrit(e) sans passer par le statut "En attente"</p>
                        </div>
                    </label>

                    <div class="flex items-center gap-3 pt-1">
                        <x-button label="Annuler" :link="route('admin.enrollments.index')"
                                  class="btn-ghost" wire:navigate />
                        <x-button label="Créer l'inscription" type="submit"
                                  icon="o-check" class="btn-primary flex-1 sm:flex-none" spinner="save" />
                    </div>
                </div>
            </x-card>

        </div>

        {{-- ══════════════════════════════════════════════════════ --}}
        {{-- RIGHT COLUMN — Fee summary + Invoice preview          --}}
        {{-- ══════════════════════════════════════════════════════ --}}
        <div class="lg:col-span-5">
            <div class="sticky top-6 space-y-4">

                @if(! $schoolClassId)
                {{-- Empty state --}}
                <div class="rounded-2xl border-2 border-dashed border-base-300 p-8 text-center text-base-content/30">
                    <x-icon name="o-banknotes" class="w-12 h-12 mx-auto mb-3 opacity-30" />
                    <p class="text-sm font-semibold">Sélectionnez une classe</p>
                    <p class="text-xs mt-1">Le barème de frais apparaîtra ici</p>
                </div>

                @elseif(! $feeSchedule)
                <x-alert icon="o-exclamation-triangle" class="alert-warning">
                    <p class="font-semibold">Aucun barème configuré</p>
                    <p class="text-sm mt-1">
                        Aucun barème actif n'existe pour ce niveau.
                        <a href="{{ route('admin.finance.fee-schedules.index') }}" wire:navigate class="underline font-semibold">Créer un barème</a>
                    </p>
                </x-alert>

                @else

                {{-- Fee schedule breakdown --}}
                <x-card>
                    <x-slot:title>
                        <div class="flex items-center justify-between w-full">
                            <div class="flex items-center gap-2">
                                <x-icon name="o-banknotes" class="w-4 h-4 text-primary" />
                                <span class="text-sm">{{ $feeSchedule->name }}</span>
                            </div>
                            <span class="text-xs text-base-content/40">Année complète</span>
                        </div>
                    </x-slot:title>

                    <div class="space-y-1.5">
                        @foreach($feeSchedule->feeItems as $item)
                        @php $isInscr = strtoupper($item->code ?? '') === 'INSCR'; @endphp
                        <div wire:key="fi-{{ $item->id }}" class="flex items-center justify-between text-sm py-1">
                            <div class="flex items-center gap-2">
                                @if($isInscr)
                                <span class="w-1.5 h-1.5 rounded-full bg-warning shrink-0"></span>
                                @else
                                <span class="w-1.5 h-1.5 rounded-full bg-primary/40 shrink-0"></span>
                                @endif
                                <span class="text-base-content/70">{{ $item->name }}</span>
                                @if($isInscr)
                                <x-badge value="Inscription" class="badge-warning badge-xs" />
                                @endif
                            </div>
                            <span class="font-semibold tabular-nums">{{ number_format($item->pivot->amount, 0, ',', ' ') }} DJF</span>
                        </div>
                        @endforeach

                        <div class="flex justify-between font-bold text-sm pt-2.5 border-t border-base-300 mt-1">
                            <span>Total annuel</span>
                            <span class="text-primary tabular-nums">{{ number_format($annual, 0, ',', ' ') }} DJF</span>
                        </div>
                    </div>
                </x-card>

                {{-- Invoice preview timeline --}}
                @if(count($invoicePreviews) > 0)
                <x-card>
                    <x-slot:title>
                        <div class="flex items-center justify-between w-full">
                            <div class="flex items-center gap-2">
                                <x-icon name="o-document-text" class="w-4 h-4 text-primary" />
                                <span class="text-sm">Aperçu des factures</span>
                            </div>
                            <x-badge value="{{ count($invoicePreviews) }}" class="badge-primary badge-sm" />
                        </div>
                    </x-slot:title>

                    <div class="relative">
                        {{-- Vertical timeline line --}}
                        <div class="absolute left-3 top-4 bottom-4 w-px bg-base-300"></div>

                        <div class="space-y-3">
                            @foreach($invoicePreviews as $idx => $inv)
                            @php $isImmediate = $inv['type'] === 'immediate'; @endphp
                            <div wire:key="inv-{{ $idx }}" class="flex gap-3 relative">
                                {{-- Timeline dot --}}
                                <div class="w-6 h-6 rounded-full border-2 shrink-0 flex items-center justify-center z-10
                                            {{ $isImmediate
                                                ? 'border-warning bg-warning text-warning-content'
                                                : 'border-base-300 bg-base-100 text-base-content/40' }}">
                                    @if($isImmediate)
                                    <x-icon name="o-bolt" class="w-3 h-3" />
                                    @else
                                    <span class="text-xs font-bold">{{ $idx }}</span>
                                    @endif
                                </div>

                                {{-- Card --}}
                                <div class="flex-1 rounded-xl border p-3 mb-1
                                            {{ $isImmediate ? 'border-warning/30 bg-warning/5' : 'border-base-200 bg-base-100' }}">
                                    <div class="flex items-start justify-between gap-2 mb-1.5">
                                        <div>
                                            @if($isImmediate)
                                            <span class="text-xs font-bold text-warning uppercase tracking-wide">À payer immédiatement</span>
                                            @else
                                            <span class="text-xs font-bold text-base-content/60">{{ $inv['label'] }}</span>
                                            @endif
                                            <p class="text-xs text-base-content/40 mt-0.5">Échéance : {{ $inv['due_date'] }}</p>
                                        </div>
                                        <span class="font-black text-sm {{ $isImmediate ? 'text-warning' : 'text-primary' }} tabular-nums whitespace-nowrap">
                                            {{ number_format($inv['amount'], 0, ',', ' ') }} DJF
                                        </span>
                                    </div>
                                    <div class="space-y-0.5 border-t border-base-200 pt-1.5">
                                        @foreach($inv['items'] as $line)
                                        <div class="flex justify-between text-xs">
                                            <span class="text-base-content/45 truncate">{{ $line['name'] }}</span>
                                            <span class="font-medium tabular-nums ml-2">{{ number_format($line['amount'], 0, ',', ' ') }}</span>
                                        </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Grand total --}}
                    @php $grandTotal = collect($invoicePreviews)->sum('amount'); @endphp
                    <div class="flex justify-between items-center mt-4 pt-3 border-t border-base-300">
                        <span class="text-sm font-bold">Total à percevoir</span>
                        <span class="font-black text-primary text-lg tabular-nums">{{ number_format($grandTotal, 0, ',', ' ') }} DJF</span>
                    </div>
                </x-card>
                @elseif($schoolClassId && $feeSchedule && ! $paymentFrequency)
                <div class="rounded-xl border border-dashed border-base-300 p-5 text-center text-base-content/40">
                    <x-icon name="o-calendar-days" class="w-8 h-8 mx-auto mb-2 opacity-30" />
                    <p class="text-xs">Choisissez un mode de paiement pour voir l'aperçu des factures</p>
                </div>
                @endif

                @endif
            </div>
        </div>

    </div>
    </x-form>
</div>
