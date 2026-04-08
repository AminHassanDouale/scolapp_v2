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

    private function sendInvoiceEmails(Enrollment $enrollment, array $tuitionInvoices): void
    {
        // Get guardians who should receive notifications, with fallback to primary guardian
        $guardians = $enrollment->student->guardians()
            ->with('user')
            ->wherePivot('receive_notifications', true)
            ->get();

        if ($guardians->isEmpty()) {
            $guardians = $enrollment->student->guardians()
                ->with('user')
                ->wherePivot('is_primary', true)
                ->get();
        }

        $guardians = $guardians->filter(
            fn($g) => filled($g->email) || filled($g->user?->email)
        );

        if ($guardians->isEmpty()) {
            return;
        }

        // Collect all invoices: registration invoice + tuition installments
        $registrationInvoice = $enrollment->invoices()
            ->where('invoice_type', InvoiceType::REGISTRATION->value)
            ->latest()
            ->first();

        $invoices = collect($tuitionInvoices);
        if ($registrationInvoice) {
            $invoices->prepend($registrationInvoice);
        }

        foreach ($guardians as $guardian) {
            foreach ($invoices as $invoice) {
                try {
                    Mail::queue(new InvoiceGeneratedMail($invoice, $guardian));
                } catch (\Throwable $e) {
                    Log::warning('Failed to queue invoice email', [
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
