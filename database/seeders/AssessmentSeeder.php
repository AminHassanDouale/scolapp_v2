<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\Enrollment;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentScore;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AssessmentSeeder extends Seeder
{
    public function run(): void
    {
        $school  = School::where('slug', 'ecole-demo')->first();
        $year    = AcademicYear::where('school_id', $school->id)->where('is_current', true)->first();
        $classes = SchoolClass::where('school_id', $school->id)->where('academic_year_id', $year->id)->get();
        $teachers = Teacher::where('school_id', $school->id)->get();

        $assessmentCount = 0;
        $scoreCount      = 0;

        foreach ($classes->take(4) as $class) {
            $classSubjects = Subject::where('school_id', $school->id)->inRandomOrder()->take(3)->get();
            $teacher       = $teachers->random();

            // Students enrolled in this class
            $enrolledStudentIds = Enrollment::where('school_class_id', $class->id)
                ->where('academic_year_id', $year->id)
                ->where('status', 'confirmed')
                ->pluck('student_id');

            if ($enrolledStudentIds->isEmpty()) continue;

            foreach ($classSubjects as $subject) {
                // 2 assessments per subject per class
                foreach (['Contrôle n°1', 'Devoir n°1'] as $idx => $title) {
                    $assessment = Assessment::create([
                        'uuid'            => (string) Str::uuid(),
                        'school_id'       => $school->id,
                        'school_class_id' => $class->id,
                        'subject_id'      => $subject->id,
                        'teacher_id'      => $teacher->id,
                        'academic_year_id'=> $year->id,
                        'title'           => $title . ' — ' . $subject->name,
                        'type'            => $idx === 0 ? 'quiz' : 'homework',
                        'period'          => 'trimester_1',
                        'max_score'       => 20,
                        'coefficient'     => $subject->default_coefficient ?? 1,
                        'assessment_date' => now()->subDays(rand(5, 60))->format('Y-m-d'),
                        'is_published'    => true,
                    ]);
                    $assessmentCount++;

                    // Generate scores for all enrolled students
                    foreach ($enrolledStudentIds as $studentId) {
                        $isAbsent = rand(0, 10) === 0; // 10% absent
                        StudentScore::create([
                            'assessment_id' => $assessment->id,
                            'student_id'    => $studentId,
                            'score'         => $isAbsent ? null : round(rand(6, 20) + rand(0, 9) / 10, 1),
                            'is_absent'     => $isAbsent,
                        ]);
                        $scoreCount++;
                    }
                }
            }
        }

        $this->command->info("  → {$assessmentCount} assessments + {$scoreCount} scores created.");
    }
}
