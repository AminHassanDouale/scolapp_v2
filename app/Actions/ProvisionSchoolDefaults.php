<?php

namespace App\Actions;

use App\Models\AcademicCycle;
use App\Models\AcademicYear;
use App\Models\FeeItem;
use App\Models\FeeSchedule;
use App\Models\Grade;
use App\Models\Room;
use App\Models\School;
use App\Models\SchoolClass;
use Illuminate\Support\Str;

/**
 * Provisions the default academic structure for a newly created school so the
 * platform admin never has to enter it by hand:
 *   - current academic year
 *   - academic cycles + grades (Maternelle → Lycée)
 *   - one class per grade for the current year
 *   - two rooms per class ("<grade> A" and "<grade> B"), each toggleable
 *     via is_active for selection.
 */
class ProvisionSchoolDefaults
{
    /** Standard French class/room size. */
    private const DEFAULT_CAPACITY = 30;

    /** Default cycles & their grades. */
    private array $cycles = [
        ['name' => 'Maternelle', 'code' => 'MAT', 'order' => 1, 'grades' => [
            ['name' => 'PS', 'code' => 'PS', 'order' => 1],
            ['name' => 'MS', 'code' => 'MS', 'order' => 2],
            ['name' => 'GS', 'code' => 'GS', 'order' => 3],
        ]],
        ['name' => 'Primaire', 'code' => 'PRI', 'order' => 2, 'grades' => [
            ['name' => 'CP',  'code' => 'CP',  'order' => 1],
            ['name' => 'CE1', 'code' => 'CE1', 'order' => 2],
            ['name' => 'CE2', 'code' => 'CE2', 'order' => 3],
            ['name' => 'CM1', 'code' => 'CM1', 'order' => 4],
            ['name' => 'CM2', 'code' => 'CM2', 'order' => 5],
        ]],
        ['name' => 'Collège', 'code' => 'COL', 'order' => 3, 'grades' => [
            ['name' => '6ème', 'code' => '6E', 'order' => 1],
            ['name' => '5ème', 'code' => '5E', 'order' => 2],
            ['name' => '4ème', 'code' => '4E', 'order' => 3],
            ['name' => '3ème', 'code' => '3E', 'order' => 4],
        ]],
        ['name' => 'Lycée', 'code' => 'LYC', 'order' => 4, 'grades' => [
            ['name' => '2nde', 'code' => '2D',  'order' => 1],
            ['name' => '1ère', 'code' => '1E',  'order' => 2],
            ['name' => 'Tle',  'code' => 'TLE', 'order' => 3],
        ]],
    ];

    /** Standard fee items (barème lines). */
    private array $feeItemDefs = [
        ['name' => 'Frais de scolarité',      'code' => 'SCOL',  'type' => 'tuition'],
        ['name' => "Frais d'inscription",     'code' => 'INSCR', 'type' => 'registration'],
        ['name' => 'Transport scolaire',      'code' => 'TRANS', 'type' => 'transport'],
        ['name' => 'Cantine',                 'code' => 'CANT',  'type' => 'other'],
        ['name' => 'Fournitures scolaires',   'code' => 'FOUR',  'type' => 'other'],
        ['name' => 'Activités parascolaires', 'code' => 'PARA',  'type' => 'other'],
        ['name' => 'Assurance scolaire',      'code' => 'ASSU',  'type' => 'other'],
    ];

    /** Default annual barème amounts (DJF) per grade code. */
    private array $barems = [
        'PS'  => ['SCOL' => 40000, 'INSCR' => 8000,  'CANT' => 10000],
        'MS'  => ['SCOL' => 40000, 'INSCR' => 8000,  'CANT' => 10000],
        'GS'  => ['SCOL' => 40000, 'INSCR' => 8000,  'CANT' => 10000],
        'CP'  => ['SCOL' => 45000, 'INSCR' => 8000,  'CANT' => 10000],
        'CE1' => ['SCOL' => 45000, 'INSCR' => 8000,  'CANT' => 10000],
        'CE2' => ['SCOL' => 50000, 'INSCR' => 8000,  'CANT' => 10000],
        'CM1' => ['SCOL' => 50000, 'INSCR' => 8000,  'CANT' => 10000],
        'CM2' => ['SCOL' => 50000, 'INSCR' => 8000,  'CANT' => 10000],
        '6E'  => ['SCOL' => 70000, 'INSCR' => 10000, 'TRANS' => 8000, 'CANT' => 12000],
        '5E'  => ['SCOL' => 70000, 'INSCR' => 10000, 'TRANS' => 8000, 'CANT' => 12000],
        '4E'  => ['SCOL' => 70000, 'INSCR' => 10000, 'TRANS' => 8000, 'CANT' => 12000],
        '3E'  => ['SCOL' => 72000, 'INSCR' => 10000, 'TRANS' => 8000, 'CANT' => 12000],
        '2D'  => ['SCOL' => 90000, 'INSCR' => 12000, 'TRANS' => 8000, 'CANT' => 12000, 'ASSU' => 3000],
        '1E'  => ['SCOL' => 90000, 'INSCR' => 12000, 'TRANS' => 8000, 'CANT' => 12000, 'ASSU' => 3000],
        'TLE' => ['SCOL' => 95000, 'INSCR' => 12000, 'TRANS' => 8000, 'CANT' => 12000, 'ASSU' => 3000],
    ];

    public function execute(School $school): AcademicYear
    {
        // ── Academic year (school year straddles Sept → June) ──────────────
        $startYear = now()->month >= 7 ? now()->year : now()->year - 1;

        $year = AcademicYear::create([
            'uuid'       => (string) Str::uuid(),
            'school_id'  => $school->id,
            'name'       => $startYear . '-' . ($startYear + 1),
            'start_date' => $startYear . '-09-01',
            'end_date'   => ($startYear + 1) . '-06-30',
            'is_current' => true,
            'is_active'  => true,
        ]);

        $gradesByCode = [];

        foreach ($this->cycles as $cycleData) {
            $grades = $cycleData['grades'];

            $cycle = AcademicCycle::create([
                'uuid'      => (string) Str::uuid(),
                'school_id' => $school->id,
                'name'      => $cycleData['name'],
                'code'      => $cycleData['code'],
                'order'     => $cycleData['order'],
                'is_active' => true,
            ]);

            foreach ($grades as $g) {
                $grade = Grade::create([
                    'uuid'              => (string) Str::uuid(),
                    'school_id'         => $school->id,
                    'academic_cycle_id' => $cycle->id,
                    'name'              => $g['name'],
                    'code'              => $g['code'],
                    'order'             => $g['order'],
                    'is_active'         => true,
                ]);
                $gradesByCode[$g['code']] = $grade;

                // Two rooms per class: A and B (toggle is_active for selection)
                $roomA = Room::create([
                    'uuid'      => (string) Str::uuid(),
                    'school_id' => $school->id,
                    'name'      => $g['name'] . ' A',
                    'code'      => $g['code'] . '-A',
                    'type'      => 'classroom',
                    'capacity'  => self::DEFAULT_CAPACITY,
                    'is_active' => true,
                ]);
                Room::create([
                    'uuid'      => (string) Str::uuid(),
                    'school_id' => $school->id,
                    'name'      => $g['name'] . ' B',
                    'code'      => $g['code'] . '-B',
                    'type'      => 'classroom',
                    'capacity'  => self::DEFAULT_CAPACITY,
                    'is_active' => true,
                ]);

                // One class per grade for the current year, default room = A
                SchoolClass::create([
                    'uuid'             => (string) Str::uuid(),
                    'school_id'        => $school->id,
                    'grade_id'         => $grade->id,
                    'academic_year_id' => $year->id,
                    'name'             => $g['name'],
                    'room'             => $roomA->name,
                    'capacity'         => self::DEFAULT_CAPACITY,
                    'is_active'        => true,
                ]);
            }
        }

        // Default barèmes (fee schedules) per grade
        $this->provisionFees($school, $year, $gradesByCode);

        return $year;
    }

    /**
     * Create the standard fee items and one default annual barème per grade.
     *
     * @param  array<string, Grade> $gradesByCode
     */
    private function provisionFees(School $school, AcademicYear $year, array $gradesByCode): void
    {
        // Fee items (idempotent per school)
        $items = [];
        foreach ($this->feeItemDefs as $def) {
            $items[$def['code']] = FeeItem::firstOrCreate(
                ['school_id' => $school->id, 'code' => $def['code']],
                [
                    'uuid'      => (string) Str::uuid(),
                    'school_id' => $school->id,
                    'name'      => $def['name'],
                    'type'      => $def['type'],
                    'is_active' => true,
                ]
            );
        }

        foreach ($this->barems as $code => $amounts) {
            $grade = $gradesByCode[$code] ?? null;
            if (! $grade) {
                continue;
            }

            $schedule = FeeSchedule::create([
                'uuid'             => (string) Str::uuid(),
                'school_id'        => $school->id,
                'academic_year_id' => $year->id,
                'grade_id'         => $grade->id,
                'name'             => 'Barème ' . $grade->name,
                'schedule_type'    => 'yearly',
                'is_default'       => true,
                'is_active'        => true,
            ]);

            $sync = [];
            foreach ($amounts as $itemCode => $amount) {
                if (isset($items[$itemCode])) {
                    $sync[$items[$itemCode]->id] = ['amount' => $amount];
                }
            }
            $schedule->feeItems()->sync($sync);
        }
    }
}
