<?php

namespace Database\Seeders;

use App\Models\School;
use App\Models\Subject;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SubjectSeeder extends Seeder
{
    public function run(): void
    {
        $school = School::where('slug', 'ecole-demo')->first();

        $subjects = [
            ['name' => 'Mathématiques',          'code' => 'MATH', 'color' => '#6366f1', 'default_coefficient' => 3],
            ['name' => 'Français',               'code' => 'FRA',  'color' => '#3b82f6', 'default_coefficient' => 3],
            ['name' => 'Sciences Naturelles',    'code' => 'SVT',  'color' => '#22c55e', 'default_coefficient' => 2],
            ['name' => 'Physique-Chimie',        'code' => 'PC',   'color' => '#a855f7', 'default_coefficient' => 2],
            ['name' => 'Histoire-Géographie',    'code' => 'HG',   'color' => '#f59e0b', 'default_coefficient' => 2],
            ['name' => 'Anglais',                'code' => 'ANG',  'color' => '#14b8a6', 'default_coefficient' => 2],
            ['name' => 'Arabe',                  'code' => 'ARB',  'color' => '#ef4444', 'default_coefficient' => 2],
            ['name' => 'Éducation Physique',     'code' => 'EPS',  'color' => '#f97316', 'default_coefficient' => 1],
            ['name' => 'Arts Plastiques',        'code' => 'ART',  'color' => '#ec4899', 'default_coefficient' => 1],
            ['name' => 'Informatique',           'code' => 'INFO', 'color' => '#64748b', 'default_coefficient' => 1],
            ['name' => 'Éducation Islamique',    'code' => 'EI',   'color' => '#0ea5e9', 'default_coefficient' => 1],
            ['name' => 'Géographie',             'code' => 'GEO',  'color' => '#84cc16', 'default_coefficient' => 1],
        ];

        foreach ($subjects as $data) {
            Subject::firstOrCreate(
                ['school_id' => $school->id, 'code' => $data['code']],
                array_merge($data, [
                    'uuid'      => (string) Str::uuid(),
                    'school_id' => $school->id,
                    'is_active' => true,
                ])
            );
        }
    }
}
