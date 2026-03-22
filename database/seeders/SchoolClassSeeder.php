<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Grade;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Teacher;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SchoolClassSeeder extends Seeder
{
    public function run(): void
    {
        $school  = School::where('slug', 'ecole-demo')->first();
        $year    = AcademicYear::where('school_id', $school->id)->where('is_current', true)->first();
        $teachers = Teacher::where('school_id', $school->id)->get();

        $classMap = [
            'CP'  => [['name' => 'CP A',   'capacity' => 30], ['name' => 'CP B',  'capacity' => 28]],
            'CE1' => [['name' => 'CE1 A',  'capacity' => 30]],
            'CE2' => [['name' => 'CE2 A',  'capacity' => 28]],
            'CM1' => [['name' => 'CM1 A',  'capacity' => 30]],
            'CM2' => [['name' => 'CM2 A',  'capacity' => 28]],
            '6E'  => [['name' => '6ème A', 'capacity' => 35], ['name' => '6ème B', 'capacity' => 33]],
            '5E'  => [['name' => '5ème A', 'capacity' => 35]],
            '4E'  => [['name' => '4ème A', 'capacity' => 33]],
            '3E'  => [['name' => '3ème A', 'capacity' => 32]],
            '2D'  => [['name' => '2nde A', 'capacity' => 35]],
            '1E'  => [['name' => '1ère A', 'capacity' => 30]],
            'TLE' => [['name' => 'Tle A',  'capacity' => 28]],
        ];

        $teacherIndex = 0;

        foreach ($classMap as $gradeCode => $classes) {
            $grade = Grade::where('school_id', $school->id)->where('code', $gradeCode)->first();
            if (! $grade) continue;

            foreach ($classes as $classData) {
                $teacher = $teachers->get($teacherIndex % max(1, $teachers->count()));
                $teacherIndex++;

                SchoolClass::firstOrCreate(
                    ['school_id' => $school->id, 'name' => $classData['name'], 'academic_year_id' => $year->id],
                    [
                        'uuid'             => (string) Str::uuid(),
                        'school_id'        => $school->id,
                        'grade_id'         => $grade->id,
                        'academic_year_id' => $year->id,
                        'main_teacher_id'  => $teacher?->id,
                        'capacity'         => $classData['capacity'],
                        'room'             => 'Salle ' . strtoupper(Str::random(3)),
                    ]
                );
            }
        }
    }
}
