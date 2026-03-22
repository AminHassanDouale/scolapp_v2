<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TimetableEntry;
use App\Models\TimetableTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TimetableSeeder extends Seeder
{
    /**
     * Weekend = Friday (5) + Saturday (6).
     * School week: Sunday (0) → Thursday (4).
     *
     * day_of_week: 0 = Dimanche, 1 = Lundi, 2 = Mardi, 3 = Mercredi, 4 = Jeudi
     *
     * Session duration: 1h30 (terrestrial schedule).
     * Slots per day:
     *   07:30–09:00   S1
     *   09:00–10:30   S2
     *   10:45–12:15   S3  (15-min break after S2)
     *   13:30–15:00   S4  (lunch break 12:15–13:30)
     *   15:00–16:30   S5
     */
    private array $slots = [
        ['start' => '07:30', 'end' => '09:00'],
        ['start' => '09:00', 'end' => '10:30'],
        ['start' => '10:45', 'end' => '12:15'],
        ['start' => '13:30', 'end' => '15:00'],
        ['start' => '15:00', 'end' => '16:30'],
    ];

    // Days of the school week (Sun–Thu)
    private array $days = [0, 1, 2, 3, 4];

    public function run(): void
    {
        $school = School::where('slug', 'ecole-demo')->first();
        if (! $school) {
            $this->command->warn('School not found. Skipping TimetableSeeder.');
            return;
        }

        $year = AcademicYear::where('school_id', $school->id)->where('is_current', true)->first();
        if (! $year) {
            $this->command->warn('No current academic year. Skipping TimetableSeeder.');
            return;
        }

        $classes  = SchoolClass::where('school_id', $school->id)
            ->where('academic_year_id', $year->id)
            ->with(['grade'])
            ->get();

        $subjects = Subject::where('school_id', $school->id)->get()->keyBy('code');
        $teachers = Teacher::where('school_id', $school->id)
            ->where('is_active', true)
            ->with('subjects')
            ->get();

        // Map: subject_code -> teacher
        $subjectTeacherMap = [];
        foreach ($teachers as $teacher) {
            foreach ($teacher->subjects as $subj) {
                $subjectTeacherMap[$subj->code] = $teacher;
            }
        }

        // Subject schedule per grade cycle
        // Each slot is [day(0-4), slot_index(0-4), subject_code]
        $schedules = $this->buildSchedules($subjects->keys()->toArray());

        $created = 0;

        foreach ($classes as $class) {
            $gradeCode = $class->grade?->code ?? 'CP';

            // Pick schedule based on cycle
            $schedule = $this->resolveSchedule($gradeCode, $schedules);

            // Create template
            $template = TimetableTemplate::firstOrCreate(
                [
                    'school_id'        => $school->id,
                    'school_class_id'  => $class->id,
                    'academic_year_id' => $year->id,
                    'name'             => 'Emploi du temps — ' . $class->name,
                ],
                [
                    'uuid'      => (string) Str::uuid(),
                    'is_active' => true,
                ]
            );

            // Wipe existing entries to re-seed cleanly
            $template->entries()->delete();

            foreach ($schedule as [$day, $slotIdx, $subjectCode]) {
                $subject = $subjects->get($subjectCode);
                if (! $subject) continue;

                $slot    = $this->slots[$slotIdx] ?? null;
                if (! $slot) continue;

                $teacher = $subjectTeacherMap[$subjectCode] ?? null;

                TimetableEntry::create([
                    'timetable_template_id' => $template->id,
                    'day_of_week'           => $day,
                    'start_time'            => $slot['start'] . ':00',
                    'end_time'              => $slot['end']   . ':00',
                    'subject_id'            => $subject->id,
                    'teacher_id'            => $teacher?->id,
                    'room'                  => 'Salle ' . $class->room ?? ('Salle ' . rand(1, 12)),
                ]);
            }

            $created++;
            $this->command->info("  ✅ {$class->name} — {$template->entries()->count()} créneaux");
        }

        $this->command->info("🎓 TimetableSeeder: {$created} emplois du temps créés.");
    }

    /**
     * Build a realistic weekly schedule per cycle.
     * Format: [day(0-4), slotIndex(0-4), subjectCode]
     * 5 days × 5 slots = 25 possible slots.
     */
    private function buildSchedules(array $availableCodes): array
    {
        // Primary schedule (CP–CM2)
        $primary = [
            // Dimanche (0)
            [0, 0, 'MATH'], [0, 1, 'FRA'],  [0, 2, 'ARB'],  [0, 3, 'EI'],   [0, 4, 'EPS'],
            // Lundi (1)
            [1, 0, 'FRA'],  [1, 1, 'MATH'], [1, 2, 'ANG'],  [1, 3, 'HG'],   [1, 4, 'ART'],
            // Mardi (2)
            [2, 0, 'MATH'], [2, 1, 'ARB'],  [2, 2, 'FRA'],  [2, 3, 'SVT'],  [2, 4, 'INFO'],
            // Mercredi (3)
            [3, 0, 'FRA'],  [3, 1, 'MATH'], [3, 2, 'EI'],   [3, 3, 'ANG'],  [3, 4, 'HG'],
            // Jeudi (4)
            [4, 0, 'ARB'],  [4, 1, 'FRA'],  [4, 2, 'MATH'], [4, 3, 'EPS'],  [4, 4, 'ART'],
        ];

        // Collège schedule (6ème–3ème)
        $college = [
            // Dimanche (0)
            [0, 0, 'MATH'], [0, 1, 'FRA'],  [0, 2, 'PC'],   [0, 3, 'ANG'],  [0, 4, 'EPS'],
            // Lundi (1)
            [1, 0, 'ARB'],  [1, 1, 'MATH'], [1, 2, 'SVT'],  [1, 3, 'HG'],   [1, 4, 'INFO'],
            // Mardi (2)
            [2, 0, 'FRA'],  [2, 1, 'PC'],   [2, 2, 'MATH'], [2, 3, 'EI'],   [2, 4, 'ANG'],
            // Mercredi (3)
            [3, 0, 'MATH'], [3, 1, 'ARB'],  [3, 2, 'HG'],   [3, 3, 'SVT'],  [3, 4, 'EPS'],
            // Jeudi (4)
            [4, 0, 'FRA'],  [4, 1, 'ANG'],  [4, 2, 'ARB'],  [4, 3, 'PC'],   [4, 4, 'INFO'],
        ];

        // Lycée schedule (2nde–Tle)
        $lycee = [
            // Dimanche (0)
            [0, 0, 'MATH'], [0, 1, 'FRA'],  [0, 2, 'PC'],   [0, 3, 'SVT'],  [0, 4, 'ANG'],
            // Lundi (1)
            [1, 0, 'ARB'],  [1, 1, 'MATH'], [1, 2, 'HG'],   [1, 3, 'PC'],   [1, 4, 'INFO'],
            // Mardi (2)
            [2, 0, 'MATH'], [2, 1, 'FRA'],  [2, 2, 'SVT'],  [2, 3, 'EPS'],  [2, 4, 'ANG'],
            // Mercredi (3)
            [3, 0, 'PC'],   [3, 1, 'MATH'], [3, 2, 'ARB'],  [3, 3, 'FRA'],  [3, 4, 'HG'],
            // Jeudi (4)
            [4, 0, 'ANG'],  [4, 1, 'SVT'],  [4, 2, 'MATH'], [4, 3, 'ARB'],  [4, 4, 'PC'],
        ];

        return [
            'primary' => $primary,
            'college' => $college,
            'lycee'   => $lycee,
        ];
    }

    private function resolveSchedule(string $gradeCode, array $schedules): array
    {
        $primaryGrades = ['PS', 'MS', 'GS', 'CP', 'CE1', 'CE2', 'CM1', 'CM2'];
        $collegeGrades = ['6E', '5E', '4E', '3E'];

        if (in_array($gradeCode, $primaryGrades)) {
            return $schedules['primary'];
        }
        if (in_array($gradeCode, $collegeGrades)) {
            return $schedules['college'];
        }
        return $schedules['lycee'];
    }
}
