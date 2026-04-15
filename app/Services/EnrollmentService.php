<?php

namespace App\Services;

use App\Actions\ConfirmEnrollmentAction;
use App\Actions\CreateEnrollmentAction;
use App\Enums\EnrollmentStatus;
use App\Enums\InvoiceType;
use App\Mail\InvoiceGeneratedMail;
use App\Notifications\InvoiceGeneratedNotification;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\FeeSchedule;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

class EnrollmentService
{
    public function __construct(
        private readonly CreateEnrollmentAction  $createAction,
        private readonly ConfirmEnrollmentAction $confirmAction,
        private readonly InvoiceService          $invoiceService,
    ) {}

    public function enroll(
        Student      $student,
        AcademicYear $year,
        Grade        $grade,
        SchoolClass  $class,
        FeeSchedule  $feeSchedule,
        array        $options = []
    ): Enrollment {
        return DB::transaction(function () use ($student, $year, $grade, $class, $feeSchedule, $options) {
            $enrollment = ($this->createAction)($student, $year, $grade, $class);

            // Attach fee plan
            $enrollment->studentFeePlan()->create([
                'school_id'       => $student->school_id,
                'fee_schedule_id' => $feeSchedule->id,
                'discount_amount' => $options['discount_amount'] ?? 0,
                'discount_pct'    => $options['discount_pct'] ?? 0,
                'discount_reason' => $options['discount_reason'] ?? null,
            ]);

            // Generate registration invoice immediately
            $this->invoiceService->generateRegistrationInvoice($enrollment);

            return $enrollment;
        });
    }

    public function confirm(Enrollment $enrollment): Enrollment
    {
        $tuitionInvoices = [];

        $enrollment = DB::transaction(function () use ($enrollment, &$tuitionInvoices) {
            $enrollment      = ($this->confirmAction)($enrollment);
            $tuitionInvoices = $this->invoiceService->generateTuitionInstallments($enrollment);
            return $enrollment;
        });

        // Send welcome WhatsApp then all invoices after the transaction commits
        $this->sendEnrollmentWelcome($enrollment);
        $this->sendInvoiceEmails($enrollment, $tuitionInvoices);

        return $enrollment;
    }

    /**
     * Send a one-time WhatsApp welcome message to every guardian with a phone number.
     */
    public function sendEnrollmentWelcome(Enrollment $enrollment): void
    {
        $whatsapp    = app(WhatsAppService::class);
        $enrollment->loadMissing('schoolClass', 'academicYear', 'school');
        $student  = $enrollment->student()->with('guardians')->firstOrFail();
        $school   = $enrollment->school ?? $student->school;

        $student->guardians
            ->filter(fn($g) => filled($g->whatsapp_number) || filled($g->phone))
            ->each(function ($guardian) use ($whatsapp, $enrollment, $student, $school) {
                $phone = $guardian->whatsapp_number ?? $guardian->phone;

                $message = implode("\n", [
                    '🎓 *Inscription confirmée — ' . ($school?->name ?? config('app.name')) . '*',
                    '',
                    'Bonjour ' . $guardian->name . ',',
                    '',
                    'L\'inscription de *' . $student->full_name . '* a été confirmée avec succès.',
                    'Classe : ' . ($enrollment->schoolClass?->name ?? '—'),
                    'Année  : ' . ($enrollment->academicYear?->name ?? '—'),
                    '',
                    'Vous recevrez les factures en pièce jointe dans les messages suivants.',
                    '',
                    '_' . ($school?->name ?? config('app.name')) . '_',
                ]);

                try {
                    $whatsapp->sendMessage($phone, $message);
                    Log::info('sendEnrollmentWelcome: sent', [
                        'guardian_id'   => $guardian->id,
                        'phone'         => $phone,
                        'enrollment_id' => $enrollment->id,
                    ]);
                } catch (\Throwable $e) {
                    Log::error('sendEnrollmentWelcome: failed', [
                        'guardian_id' => $guardian->id,
                        'error'       => $e->getMessage(),
                    ]);
                }
            });
    }

    public function sendInvoiceEmails(Enrollment $enrollment, array $tuitionInvoices): void
    {
        // Explicitly reload student with all guardians and their users
        $student = $enrollment->student()->with('guardians.user')->firstOrFail();

        Log::info('sendInvoiceEmails: student loaded', [
            'student_id'     => $student->id,
            'student_name'   => $student->full_name,
            'guardian_count' => $student->guardians->count(),
            'tuition_count'  => count($tuitionInvoices),
        ]);

        // All guardians — email sent to those with email, WhatsApp to those with phone
        $guardians = $student->guardians;

        Log::info('sendInvoiceEmails: guardians loaded', [
            'count'  => $guardians->count(),
            'emails' => $guardians->map(fn($g) => $g->email ?? $g->user?->email)->filter()->all(),
            'phones' => $guardians->map(fn($g) => $g->whatsapp_number ?? $g->phone)->filter()->all(),
        ]);

        if ($guardians->isEmpty()) {
            Log::warning('sendInvoiceEmails: no guardian found', [
                'enrollment_id' => $enrollment->id,
                'student_id'    => $student->id,
            ]);
            return;
        }

        // Collect all invoices: registration invoice + tuition installments
        $registrationInvoice = $enrollment->invoices()
            ->where('invoice_type', InvoiceType::REGISTRATION->value)
            ->latest()
            ->first();

        $invoices = collect($tuitionInvoices)->values();
        if ($registrationInvoice) {
            $invoices->prepend($registrationInvoice);
        }

        Log::info('sendInvoiceEmails: queuing emails', [
            'invoice_count'  => $invoices->count(),
            'guardian_count' => $guardians->count(),
        ]);

        foreach ($guardians as $guardian) {
            $email = $guardian->email ?? $guardian->user?->email ?? null;
            $phone = $guardian->whatsapp_number ?? $guardian->phone ?? null;

            foreach ($invoices as $invoice) {
                // ── Email ──────────────────────────────────────────────────────
                if (filled($email)) {
                    try {
                        Mail::queue(new InvoiceGeneratedMail($invoice, $guardian));
                        Log::info('sendInvoiceEmails: email queued', [
                            'invoice_ref' => $invoice->reference,
                            'email'       => $email,
                        ]);
                    } catch (\Throwable $e) {
                        Log::error('sendInvoiceEmails: email queue failed', [
                            'invoice_id'  => $invoice->id,
                            'guardian_id' => $guardian->id,
                            'error'       => $e->getMessage(),
                        ]);
                    }
                }

                // ── WhatsApp (text + PDF attachment) ───────────────────────────
                if (filled($phone)) {
                    try {
                        Notification::send($guardian, new InvoiceGeneratedNotification($invoice, $guardian));
                        Log::info('sendInvoiceEmails: WhatsApp queued', [
                            'invoice_ref' => $invoice->reference,
                            'phone'       => $phone,
                        ]);
                    } catch (\Throwable $e) {
                        Log::error('sendInvoiceEmails: WhatsApp failed', [
                            'invoice_id'  => $invoice->id,
                            'guardian_id' => $guardian->id,
                            'error'       => $e->getMessage(),
                        ]);
                    }
                }
            }
        }
    }

    public function cancel(Enrollment $enrollment, string $reason): Enrollment
    {
        if ($enrollment->status === EnrollmentStatus::CANCELLED) {
            throw new \RuntimeException('Enrollment is already cancelled.');
        }

        return DB::transaction(function () use ($enrollment, $reason) {
            $enrollment->update([
                'status'           => EnrollmentStatus::CANCELLED,
                'cancelled_at'     => now(),
                'cancelled_reason' => $reason,
            ]);

            // Cancel any pending invoices
            $enrollment->invoices()
                ->whereIn('status', ['draft', 'issued'])
                ->update(['status' => 'cancelled']);

            return $enrollment->fresh();
        });
    }

    public function getCurrentEnrollment(Student $student): ?Enrollment
    {
        $currentYear = AcademicYear::where('school_id', $student->school_id)
            ->where('is_current', true)
            ->first();

        if (! $currentYear) {
            return null;
        }

        return Enrollment::where('student_id', $student->id)
            ->where('academic_year_id', $currentYear->id)
            ->first();
    }
}
