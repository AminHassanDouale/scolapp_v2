<?php

namespace App\Actions;

use App\Enums\EnrollmentStatus;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Student;

class CreateEnrollmentAction
{
    public function __invoke(
        Student      $student,
        AcademicYear $year,
        Grade        $grade,
        SchoolClass  $class
    ): Enrollment {
        // Ensure no duplicate enrollment for this student + year
        $existing = Enrollment::where('student_id', $student->id)
            ->where('academic_year_id', $year->id)
            ->first();

        if ($existing) {
            throw new \RuntimeException(
                "Student {$student->full_name} is already enrolled for {$year->name}."
            );
        }

        return Enrollment::create([
            'school_id'        => $student->school_id,
            'student_id'       => $student->id,
            'academic_year_id' => $year->id,
            'grade_id'         => $grade->id,
            'school_class_id'  => $class->id,
            'status'           => EnrollmentStatus::HOLD,
            'enrolled_at'      => now()->toDateString(),
        ]);
    }
}
