<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\FeeSchedule;
use App\Models\Grade;
use App\Models\Invoice;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class EnrollmentSeeder extends Seeder
{
    public function run(): void
    {
        $school  = School::where('slug', 'ecole-demo')->first();
        $year    = AcademicYear::where('school_id', $school->id)->where('is_current', true)->first();
        $classes = SchoolClass::where('school_id', $school->id)->where('academic_year_id', $year->id)->get();
        $students = Student::where('school_id', $school->id)->get()->shuffle();

        if ($classes->isEmpty() || $students->isEmpty()) {
            $this->command->warn('  → No classes or students found. Run TeacherSeeder and SchoolClassSeeder first.');
            return;
        }

        $studentIndex = 0;
        $enrollCount  = 0;
        $invoiceCount = 0;

        foreach ($classes as $class) {
            $grade    = Grade::find($class->grade_id);
            $schedule = FeeSchedule::where('school_id', $school->id)
                ->where('grade_id', $class->grade_id)
                ->where('academic_year_id', $year->id)
                ->first();

            $slots = min($class->capacity, $students->count() - $studentIndex);
            $slots = max(0, min($slots, 6)); // cap at 6 per class for demo

            for ($s = 0; $s < $slots; $s++) {
                $student = $students[$studentIndex++] ?? null;
                if (! $student) break;

                // Skip if already enrolled this year
                if (Enrollment::where('student_id', $student->id)->where('academic_year_id', $year->id)->exists()) {
                    continue;
                }

                $status = match(true) {
                    $s < 2  => 'hold',
                    default => 'confirmed',
                };

                $enrollment = Enrollment::create([
                    'uuid'             => (string) Str::uuid(),
                    'reference'        => 'ENR-' . strtoupper(Str::random(6)),
                    'school_id'        => $school->id,
                    'student_id'       => $student->id,
                    'academic_year_id' => $year->id,
                    'school_class_id'  => $class->id,
                    'grade_id'         => $class->grade_id,
                    'status'           => $status,
                    'enrolled_at'      => now()->subDays(rand(30, 90))->format('Y-m-d'),
                    'confirmed_at'     => $status === 'confirmed' ? now()->subDays(rand(1, 29))->format('Y-m-d') : null,
                ]);
                $enrollCount++;

                // Generate invoice for confirmed enrollments that have a fee schedule
                if ($status === 'confirmed' && $schedule) {
                    $feeItems = $schedule->feeItems;
                    $subtotal = $feeItems->sum(fn($i) => $i->pivot->amount ?? 0);

                    if ($subtotal > 0) {
                        $isPaid     = rand(0, 3) > 0; // 75% paid
                        $paidTotal  = $isPaid ? $subtotal : (rand(0, 1) ? (int)($subtotal * 0.5) : 0);
                        $balanceDue = $subtotal - $paidTotal;

                        $invStatus = match(true) {
                            $paidTotal >= $subtotal => 'paid',
                            $paidTotal > 0          => 'partially_paid',
                            now()->gt(now()->startOfYear()->addMonths(3)) => 'overdue',
                            default => 'issued',
                        };

                        Invoice::create([
                            'uuid'             => (string) Str::uuid(),
                            'reference'        => 'INV-' . strtoupper(Str::random(8)),
                            'school_id'        => $school->id,
                            'student_id'       => $student->id,
                            'enrollment_id'    => $enrollment->id,
                            'academic_year_id' => $year->id,
                            'fee_schedule_id'  => $schedule->id,
                            'invoice_type'     => 'tuition',
                            'schedule_type'    => 'yearly',
                            'status'           => $invStatus,
                            'issue_date'       => now()->startOfYear()->format('Y-m-d'),
                            'due_date'         => now()->startOfYear()->addMonths(1)->format('Y-m-d'),
                            'subtotal'         => $subtotal,
                            'vat_rate'         => 0,
                            'vat_amount'       => 0,
                            'total'            => $subtotal,
                            'paid_total'       => $paidTotal,
                            'balance_due'      => $balanceDue,
                            'penalty_amount'   => 0,
                        ]);
                        $invoiceCount++;
                    }
                }
            }

            if ($studentIndex >= $students->count()) break;
        }

        $this->command->info("  → {$enrollCount} enrollments + {$invoiceCount} invoices created.");
    }
}
