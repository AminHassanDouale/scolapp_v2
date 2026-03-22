<?php
use App\Models\Student;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Guardian;
use App\Models\User;
use App\Models\School;
use App\Mail\GuardianWelcomeMail;
use App\Enums\EnrollmentStatus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Mary\Traits\Toast;

new #[Layout('layouts.app')] class extends Component {
    use Toast;

    public Student $student;
    public string  $activeTab = 'info';

    public function mount(string $uuid): void
    {
        $this->student = Student::where('school_id', auth()->user()->school_id)
            ->where('uuid', $uuid)
            ->with([
                'currentEnrollment.schoolClass.grade',
                'currentEnrollment.academicYear',
                'guardians',
                'invoices' => fn($q) => $q->latest()->limit(10),
                'payments' => fn($q) => $q->latest()->limit(10),
            ])
            ->firstOrFail();
    }

    public function toggleActive(): void
    {
        $this->student->update(['is_active' => ! $this->student->is_active]);
        $this->student->refresh();
        $this->success($this->student->is_active ? 'Élève activé.' : 'Élève désactivé.', position: 'toast-top toast-end', icon: 'o-bolt', css: 'alert-success', timeout: 3000);
    }

    public function sendGuardianCredentials(int $guardianId): void
    {
        abort_unless(auth()->user()->hasRole(['super-admin', 'admin']), 403);

        $guardian = Guardian::find($guardianId);

        if (!$guardian || !$guardian->email) {
            $this->error('Ce tuteur n\'a pas d\'adresse email.', position: 'toast-top toast-center', icon: 'o-x-circle', css: 'alert-error', timeout: 4000);
            return;
        }

        $school   = School::findOrFail(auth()->user()->school_id);
        $password = Str::password(12, symbols: false);

        // Create account if it doesn't exist yet
        if (!$guardian->user_id) {
            $user = User::create([
                'uuid'       => (string) Str::uuid(),
                'school_id'  => auth()->user()->school_id,
                'name'       => $guardian->full_name,
                'email'      => $guardian->email,
                'password'   => Hash::make($password),
                'ui_lang'    => 'fr',
                'timezone'   => 'Africa/Djibouti',
            ]);
            $user->assignRole('guardian');
            $guardian->update(['user_id' => $user->id]);
            $guardian->refresh();
        } else {
            // Account exists — reset password and resend
            $guardian->user->update(['password' => Hash::make($password)]);
        }

        try {
            $this->student->load(['enrollments.schoolClass', 'enrollments.grade']);
            Mail::to($guardian->email)->send(
                new GuardianWelcomeMail($guardian, $school, $this->student, $password)
            );
            $this->success(
                "Identifiants envoyés à {$guardian->email}",
                "Tuteur : {$guardian->full_name}",
                position: 'toast-top toast-end', icon: 'o-paper-airplane', css: 'alert-success', timeout: 4000
            );
            // Refresh student to update displayed user_id
            $this->student = $this->student->fresh(['guardians']);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('GuardianWelcomeMail failed: ' . $e->getMessage());
            $this->error('Email non envoyé : ' . $e->getMessage(), position: 'toast-top toast-center', icon: 'o-exclamation-triangle', css: 'alert-error', timeout: 5000);
        }
    }

    public function with(): array
    {
        $enrollment = $this->student->currentEnrollment;

        $financeSummary = [
            'total'     => $this->student->invoices()->sum('total'),
            'paid'      => $this->student->invoices()->sum('paid_total'),
            'balance'   => $this->student->invoices()->sum('balance_due'),
            'overdue'   => $this->student->invoices()->where('status', 'overdue')->count(),
        ];

        return [
            'student'         => $this->student,
            'enrollment'      => $enrollment,
            'financeSummary'  => $financeSummary,
            'allEnrollments'  => Enrollment::where('student_id', $this->student->id)
                ->with('academicYear', 'schoolClass')
                ->orderByDesc('enrolled_at')
                ->get(),
            'invoices' => $this->student->invoices,
            'payments' => $this->student->payments,
        ];
    }
};
?>

<div>
    {{-- Breadcrumb + Actions --}}
    <x-header separator progress-indicator>
        <x-slot:title>
            <div class="flex items-center gap-2 text-sm text-base-content/60">
                <a href="{{ route('admin.students.index') }}" wire:navigate class="hover:text-primary">
                    {{ __('students.title') }}
                </a>
                <x-icon name="o-chevron-right" class="w-3 h-3"/>
                <span class="text-base-content font-semibold">{{ $student->full_name }}</span>
            </div>
        </x-slot:title>
        <x-slot:actions>
            <x-button
                :label="$student->is_active ? 'Désactiver' : 'Activer'"
                :icon="$student->is_active ? 'o-x-circle' : 'o-check-circle'"
                wire:click="toggleActive"
                :class="$student->is_active ? 'btn-warning' : 'btn-success'"
                spinner
            />
            <x-button
                label="Modifier"
                icon="o-pencil"
                :link="route('admin.students.edit', $student->uuid)"
                class="btn-primary"
                wire:navigate
            />
        </x-slot:actions>
    </x-header>

    {{-- Hero Card --}}
    <div class="bg-base-100 rounded-2xl shadow-lg overflow-hidden mb-6">
        <div class="h-24 bg-gradient-to-r from-primary to-secondary"></div>
        <div class="px-6 pb-6">
            <div class="flex items-end gap-4 -mt-10 mb-4">
                {{-- Avatar --}}
                <div class="w-20 h-20 rounded-2xl bg-base-100 border-4 border-base-100 shadow-lg overflow-hidden flex items-center justify-center text-primary font-black text-2xl">
                    @if($student->photo)
                    <img src="{{ \Illuminate\Support\Facades\Storage::url($student->photo) }}" alt="{{ $student->full_name }}" class="w-full h-full object-cover" />
                    @else
                    {{ substr($student->name, 0, 1) }}
                    @endif
                </div>
                <div class="pb-1">
                    <h1 class="text-2xl font-black">{{ $student->full_name }}</h1>
                    <div class="flex items-center gap-2 flex-wrap">
                        @if($student->student_code)
                        <span class="font-mono text-sm text-base-content/60">{{ $student->student_code }}</span>
                        @endif
                        <x-badge
                            :value="$student->is_active ? 'Actif' : 'Inactif'"
                            :class="$student->is_active ? 'badge-success' : 'badge-error'"
                        />
                        @if($enrollment)
                        <x-badge value="{{ $enrollment->schoolClass->name ?? '—' }}" class="badge-info badge-outline" />
                        <x-badge value="{{ $enrollment->status->label() }}" class="badge-warning badge-outline" />
                        @endif
                    </div>
                </div>
            </div>

            {{-- Finance mini-summary --}}
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                @foreach([
                    ['label' => 'Total facturé', 'val' => number_format($financeSummary['total']).' DJF', 'class' => 'text-purple-600'],
                    ['label' => 'Payé',           'val' => number_format($financeSummary['paid']).' DJF',  'class' => 'text-green-600'],
                    ['label' => 'Solde',          'val' => number_format($financeSummary['balance']).' DJF','class' => 'text-amber-600'],
                    ['label' => 'En retard',      'val' => $financeSummary['overdue'].' facture(s)',        'class' => 'text-red-600'],
                ] as $s)
                <div class="p-3 bg-base-200 rounded-xl">
                    <p class="text-xs text-base-content/60">{{ $s['label'] }}</p>
                    <p class="font-black {{ $s['class'] }}">{{ $s['val'] }}</p>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Tabs --}}
    <x-tabs wire:model="activeTab">

        <x-tab name="info" label="Informations" icon="o-user">
            <div class="grid grid-cols-1 gap-6 mt-4 lg:grid-cols-2">

                {{-- Personal Info --}}
                <x-card title="Informations personnelles" separator>
                    <div class="space-y-3">
                        @foreach([
                            ['label' => 'Nom complet',  'val' => $student->name],
                            ['label' => 'Genre',        'val' => $student->gender?->label()],
                            ['label' => 'Date naissance','val' => $student->date_of_birth?->format('d/m/Y')],
                            ['label' => 'Lieu naissance','val' => $student->place_of_birth],
                            ['label' => 'Nationalité',  'val' => $student->nationality],
                            ['label' => 'N° identité',  'val' => $student->national_id],
                            ['label' => 'Groupe sanguin','val' => $student->blood_type],
                            ['label' => 'Adresse',      'val' => $student->address],
                        ] as $row)
                        @if($row['val'])
                        <div class="flex items-center justify-between py-1.5 border-b border-base-200 last:border-0">
                            <span class="text-sm text-base-content/60">{{ $row['label'] }}</span>
                            <span class="font-medium text-sm">{{ $row['val'] }}</span>
                        </div>
                        @endif
                        @endforeach

                        @if($student->has_disability)
                        <div class="p-3 bg-warning/10 rounded-lg text-sm">
                            <p class="font-semibold text-warning">Situation de handicap</p>
                            @if($student->disability_notes)
                            <p class="text-base-content/70 mt-1">{{ $student->disability_notes }}</p>
                            @endif
                        </div>
                        @endif
                    </div>
                </x-card>

                {{-- Guardians --}}
                <x-card title="Responsables légaux" separator>
                    @forelse($student->guardians as $guardian)
                    <div class="flex items-start gap-3 py-3 border-b border-base-200 last:border-0">
                        <div class="w-10 h-10 rounded-full bg-secondary flex items-center justify-center text-secondary-content font-bold shrink-0">
                            {{ substr($guardian->name, 0, 1) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <p class="font-semibold text-sm">{{ $guardian->full_name }}</p>
                                @if($guardian->pivot->is_primary)
                                <x-badge value="Principal" class="badge-primary badge-xs" />
                                @endif
                                @if($guardian->user_id)
                                <x-badge value="Compte actif" class="badge-success badge-xs" />
                                @else
                                <x-badge value="Sans compte" class="badge-warning badge-xs" />
                                @endif
                            </div>
                            <p class="text-xs text-base-content/60 mt-0.5">{{ $guardian->pivot->relation }}</p>
                            @if($guardian->email)
                            <p class="text-xs text-base-content/60">{{ $guardian->email }}</p>
                            @endif
                            @if($guardian->phone)
                            <p class="text-xs text-base-content/60">{{ $guardian->phone }}</p>
                            @endif
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            @if($guardian->pivot->receive_notifications)
                            <x-icon name="o-bell" class="w-4 h-4 text-success" title="Reçoit les notifications" />
                            @endif
                            @if($guardian->email && auth()->user()->hasRole(['super-admin', 'admin']))
                            <x-button
                                wire:click="sendGuardianCredentials({{ $guardian->id }})"
                                wire:loading.attr="disabled"
                                wire:target="sendGuardianCredentials({{ $guardian->id }})"
                                :tooltip="$guardian->user_id ? 'Réinitialiser et renvoyer les identifiants' : 'Créer le compte et envoyer les identifiants'"
                                :icon="$guardian->user_id ? 'o-arrow-path' : 'o-paper-airplane'"
                                class="{{ $guardian->user_id ? 'btn-ghost btn-xs text-info' : 'btn-warning btn-xs' }}"
                                spinner
                            />
                            @endif
                        </div>
                    </div>
                    @empty
                    <x-alert icon="o-information-circle" class="alert-info text-sm">Aucun responsable enregistré.</x-alert>
                    @endforelse
                </x-card>
            </div>
        </x-tab>

        <x-tab name="enrollments" label="Inscriptions" icon="o-clipboard-document-check">
            <div class="mt-4 space-y-3">
                @forelse($allEnrollments as $enr)
                <div class="flex items-center justify-between p-4 bg-base-100 rounded-xl border border-base-200">
                    <div>
                        <p class="font-semibold">{{ $enr->academicYear->name }}</p>
                        <p class="text-sm text-base-content/60">{{ $enr->schoolClass?->name ?? '—' }}</p>
                        <p class="text-xs text-base-content/40">{{ $enr->reference }}</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <x-badge
                            :value="$enr->status->label()"
                            :class="match($enr->status->value) {
                                'confirmed' => 'badge-success',
                                'hold'      => 'badge-warning',
                                'cancelled' => 'badge-error',
                                default     => 'badge-ghost'
                            }"
                        />
                        <a href="{{ route('admin.enrollments.show', $enr->uuid) }}" wire:navigate
                           class="btn btn-ghost btn-xs">
                            <x-icon name="o-eye" class="w-3.5 h-3.5"/>
                        </a>
                    </div>
                </div>
                @empty
                <x-alert icon="o-information-circle" class="alert-info">Aucune inscription.</x-alert>
                @endforelse
            </div>
        </x-tab>

        <x-tab name="documents" label="Documents" icon="o-paper-clip">
            <x-card class="mt-4">
                <livewire:admin.components.attachments
                    model-type="student"
                    :model-id="$student->id"
                    :key="'student-attachments-'.$student->id" />
            </x-card>
        </x-tab>

        <x-tab name="finance" label="Finance" icon="o-banknotes">
            <div class="mt-4 space-y-4">
                <h3 class="font-bold text-lg">Factures récentes</h3>
                @forelse($invoices as $invoice)
                <div class="flex items-center justify-between p-4 bg-base-100 rounded-xl border border-base-200">
                    <div>
                        <p class="font-mono font-bold text-sm">{{ $invoice->reference }}</p>
                        <p class="text-xs text-base-content/60">{{ $invoice->invoice_type->label() }} — Échéance: {{ $invoice->due_date?->format('d/m/Y') }}</p>
                    </div>
                    <div class="text-right">
                        <p class="font-bold">{{ number_format($invoice->total) }} DJF</p>
                        <p class="text-xs {{ $invoice->balance_due > 0 ? 'text-orange-600' : 'text-green-600' }}">
                            Solde: {{ number_format($invoice->balance_due) }} DJF
                        </p>
                    </div>
                    <a href="{{ route('admin.finance.invoices.show', $invoice->uuid) }}" wire:navigate
                       class="btn btn-ghost btn-xs ml-2">
                        <x-icon name="o-eye" class="w-3.5 h-3.5"/>
                    </a>
                </div>
                @empty
                <x-alert icon="o-information-circle" class="alert-info">Aucune facture.</x-alert>
                @endforelse
            </div>
        </x-tab>

    </x-tabs>
</div>
