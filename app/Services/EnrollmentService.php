<?php

namespace App\Services;

use App\Actions\ConfirmEnrollmentAction;
use App\Actions\CreateEnrollmentAction;
use App\Enums\EnrollmentStatus;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\FeeSchedule;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

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
        return DB::transaction(function () use ($enrollment) {
            $enrollment = ($this->confirmAction)($enrollment);

            // Generate tuition installments
            $this->invoiceService->generateTuitionInstallments($enrollment);

            return $enrollment;
        });
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
