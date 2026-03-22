<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\AttendanceEntry;
use App\Models\AttendanceSession;
use App\Models\Enrollment;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Teacher;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class AttendanceSeeder extends Seeder
{
    public function run(): void
    {
        $school  = School::where('slug', 'ecole-demo')->firstOrFail();
        $year    = AcademicYear::where('school_id', $school->id)->where('is_current', true)->firstOrFail();
        $classes = SchoolClass::where('school_id', $school->id)->where('academic_year_id', $year->id)->get();
        $teacher = Teacher::where('school_id', $school->id)->first();

        if ($classes->isEmpty()) {
            $this->command->warn('  → No classes found. Run SchoolClassSeeder first.');
            return;
        }

        $sessionCount = 0;
        $entryCount   = 0;

        // Build last 14 school days (Mon–Fri only), all strictly in the past, chronological order
        $schoolDays = [];
        $cursor     = Carbon::today()->subDay(); // start yesterday — never seed future dates
        while (count($schoolDays) < 14) {
            if (! $cursor->isWeekend()) {
                $schoolDays[] = $cursor->copy();
            }
            $cursor->subDay();
        }
        $schoolDays = array_reverse($schoolDays); // oldest → newest

        foreach ($classes->take(3) as $class) {
            $studentIds = Enrollment::where('school_class_id', $class->id)
                ->where('academic_year_id', $year->id)
                ->where('status', 'confirmed')
                ->pluck('student_id');

            if ($studentIds->isEmpty()) continue;

            foreach ($schoolDays as $day) {
                foreach (['morning', 'afternoon'] as $period) {
                    // Idempotent: skip if session already exists
                    $exists = AttendanceSession::where('school_id', $school->id)
                        ->where('school_class_id', $class->id)
                        ->where('session_date', $day->format('Y-m-d'))
                        ->where('period', $period)
                        ->exists();

                    if ($exists) continue;

                    $session = AttendanceSession::create([
                        'uuid'             => (string) Str::uuid(),
                        'school_id'        => $school->id,
                        'school_class_id'  => $class->id,
                        'teacher_id'       => $teacher?->id,
                        'academic_year_id' => $year->id,
                        'session_date'     => $day->format('Y-m-d'),
                        'period'           => $period,
                        'start_time'       => $period === 'morning' ? '07:30:00' : '13:00:00',
                        'end_time'         => $period === 'morning' ? '12:00:00' : '17:00:00',
                    ]);
                    $sessionCount++;

                    foreach ($studentIds as $studentId) {
                        $rand   = rand(1, 10);
                        $status = match (true) {
                            $rand <= 7 => 'present',
                            $rand <= 8 => 'absent',
                            $rand <= 9 => 'late',
                            default    => 'excused',
                        };

                        AttendanceEntry::create([
                            'attendance_session_id' => $session->id,
                            'student_id'            => $studentId,
                            'status'                => $status,
                            'reason'                => $status === 'excused' ? 'Motif médical' : null,
                            'notified'              => $status !== 'present',
                        ]);
                        $entryCount++;
                    }
                }
            }
        }

        $this->command->info("  → {$sessionCount} sessions + {$entryCount} attendance entries created.");
    }
}
