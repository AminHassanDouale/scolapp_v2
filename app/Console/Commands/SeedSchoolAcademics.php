<?php

namespace App\Console\Commands;

use App\Enums\ReportPeriod;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\ReportCard;
use App\Models\ReportCardSubject;
use App\Models\Room;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\TimetableEntry;
use App\Models\TimetableTemplate;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Seed sample timetables (emplois du temps) and bulletins (report cards) for a
 * school — e.g. after provisioning a new school that has classes but no
 * schedule or grades yet.
 *
 *   php artisan school:seed-academics ECOLEBARWAQO
 */
class SeedSchoolAcademics extends Command
{
    protected $signature = 'school:seed-academics
                            {code : School code, UUID or slug}
                            {--period=trimester_1 : Report card period}';

    protected $description = 'Seed timetables and bulletins (report cards) for a school';

    /** Standard subjects (created if missing). */
    private array $subjectDefs = [
        ['name' => 'Mathématiques',       'code' => 'MATH', 'color' => '#6366f1', 'coef' => 3],
        ['name' => 'Français',            'code' => 'FRA',  'color' => '#3b82f6', 'coef' => 3],
        ['name' => 'Sciences Naturelles', 'code' => 'SVT',  'color' => '#22c55e', 'coef' => 2],
        ['name' => 'Physique-Chimie',     'code' => 'PC',   'color' => '#a855f7', 'coef' => 2],
        ['name' => 'Histoire-Géographie', 'code' => 'HG',   'color' => '#f59e0b', 'coef' => 2],
        ['name' => 'Anglais',             'code' => 'ANG',  'color' => '#14b8a6', 'coef' => 2],
        ['name' => 'Arabe',               'code' => 'ARB',  'color' => '#ef4444', 'coef' => 2],
        ['name' => 'Éducation Physique',  'code' => 'EPS',  'color' => '#f97316', 'coef' => 1],
        ['name' => 'Arts Plastiques',     'code' => 'ART',  'color' => '#ec4899', 'coef' => 1],
        ['name' => 'Informatique',        'code' => 'INFO', 'color' => '#64748b', 'coef' => 1],
        ['name' => 'Éducation Islamique', 'code' => 'EI',   'color' => '#0ea5e9', 'coef' => 1],
    ];

    /** School week Sun–Thu; 5 slots of 1h30. */
    private array $slots = [
        ['07:30', '09:00'], ['09:00', '10:30'], ['10:45', '12:15'], ['13:30', '15:00'], ['15:00', '16:30'],
    ];

    public function handle(): int
    {
        $code   = $this->argument('code');
        $school = School::where('code', strtoupper($code))->orWhere('uuid', $code)->orWhere('slug', $code)->first();
        if (! $school) {
            $this->error("École introuvable : {$code}");
            return self::FAILURE;
        }

        $year = AcademicYear::where('school_id', $school->id)->where('is_current', true)->first()
            ?? AcademicYear::where('school_id', $school->id)->latest('start_date')->first();
        if (! $year) {
            $this->error("Aucune année scolaire pour {$school->name}. Lancez d'abord school:provision.");
            return self::FAILURE;
        }

        // ── Subjects ──────────────────────────────────────────────────────────
        $subjects = collect($this->subjectDefs)->map(fn ($d) => Subject::firstOrCreate(
            ['school_id' => $school->id, 'code' => $d['code']],
            ['uuid' => (string) Str::uuid(), 'school_id' => $school->id, 'name' => $d['name'], 'color' => $d['color'], 'default_coefficient' => $d['coef'], 'is_active' => true],
        ))->values();
        $this->info("Matières : {$subjects->count()}");

        $classes = SchoolClass::where('school_id', $school->id)
            ->where('academic_year_id', $year->id)->with('grade')->get();

        // ── Timetables ────────────────────────────────────────────────────────
        $ttCount = 0;
        foreach ($classes as $class) {
            $template = TimetableTemplate::updateOrCreate(
                ['school_id' => $school->id, 'school_class_id' => $class->id, 'academic_year_id' => $year->id],
                ['uuid' => (string) Str::uuid(), 'name' => 'Emploi du temps — ' . $class->name, 'is_active' => true],
            );
            $template->entries()->delete();

            $room = Room::where('school_id', $school->id)->where('name', $class->room)->first();
            $i = 0;
            for ($day = 0; $day <= 4; $day++) {
                foreach ($this->slots as [$startT, $endT]) {
                    $subject = $subjects[$i % $subjects->count()];
                    $i++;
                    TimetableEntry::create([
                        'timetable_template_id' => $template->id,
                        'day_of_week'           => $day,
                        'start_time'            => $startT . ':00',
                        'end_time'              => $endT . ':00',
                        'subject_id'            => $subject->id,
                        'teacher_id'            => $class->main_teacher_id,
                        'room_id'               => $room?->id,
                        'room'                  => $class->room,
                    ]);
                }
            }
            $ttCount++;
        }
        $this->info("Emplois du temps : {$ttCount}");

        // ── Bulletins ─────────────────────────────────────────────────────────
        $period    = ReportPeriod::tryFrom($this->option('period')) ?? ReportPeriod::TRIMESTER_1;
        $bulletins = 0;
        foreach ($classes as $class) {
            $enrollments = Enrollment::where('school_class_id', $class->id)
                ->where('academic_year_id', $year->id)->where('status', 'confirmed')
                ->with('student')->get();
            $classSize = $enrollments->count();
            $rank = 0;

            foreach ($enrollments as $enr) {
                $rank++;
                $rc = ReportCard::updateOrCreate(
                    ['school_id' => $school->id, 'student_id' => $enr->student_id, 'enrollment_id' => $enr->id, 'period' => $period->value],
                    ['uuid' => (string) Str::uuid(), 'academic_year_id' => $year->id, 'is_published' => true, 'published_at' => now()],
                );
                $rc->subjectGrades()->delete();

                $total = 0; $coefSum = 0;
                foreach ($subjects as $subject) {
                    $avg      = round(mt_rand(80, 180) / 10, 2);       // 8.0 – 18.0
                    $coef     = (float) ($subject->default_coefficient ?? 1);
                    $weighted = round($avg * $coef, 2);
                    ReportCardSubject::create([
                        'report_card_id' => $rc->id,
                        'subject_id'     => $subject->id,
                        'average'        => $avg,
                        'coefficient'    => $coef,
                        'weighted_avg'   => $weighted,
                        'class_avg'      => round(mt_rand(90, 150) / 10, 2),
                        'rank'           => mt_rand(1, max(1, $classSize)),
                        'comment'        => $this->mention($avg),
                    ]);
                    $total += $weighted; $coefSum += $coef;
                }

                $general = $coefSum ? round($total / $coefSum, 2) : null;
                $rc->update([
                    'average'         => $general,
                    'rank'            => $rank,
                    'class_size'      => $classSize,
                    'class_average'   => round(mt_rand(90, 140) / 10, 2),
                    'general_comment' => $this->generalComment($general),
                ]);
                $bulletins++;
            }
        }
        $this->info("Bulletins : {$bulletins} ({$period->value})");

        $this->newLine();
        $this->info("✓ {$school->name} — emplois du temps & bulletins créés.");

        return self::SUCCESS;
    }

    private function mention(float $a): string
    {
        return match (true) {
            $a >= 16 => 'Très bien',
            $a >= 14 => 'Bien',
            $a >= 12 => 'Assez bien',
            $a >= 10 => 'Passable',
            default  => 'Insuffisant',
        };
    }

    private function generalComment(?float $a): string
    {
        if ($a === null) {
            return '';
        }
        return match (true) {
            $a >= 14 => 'Très bon trimestre, continuez ainsi.',
            $a >= 10 => 'Trimestre satisfaisant, des efforts à poursuivre.',
            default  => 'Trimestre difficile, un accompagnement est recommandé.',
        };
    }
}
