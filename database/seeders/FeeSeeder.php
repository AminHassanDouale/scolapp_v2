<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\FeeItem;
use App\Models\FeeSchedule;
use App\Models\Grade;
use App\Models\School;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class FeeSeeder extends Seeder
{
    public function run(): void
    {
        $school = School::where('slug', 'ecole-demo')->first();
        $year   = AcademicYear::where('school_id', $school->id)->where('is_current', true)->first();

        // ── Fee Items ─────────────────────────────────────────────────────────
        $itemDefs = [
            ['name' => 'Frais de scolarité',     'code' => 'SCOL',  'type' => 'tuition'],
            ['name' => "Frais d'inscription",    'code' => 'INSCR', 'type' => 'registration'],
            ['name' => 'Transport scolaire',     'code' => 'TRANS', 'type' => 'transport'],
            ['name' => 'Cantine',                'code' => 'CANT',  'type' => 'other'],
            ['name' => 'Fournitures scolaires',  'code' => 'FOUR',  'type' => 'other'],
            ['name' => 'Activités parascolaires','code' => 'PARA',  'type' => 'other'],
            ['name' => 'Assurance scolaire',     'code' => 'ASSU',  'type' => 'other'],
        ];

        $items = [];
        foreach ($itemDefs as $def) {
            $items[$def['code']] = FeeItem::firstOrCreate(
                ['school_id' => $school->id, 'code' => $def['code']],
                array_merge($def, [
                    'uuid'      => (string) Str::uuid(),
                    'school_id' => $school->id,
                    'is_active' => true,
                ])
            );
        }

        // ── One fee schedule per grade (annual totals) ───────────────────────
        // Each schedule stores the ANNUAL amounts for each fee item.
        // At enrollment, the student chooses a payment frequency
        // (monthly, bimonthly, quarterly, yearly) and invoices are
        // generated as: annual_amount ÷ number_of_installments.

        $gradeBarem = [
            // Primaire bas
            'CP'  => ['name' => 'Barème CP',         'amounts' => ['SCOL' => 45000, 'INSCR' => 8000, 'CANT' => 10000]],
            'CE1' => ['name' => 'Barème CE1',         'amounts' => ['SCOL' => 45000, 'INSCR' => 8000, 'CANT' => 10000]],
            // Primaire haut
            'CE2' => ['name' => 'Barème CE2',         'amounts' => ['SCOL' => 50000, 'INSCR' => 8000, 'CANT' => 10000]],
            'CM1' => ['name' => 'Barème CM1',         'amounts' => ['SCOL' => 50000, 'INSCR' => 8000, 'CANT' => 10000]],
            'CM2' => ['name' => 'Barème CM2',         'amounts' => ['SCOL' => 50000, 'INSCR' => 8000, 'CANT' => 10000]],
            // Collège
            '6E'  => ['name' => 'Barème 6ème',        'amounts' => ['SCOL' => 70000, 'INSCR' => 10000, 'TRANS' => 8000, 'CANT' => 12000]],
            '5E'  => ['name' => 'Barème 5ème',        'amounts' => ['SCOL' => 70000, 'INSCR' => 10000, 'TRANS' => 8000, 'CANT' => 12000]],
            '4E'  => ['name' => 'Barème 4ème',        'amounts' => ['SCOL' => 70000, 'INSCR' => 10000, 'TRANS' => 8000, 'CANT' => 12000]],
            '3E'  => ['name' => 'Barème 3ème',        'amounts' => ['SCOL' => 72000, 'INSCR' => 10000, 'TRANS' => 8000, 'CANT' => 12000]],
            // Lycée
            '2D'  => ['name' => 'Barème 2nde',        'amounts' => ['SCOL' => 90000, 'INSCR' => 12000, 'TRANS' => 8000, 'CANT' => 12000, 'ASSU' => 3000]],
            '1E'  => ['name' => 'Barème 1ère',        'amounts' => ['SCOL' => 90000, 'INSCR' => 12000, 'TRANS' => 8000, 'CANT' => 12000, 'ASSU' => 3000]],
            'TLE' => ['name' => 'Barème Terminale',   'amounts' => ['SCOL' => 95000, 'INSCR' => 12000, 'TRANS' => 8000, 'CANT' => 12000, 'ASSU' => 3000]],
        ];

        // Remove old multi-type schedules if they exist (keeps only one per grade)
        foreach ($gradeBarem as $gradeCode => $data) {
            $grade = Grade::where('school_id', $school->id)->where('code', $gradeCode)->first();
            if (! $grade) continue;

            // Delete any previously seeded type-specific duplicates
            FeeSchedule::where('school_id', $school->id)
                ->where('grade_id', $grade->id)
                ->whereIn('schedule_type', ['monthly', 'bimonthly', 'quarterly'])
                ->delete();

            // Upsert the single yearly barem for this grade
            $schedule = FeeSchedule::firstOrCreate(
                [
                    'school_id'        => $school->id,
                    'academic_year_id' => $year->id,
                    'grade_id'         => $grade->id,
                ],
                [
                    'uuid'             => (string) Str::uuid(),
                    'school_id'        => $school->id,
                    'academic_year_id' => $year->id,
                    'grade_id'         => $grade->id,
                    'name'             => $data['name'],
                    'schedule_type'    => 'yearly',   // annual reference amounts
                    'is_default'       => true,
                    'is_active'        => true,
                ]
            );

            // Keep name in sync
            $schedule->update(['name' => $data['name']]);

            // Sync fee items (annual totals)
            $sync = [];
            foreach ($data['amounts'] as $code => $amount) {
                if (isset($items[$code])) {
                    $sync[$items[$code]->id] = ['amount' => $amount];
                }
            }
            $schedule->feeItems()->sync($sync);
        }
    }
}
