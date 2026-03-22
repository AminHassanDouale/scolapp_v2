<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\ReportCard;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Enums\ReportPeriod;
use Illuminate\Support\Facades\DB;

class ReportCardService
{
    /**
     * Generate (or recalculate) report cards for every enrolled student in a class/period.
     * Returns the number of report cards generated.
     */
    public function generateForClass(SchoolClass $class, ReportPeriod $period, AcademicYear $year): int
    {
        $enrollments = Enrollment::where('school_class_id', $class->id)
            ->where('academic_year_id', $year->id)
            ->where('status', 'active')
            ->with('student')
            ->get();

        foreach ($enrollments as $enrollment) {
            $this->generate($enrollment, $period->value);
        }

        // Compute class-wide rankings after all cards are generated
        $this->computeClassRankings($class, $period->value);

        return $enrollments->count();
    }

    /**
     * Generate (or recalculate) a single student's report card.
     */
    public function generate(Enrollment $enrollment, string $period): ReportCard
    {
        return DB::transaction(function () use ($enrollment, $period) {
            $class   = $enrollment->schoolClass;
            $student = $enrollment->student;

            /** @var ReportCard $card */
            $card = ReportCard::updateOrCreate(
                [
                    'enrollment_id' => $enrollment->id,
                    'period'        => $period,
                ],
                [
                    'school_id'        => $enrollment->school_id,
                    'student_id'       => $student->id,
                    'academic_year_id' => $enrollment->academic_year_id,
                    'is_published'     => false,
                ]
            );

            // Subjects to grade — fall back to all school subjects
            $gradeSubjects = $class->grade?->subjects ?? collect();
            $subjects = $gradeSubjects->isNotEmpty()
                ? $gradeSubjects
                : Subject::where('school_id', $enrollment->school_id)->get();

            $weightedSum = 0;
            $totalCoeff  = 0;

            foreach ($subjects as $subject) {
                $scores = $student->studentScores()
                    ->whereHas('assessment', fn($q) =>
                        $q->where('subject_id', $subject->id)
                          ->where('period', $period)
                          ->where('school_class_id', $class->id)
                          ->where('is_published', true)
                    )
                    ->whereNotNull('score')
                    ->with('assessment')
                    ->get();

                if ($scores->isEmpty()) {
                    continue;
                }

                $totalCoeffAssmt = $scores->sum(fn($s) => $s->assessment->coefficient);
                $weightedScore   = $scores->sum(
                    fn($s) => ($s->score / max($s->assessment->max_score, 1)) * 20 * $s->assessment->coefficient
                );

                $subjectAvg  = $totalCoeffAssmt > 0 ? $weightedScore / $totalCoeffAssmt : null;
                $coefficient = $subject->default_coefficient ?? 1;
                $weightedAvg = $subjectAvg !== null ? $subjectAvg * $coefficient : null;

                $weightedSum += $weightedAvg ?? 0;
                $totalCoeff  += $coefficient;

                $card->reportCardSubjects()->updateOrCreate(
                    ['subject_id' => $subject->id],
                    [
                        'average'      => $subjectAvg,
                        'coefficient'  => $coefficient,
                        'weighted_avg' => $weightedAvg,
                    ]
                );
            }

            $generalAverage = $totalCoeff > 0 ? round($weightedSum / $totalCoeff, 2) : null;
            $card->update(['average' => $generalAverage]);

            return $card->fresh();
        });
    }

    public function publish(ReportCard $card): ReportCard
    {
        $card->update([
            'is_published' => true,
            'published_at' => now(),
        ]);

        return $card;
    }

    private function computeClassRankings(SchoolClass $class, string $period): void
    {
        $cards = ReportCard::whereHas('enrollment', fn($q) =>
                $q->where('school_class_id', $class->id)
            )
            ->where('period', $period)
            ->whereNotNull('average')
            ->orderByDesc('average')
            ->get();

        $total    = $cards->count();
        $classAvg = $total > 0 ? round($cards->avg('average'), 2) : null;

        foreach ($cards as $rank => $card) {
            $card->update([
                'rank'          => $rank + 1,
                'class_size'    => $total,
                'class_average' => $classAvg,
            ]);
        }

        // Update class_avg on each report_card_subject for all cards in this class/period
        foreach ($cards as $card) {
            foreach ($card->reportCardSubjects as $sg) {
                $classSubjectAvg = $cards
                    ->flatMap(fn($c) => $c->reportCardSubjects->where('subject_id', $sg->subject_id))
                    ->avg('average');

                $sg->update(['class_avg' => $classSubjectAvg ? round($classSubjectAvg, 2) : null]);
            }
        }
    }
}
