<?php

namespace App\Actions;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use Illuminate\Support\Str;

class ConfirmEnrollmentAction
{
    public function __invoke(Enrollment $enrollment): Enrollment
    {
        if ($enrollment->status !== EnrollmentStatus::HOLD) {
            throw new \RuntimeException('Only enrollments in hold status can be confirmed.');
        }

        // Generate student_code on first confirmed enrollment
        $student = $enrollment->student;
        if (empty($student->student_code)) {
            $year = $enrollment->academicYear;
            $seq  = str_pad($student->id, 4, '0', STR_PAD_LEFT);
            $student->update([
                'student_code' => 'SC' . substr($year->name, -4) . $seq,
            ]);
        }

        $enrollment->update([
            'status'       => EnrollmentStatus::CONFIRMED,
            'confirmed_at' => now()->toDateString(),
        ]);

        return $enrollment->fresh();
    }
}
