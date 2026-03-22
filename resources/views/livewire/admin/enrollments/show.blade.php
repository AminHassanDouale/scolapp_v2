<?php
use App\Models\Enrollment;
use App\Enums\EnrollmentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Actions\ConfirmEnrollmentAction;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Mary\Traits\Toast;

new #[Layout('layouts.app')] class extends Component {
    use Toast;

    public Enrollment $enrollment;

    public function mount(string $uuid): void
    {
        $schoolId = auth()->user()->school_id;
        $this->enrollment = Enrollment::where('uuid', $uuid)
            ->where('school_id', $schoolId)
            ->with([
                'student.guardians',
                'schoolClass.grade',
                'academicYear',
                'studentFeePlan.feeSchedule',
                'invoices' => fn($q) => $q->orderBy('due_date'),
                'invoices.payments',
            ])
            ->firstOrFail();

        if (session()->has('success')) {
            $this->success(session()->pull('success'), position: 'toast-top toast-end', icon: 'o-check-circle', css: 'alert-success', timeout: 3000);
        }
        if (session()->has('warning')) {
            $this->warning(session()->pull('warning'), position: 'toast-bottom toast-end', icon: 'o-exclamation-triangle', css: 'alert-warning', timeout: 4000);
        }
    }

    public function confirmEnrollment(): void
    {
        try {
            app(ConfirmEnrollmentAction::class)($this->enrollment);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage(), position: 'toast-top toast-center', icon: 'o-x-circle', css: 'alert-error', timeout: 4000);
            return;
        }
        $this->enrollment = $this->enrollment->fresh([
            'student.guardians', 'schoolClass.grade', 'academicYear',
            'studentFeePlan.feeSchedule', 'invoices.payments',
        ]);
        $this->success('Inscription confirmée.', position: 'toast-top toast-end', icon: 'o-banknotes', css: 'alert-success', timeout: 3000);
    }

    public function cancelEnrollment(): void
    {
        $this->enrollment->update([
            'status'           => EnrollmentStatus::CANCELLED,
            'cancelled_at'     => now()->toDateString(),
        ]);
        $this->enrollment->invoices()
            ->whereIn('status', [InvoiceStatus::DRAFT->value, InvoiceStatus::ISSUED->value])
            ->update(['status' => InvoiceStatus::CANCELLED->value]);
        $this->enrollment = $this->enrollment->fresh([
            'student.guardians', 'schoolClass.grade', 'academicYear',
            'studentFeePlan.feeSchedule', 'invoices.payments',
        ]);
        $this->success('Inscription annulée.', position: 'toast-top toast-end', icon: 'o-x-mark', css: 'alert-success', timeout: 3000);
    }

    public function with(): array
    {
        $invoices     = $this->enrollment->invoices ?? collect();
        $active       = $invoices->filter(fn($i) => $i->status !== InvoiceStatus::CANCELLED);
        $totalBilled  = $active->sum('total');
        $totalPaid    = $active->sum('paid_total');
        $totalBalance = $active->sum('balance_due');
        $payPct       = $totalBilled > 0 ? round(($totalPaid / $totalBilled) * 100) : 0;
        $overdueCount = $active->filter(fn($i) => $i->status === InvoiceStatus::OVERDUE)->count();

        $student         = $this->enrollment->student;
        $primaryGuardian = $student?->guardians->where('pivot.is_primary', true)->first()
                        ?? $student?->guardians->first();
        $feePlan = $this->enrollment->studentFeePlan;

        // Pre-compute per-invoice display values so the Blade template stays simple
        $invoiceRows = $invoices->map(function ($inv) {
            $isReg      = $inv->invoice_type === InvoiceType::REGISTRATION;
            $isPaid     = $inv->status === InvoiceStatus::PAID;
            $isOverdue  = $inv->status === InvoiceStatus::OVERDUE;
            $isCancelled = $inv->status === InvoiceStatus::CANCELLED;
            $pct        = $inv->total > 0 ? round(($inv->paid_total / $inv->total) * 100) : 0;

            if ($isCancelled) {
                $dotColor   = 'border-base-300 bg-base-200 text-base-content/30';
                $cardBorder = 'border-base-200 opacity-50';
            } elseif ($isPaid) {
                $dotColor   = 'border-success bg-success text-success-content';
                $cardBorder = 'border-success/20 bg-success/5';
            } elseif ($isOverdue) {
                $dotColor   = 'border-error bg-error text-error-content';
                $cardBorder = 'border-error/30 bg-error/5';
            } elseif ($isReg) {
                $dotColor   = 'border-warning bg-warning text-warning-content';
                $cardBorder = 'border-warning/20 bg-warning/5';
            } else {
                $dotColor   = 'border-base-300 bg-base-100 text-base-content/50';
                $cardBorder = 'border-base-200';
            }

            $progressClass = $isPaid ? 'progress-success' : ($isOverdue ? 'progress-error' : 'progress-primary');
            $amountClass   = $isReg ? 'text-warning' : 'text-primary';
            $statusBadge   = 'badge-' . $inv->status->color();

            return (object) [
                'model'         => $inv,
                'isRegistration'=> $isReg,
                'isPaid'        => $isPaid,
                'isOverdue'     => $isOverdue,
                'isCancelled'   => $isCancelled,
                'pct'           => $pct,
                'dotColor'      => $dotColor,
                'cardBorder'    => $cardBorder,
                'progressClass' => $progressClass,
                'amountClass'   => $amountClass,
                'statusBadge'   => $statusBadge,
            ];
        })->values();

        $invoiceStats = [
            ['label' => 'Payées',       'class' => 'badge-success', 'count' => $active->filter(fn($i) => $i->status === InvoiceStatus::PAID)->count()],
            ['label' => 'Part. payées', 'class' => 'badge-warning', 'count' => $active->filter(fn($i) => $i->status === InvoiceStatus::PARTIALLY_PAID)->count()],
            ['label' => 'En retard',    'class' => 'badge-error',   'count' => $active->filter(fn($i) => $i->status === InvoiceStatus::OVERDUE)->count()],
            ['label' => 'Émises',       'class' => 'badge-info',    'count' => $active->filter(fn($i) => $i->status === InvoiceStatus::ISSUED)->count()],
            ['label' => 'Annulées',     'class' => 'badge-ghost',   'count' => $invoices->filter(fn($i) => $i->status === InvoiceStatus::CANCELLED)->count()],
        ];

        return compact(
            'invoices', 'invoiceRows', 'invoiceStats',
            'totalBilled', 'totalPaid', 'totalBalance', 'payPct', 'overdueCount',
            'primaryGuardian', 'feePlan'
        );
    }
};
?>

<div>
    {{-- ── Header ── --}}
    <x-header separator progress-indicator>
        <x-slot:title>
            <div class="flex flex-wrap items-center gap-2 text-sm text-base-content/60">
                <a href="{{ route('admin.enrollments.index') }}" wire:navigate class="hover:text-primary">Inscriptions</a>
                <x-icon name="o-chevron-right" class="w-3 h-3"/>
                <span class="text-base-content font-semibold">{{ $enrollment->student?->full_name }}</span>
                <span class="font-mono text-xs text-base-content/40">{{ $enrollment->reference }}</span>
            </div>
        </x-slot:title>
        <x-slot:actions>
            @php $status = $enrollment->status; @endphp
            @if($status === EnrollmentStatus::HOLD)
                <x-button label="Confirmer l'inscription" icon="o-check-circle"
                          wire:click="confirmEnrollment"
                          wire:confirm="Confirmer cette inscription ?"
                          class="btn-success" spinner="confirmEnrollment" />
                <x-button label="Annuler" icon="o-x-circle"
                          wire:click="cancelEnrollment"
                          wire:confirm="Annuler cette inscription ? Les factures en cours seront annulées."
                          class="btn-error btn-outline" spinner="cancelEnrollment" />
            @elseif($status === EnrollmentStatus::CONFIRMED)
                <x-button label="Annuler l'inscription" icon="o-x-circle"
                          wire:click="cancelEnrollment"
                          wire:confirm="Annuler cette inscription confirmée ?"
                          class="btn-error btn-outline btn-sm" spinner="cancelEnrollment" />
            @endif
            <a href="{{ route('admin.students.show', $enrollment->student?->uuid) }}" wire:navigate>
                <x-button label="Voir l'élève" icon="o-arrow-top-right-on-square" class="btn-ghost btn-sm" />
            </a>
        </x-slot:actions>
    </x-header>

    {{-- ── Status bar ── --}}
    @php
        $statusColors = [
            EnrollmentStatus::CONFIRMED->value => 'bg-success/10 border-success/30 text-success',
            EnrollmentStatus::HOLD->value      => 'bg-warning/10 border-warning/30 text-warning',
            EnrollmentStatus::CANCELLED->value => 'bg-error/10 border-error/30 text-error',
        ];
        $statusIcons = [
            EnrollmentStatus::CONFIRMED->value => 'o-check-circle',
            EnrollmentStatus::HOLD->value      => 'o-clock',
            EnrollmentStatus::CANCELLED->value => 'o-x-circle',
        ];
        $sc    = $statusColors[$enrollment->status->value] ?? '';
        $si    = $statusIcons[$enrollment->status->value]  ?? 'o-information-circle';
    @endphp
    <div class="flex items-center justify-between px-4 py-3 rounded-xl border mb-6 {{ $sc }}">
        <div class="flex items-center gap-2 font-semibold text-sm">
            <x-icon :name="$si" class="w-4 h-4" />
            {{ $enrollment->status->label() }}
            @if($enrollment->confirmed_at)
                <span class="font-normal opacity-70">— Confirmée le {{ $enrollment->confirmed_at->format('d/m/Y') }}</span>
            @endif
            @if($enrollment->cancelled_at)
                <span class="font-normal opacity-70">— Annulée le {{ $enrollment->cancelled_at->format('d/m/Y') }}</span>
            @endif
        </div>
        @if($overdueCount > 0)
        <x-badge value="{{ $overdueCount }} facture(s) en retard" class="badge-error badge-sm" />
        @endif
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

        {{-- ════════════════════════════════════════════════════════ --}}
        {{-- LEFT COLUMN                                              --}}
        {{-- ════════════════════════════════════════════════════════ --}}
        <div class="lg:col-span-7 space-y-5">

            {{-- ── Student card ── --}}
            <x-card>
                @php $student = $enrollment->student; @endphp
                <div class="flex items-start gap-4">
                    {{-- Avatar --}}
                    <div class="w-16 h-16 rounded-2xl overflow-hidden shrink-0 border-2 border-base-300 flex items-center justify-center
                                {{ $student?->photo ? '' : 'bg-primary/10' }}">
                        @if($student?->photo)
                            <img src="{{ Storage::url($student->photo) }}" class="w-full h-full object-cover" />
                        @else
                            <span class="text-2xl font-black text-primary">{{ substr($student?->name ?? '?', 0, 1) }}</span>
                        @endif
                    </div>
                    {{-- Info --}}
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2 mb-1">
                            <h2 class="font-bold text-lg">{{ $student?->full_name }}</h2>
                            @if($student?->student_code)
                            <x-badge value="{{ $student->student_code }}" class="badge-neutral badge-sm font-mono" />
                            @endif
                        </div>
                        <div class="flex flex-wrap gap-x-5 gap-y-1 text-sm text-base-content/60">
                            @if($student?->date_of_birth)
                            <span class="flex items-center gap-1.5">
                                <x-icon name="o-cake" class="w-3.5 h-3.5" />
                                {{ $student->date_of_birth->format('d/m/Y') }}
                                <span class="text-base-content/40">({{ $student->date_of_birth->age }} ans)</span>
                            </span>
                            @endif
                            @if($student?->nationality)
                            <span class="flex items-center gap-1.5">
                                <x-icon name="o-flag" class="w-3.5 h-3.5" />
                                {{ $student->nationality }}
                            </span>
                            @endif
                            @if($student?->gender)
                            <span class="flex items-center gap-1.5">
                                <x-icon name="o-user" class="w-3.5 h-3.5" />
                                {{ $student->gender === 'male' ? 'Masculin' : 'Féminin' }}
                            </span>
                            @endif
                        </div>
                    </div>
                    <a href="{{ route('admin.students.show', $student?->uuid) }}" wire:navigate
                       class="btn btn-ghost btn-xs shrink-0">
                        <x-icon name="o-arrow-top-right-on-square" class="w-3.5 h-3.5" />
                    </a>
                </div>
            </x-card>

            {{-- ── Enrollment details ── --}}
            <x-card title="Détails de l'inscription" separator>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 text-sm">
                    <div>
                        <p class="text-xs text-base-content/50 mb-1">Référence</p>
                        <p class="font-mono font-bold">{{ $enrollment->reference }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-base-content/50 mb-1">Année scolaire</p>
                        <p class="font-semibold">{{ $enrollment->academicYear?->name }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-base-content/50 mb-1">Classe</p>
                        <p class="font-semibold">{{ $enrollment->schoolClass?->name }}</p>
                        @if($enrollment->schoolClass?->grade)
                        <p class="text-xs text-base-content/40">{{ $enrollment->schoolClass->grade->name }}</p>
                        @endif
                    </div>
                    <div>
                        <p class="text-xs text-base-content/50 mb-1">Date d'inscription</p>
                        <p class="font-semibold">{{ ($enrollment->enrolled_at ?? $enrollment->created_at)->format('d/m/Y') }}</p>
                    </div>
                    @if($feePlan)
                    <div>
                        <p class="text-xs text-base-content/50 mb-1">Barème</p>
                        <p class="font-semibold">{{ $feePlan->feeSchedule?->name }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-base-content/50 mb-1">Fréquence de paiement</p>
                        <p class="font-semibold">{{ $feePlan->payment_frequency?->label() ?? '—' }}</p>
                        @if($feePlan->installments)
                        <p class="text-xs text-base-content/40">{{ $feePlan->installments }} versements</p>
                        @endif
                    </div>
                    @endif
                    @if($enrollment->notes)
                    <div class="col-span-full">
                        <p class="text-xs text-base-content/50 mb-1">Notes</p>
                        <p class="text-sm italic text-base-content/70">{{ $enrollment->notes }}</p>
                    </div>
                    @endif
                </div>
            </x-card>

            {{-- ── Invoice timeline ── --}}
            <x-card separator>
                <x-slot:title>
                    <div class="flex items-center justify-between w-full">
                        <div class="flex items-center gap-2">
                            <x-icon name="o-document-text" class="w-4 h-4 text-primary" />
                            <span>Factures</span>
                        </div>
                        <x-badge value="{{ $invoices->count() }}" class="badge-neutral badge-sm" />
                    </div>
                </x-slot:title>

                @if($invoices->isEmpty())
                <div class="text-center py-8 text-base-content/30">
                    <x-icon name="o-document-text" class="w-10 h-10 mx-auto mb-2 opacity-30" />
                    <p class="text-sm">Aucune facture générée</p>
                    @if($enrollment->status === EnrollmentStatus::HOLD)
                    <p class="text-xs mt-1">Confirmez l'inscription pour générer les factures</p>
                    @endif
                </div>
                @else

                <div class="relative">
                    {{-- Timeline line --}}
                    <div class="absolute left-3 top-4 bottom-4 w-px bg-base-300"></div>

                    <div class="space-y-3">
                    @foreach($invoiceRows as $row)
                    <div wire:key="inv-{{ $row->model->id }}" class="flex gap-3 relative">
                        {{-- Dot --}}
                        <div class="w-6 h-6 rounded-full border-2 shrink-0 flex items-center justify-center z-10 {{ $row->dotColor }}">
                            @if($row->isRegistration)
                                <x-icon name="o-bolt" class="w-3 h-3" />
                            @elseif($row->isPaid)
                                <x-icon name="o-check" class="w-3 h-3" />
                            @elseif($row->isOverdue)
                                <x-icon name="o-exclamation-triangle" class="w-3 h-3" />
                            @else
                                <span class="text-xs font-bold">{{ $row->model->installment_number ?? $loop->index + 1 }}</span>
                            @endif
                        </div>

                        {{-- Card --}}
                        <div class="flex-1 rounded-xl border p-3 mb-1 {{ $row->cardBorder }}">
                            <div class="flex items-start justify-between gap-2 mb-2">
                                <div>
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="font-bold text-sm font-mono">{{ $row->model->reference }}</span>
                                        <x-badge :value="$row->model->status->label()" class="{{ $row->statusBadge }} badge-xs" />
                                        @if($row->isRegistration)
                                        <x-badge value="Inscription" class="badge-warning badge-xs" />
                                        @endif
                                    </div>
                                    <p class="text-xs text-base-content/50 mt-0.5">
                                        {{ $row->model->invoice_type?->label() }}
                                        @if($row->model->installment_number)
                                        · Versement {{ $row->model->installment_number }}
                                        @endif
                                        · Échéance {{ $row->model->due_date?->format('d/m/Y') ?? '—' }}
                                    </p>
                                </div>
                                <div class="text-right shrink-0">
                                    <p class="font-black text-sm tabular-nums">{{ number_format($row->model->total, 0, ',', ' ') }} DJF</p>
                                    @if($row->model->balance_due > 0 && !$row->isCancelled)
                                    <p class="text-xs text-error tabular-nums">Reste : {{ number_format($row->model->balance_due, 0, ',', ' ') }}</p>
                                    @endif
                                </div>
                            </div>

                            @if(!$row->isCancelled && $row->model->total > 0)
                            <div>
                                <div class="flex justify-between text-xs text-base-content/40 mb-0.5">
                                    <span>{{ number_format($row->model->paid_total, 0, ',', ' ') }} DJF payés</span>
                                    <span>{{ $row->pct }}%</span>
                                </div>
                                <progress class="progress {{ $row->progressClass }} h-1.5 w-full"
                                          value="{{ $row->model->paid_total }}" max="{{ $row->model->total }}"></progress>
                            </div>
                            @endif

                            @if($row->model->payments->isNotEmpty())
                            <div class="mt-2 space-y-1 border-t border-base-200 pt-1.5">
                                @foreach($row->model->payments as $payment)
                                <div class="flex items-center justify-between text-xs text-base-content/60">
                                    <span class="flex items-center gap-1.5">
                                        <x-icon name="o-banknotes" class="w-3 h-3 text-success" />
                                        {{ $payment->paid_at?->format('d/m/Y') ?? '—' }}
                                        @if($payment->method)
                                        · {{ ucfirst($payment->method) }}
                                        @endif
                                    </span>
                                    <span class="font-semibold text-success tabular-nums">+{{ number_format($payment->amount, 0, ',', ' ') }}</span>
                                </div>
                                @endforeach
                            </div>
                            @endif
                        </div>
                    </div>
                    @endforeach
                    </div>
                </div>
                @endif
            </x-card>

        </div>

        {{-- ════════════════════════════════════════════════════════ --}}
        {{-- RIGHT COLUMN — sticky sidebar                           --}}
        {{-- ════════════════════════════════════════════════════════ --}}
        <div class="lg:col-span-5">
            <div class="sticky top-6 space-y-4">

                {{-- ── Financial summary ── --}}
                <x-card>
                    <x-slot:title>
                        <div class="flex items-center gap-2">
                            <x-icon name="o-banknotes" class="w-4 h-4 text-primary" />
                            <span>Résumé financier</span>
                        </div>
                    </x-slot:title>

                    <div class="space-y-2.5 text-sm">
                        <div class="flex justify-between items-center">
                            <span class="text-base-content/60">Total facturé</span>
                            <span class="font-semibold tabular-nums">{{ number_format($totalBilled, 0, ',', ' ') }} DJF</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-base-content/60">Total payé</span>
                            <span class="font-semibold text-success tabular-nums">{{ number_format($totalPaid, 0, ',', ' ') }} DJF</span>
                        </div>
                        <div class="flex justify-between items-center pt-2 border-t border-base-200">
                            <span class="font-bold">Solde restant</span>
                            <span class="font-black text-lg tabular-nums {{ $totalBalance > 0 ? 'text-error' : 'text-success' }}">
                                {{ number_format($totalBalance, 0, ',', ' ') }} DJF
                            </span>
                        </div>
                    </div>

                    @if($totalBilled > 0)
                    <div class="mt-4">
                        <div class="flex justify-between text-xs text-base-content/50 mb-1">
                            <span>Progression du paiement</span>
                            <span class="font-semibold {{ $payPct >= 100 ? 'text-success' : '' }}">{{ $payPct }}%</span>
                        </div>
                        <progress class="progress {{ $payPct >= 100 ? 'progress-success' : 'progress-primary' }} w-full h-2"
                                  value="{{ $totalPaid }}" max="{{ $totalBilled }}"></progress>
                        @if($payPct >= 100)
                        <p class="text-xs text-success text-center mt-1 font-semibold">Intégralement réglé ✓</p>
                        @endif
                    </div>
                    @endif
                </x-card>

                {{-- ── Primary guardian ── --}}
                @if($primaryGuardian)
                <x-card>
                    <x-slot:title>
                        <div class="flex items-center gap-2">
                            <x-icon name="o-user-circle" class="w-4 h-4 text-primary" />
                            <span>Responsable légal</span>
                        </div>
                    </x-slot:title>

                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full overflow-hidden shrink-0 flex items-center justify-center
                                    {{ $primaryGuardian->photo ? '' : 'bg-secondary/10' }}">
                            @if($primaryGuardian->photo)
                                <img src="{{ Storage::url($primaryGuardian->photo) }}" class="w-full h-full object-cover" />
                            @else
                                <span class="font-black text-secondary">{{ substr($primaryGuardian->name, 0, 1) }}</span>
                            @endif
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-bold text-sm">{{ $primaryGuardian->full_name ?? $primaryGuardian->name }}</p>
                            @if($primaryGuardian->pivot?->relation)
                            <p class="text-xs text-base-content/50">{{ ucfirst($primaryGuardian->pivot->relation) }}</p>
                            @endif
                        </div>
                    </div>
                    <div class="mt-3 space-y-1.5 text-sm">
                        @if($primaryGuardian->phone)
                        <a href="tel:{{ $primaryGuardian->phone }}"
                           class="flex items-center gap-2 text-base-content/70 hover:text-primary transition-colors">
                            <x-icon name="o-phone" class="w-3.5 h-3.5 shrink-0" />
                            {{ $primaryGuardian->phone }}
                        </a>
                        @endif
                        @if($primaryGuardian->email)
                        <a href="mailto:{{ $primaryGuardian->email }}"
                           class="flex items-center gap-2 text-base-content/70 hover:text-primary transition-colors">
                            <x-icon name="o-envelope" class="w-3.5 h-3.5 shrink-0" />
                            {{ $primaryGuardian->email }}
                        </a>
                        @endif
                    </div>
                </x-card>
                @endif

                {{-- ── Fee plan ── --}}
                @if($feePlan)
                <x-card>
                    <x-slot:title>
                        <div class="flex items-center gap-2">
                            <x-icon name="o-clipboard-document-list" class="w-4 h-4 text-primary" />
                            <span>Plan de paiement</span>
                        </div>
                    </x-slot:title>

                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-base-content/60">Barème</span>
                            <span class="font-semibold">{{ $feePlan->feeSchedule?->name ?? '—' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-base-content/60">Fréquence</span>
                            <span class="font-semibold">{{ $feePlan->payment_frequency?->label() ?? '—' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-base-content/60">Versements</span>
                            <span class="font-semibold">{{ $feePlan->installments() }}</span>
                        </div>
                        @if($feePlan->discount_pct || $feePlan->discount_amount)
                        <div class="flex justify-between text-success">
                            <span>Remise</span>
                            <span class="font-semibold">
                                @if($feePlan->discount_pct)
                                    {{ $feePlan->discount_pct }}%
                                @endif
                                @if($feePlan->discount_amount)
                                    {{ number_format($feePlan->discount_amount, 0, ',', ' ') }} DJF
                                @endif
                            </span>
                        </div>
                        @endif
                    </div>
                </x-card>
                @endif

                {{-- ── Invoice stats ── --}}
                @if($invoices->isNotEmpty())
                <x-card title="Statut des factures">
                    <div class="space-y-1.5">
                        @foreach($invoiceStats as $stat)
                        @if($stat['count'] > 0)
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-base-content/60">{{ $stat['label'] }}</span>
                            <x-badge :value="$stat['count']" class="{{ $stat['class'] }} badge-sm" />
                        </div>
                        @endif
                        @endforeach
                    </div>
                </x-card>
                @endif

            </div>
        </div>

    </div>
</div>
