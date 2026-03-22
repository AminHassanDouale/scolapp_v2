<?php

namespace Database\Seeders;

use App\Models\School;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TeacherSeeder extends Seeder
{
    public function run(): void
    {
        $school = School::where('slug', 'ecole-demo')->firstOrFail();

        $teacherData = [
            ['name' => 'Amina Hassan',    'gender' => 'female', 'subjects' => ['MATH', 'PC'],   'email' => 'amina.hassan@ecole-demo.dj'],
            ['name' => 'Omar Abdillahi',  'gender' => 'male',   'subjects' => ['FRA', 'HG'],    'email' => 'omar.abdillahi@ecole-demo.dj'],
            ['name' => 'Fatouma Ali',     'gender' => 'female', 'subjects' => ['SVT'],           'email' => 'fatouma.ali@ecole-demo.dj'],
            ['name' => 'Ibrahim Moussa',  'gender' => 'male',   'subjects' => ['ANG'],           'email' => 'ibrahim.moussa@ecole-demo.dj'],
            ['name' => 'Hodan Guedi',     'gender' => 'female', 'subjects' => ['ARB', 'EI'],    'email' => 'hodan.guedi@ecole-demo.dj'],
            ['name' => 'Yusuf Ismail',    'gender' => 'male',   'subjects' => ['EPS'],           'email' => 'yusuf.ismail@ecole-demo.dj'],
            ['name' => 'Safia Ahmed',     'gender' => 'female', 'subjects' => ['INFO', 'MATH'], 'email' => 'safia.ahmed@ecole-demo.dj'],
            ['name' => 'Abdi Warsame',    'gender' => 'male',   'subjects' => ['HG', 'GEO'],   'email' => 'abdi.warsame@ecole-demo.dj'],
        ];

        foreach ($teacherData as $i => $data) {
            $subjects = $data['subjects'];
            unset($data['subjects']);

            // ── Portal user for teacher ────────────────────────────────────────
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'uuid'      => (string) Str::uuid(),
                    'school_id' => $school->id,
                    'name'      => $data['name'],
                    'password'  => Hash::make('password'),
                    'ui_lang'   => 'fr',
                    'timezone'  => 'Africa/Djibouti',
                ]
            );
            // syncRoles (not assignRole) prevents duplicate role entries on re-run
            $user->syncRoles(['teacher']);

            // ── Teacher profile record ─────────────────────────────────────────
            $teacher = Teacher::firstOrCreate(
                ['school_id' => $school->id, 'email' => $data['email']],
                array_merge($data, [
                    'uuid'      => (string) Str::uuid(),
                    'school_id' => $school->id,
                    'user_id'   => $user->id,
                    'phone'     => '+253 77 ' . str_pad($i + 10, 2, '0', STR_PAD_LEFT) . ' ' . rand(10, 99) . ' ' . rand(10, 99),
                    'hire_date' => now()->subYears(rand(1, 10))->format('Y-m-d'),
                    'is_active' => true,
                    'reference' => 'TCH-' . str_pad($i + 1, 4, '0', STR_PAD_LEFT),
                ])
            );

            // Ensure user_id is set even on existing records
            if (! $teacher->user_id) {
                $teacher->update(['user_id' => $user->id]);
            }

            // ── Sync subjects ──────────────────────────────────────────────────
            $subjectIds = Subject::where('school_id', $school->id)
                ->whereIn('code', $subjects)
                ->pluck('id');

            $teacher->subjects()->syncWithoutDetaching($subjectIds);
        }

        $this->command->info('  → ' . count($teacherData) . ' teachers created (with portal User accounts).');
        $this->command->line('    Login: amina.hassan@ecole-demo.dj / password');
    }
}
