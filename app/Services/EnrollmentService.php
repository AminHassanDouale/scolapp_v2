<?php

namespace App\Services;

use App\Actions\ConfirmEnrollmentAction;
use App\Actions\CreateEnrollmentAction;
use App\Enums\EnrollmentStatus;
use App\Enums\InvoiceType;
use App\Mail\InvoiceGeneratedMail;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\FeeSchedule;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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

        // Send all invoices by email after the transaction commits
        $this->sendInvoiceEmails($enrollment, $tuitionInvoices);

        return $enrollment;
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

        // Get all guardians who have any email — no pivot filter
        $guardians = $student->guardians->filter(
            fn($g) => filled($g->email) || filled($g->user?->email)
        );

        Log::info('sendInvoiceEmails: guardians with email', [
            'count' => $guardians->count(),
            'emails' => $guardians->map(fn($g) => $g->email ?? $g->user?->email)->all(),
        ]);

        if ($guardians->isEmpty()) {
            Log::warning('sendInvoiceEmails: no guardian with email found', [
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
            foreach ($invoices as $invoice) {
                try {
                    Mail::queue(new InvoiceGeneratedMail($invoice, $guardian));
                    Log::info('sendInvoiceEmails: queued', [
                        'invoice_ref' => $invoice->reference,
                        'email'       => $guardian->email ?? $guardian->user?->email,
                    ]);
                } catch (\Throwable $e) {
                    Log::error('sendInvoiceEmails: failed to queue', [
                        'invoice_id'  => $invoice->id,
                        'guardian_id' => $guardian->id,
                        'error'       => $e->getMessage(),
                    ]);
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
