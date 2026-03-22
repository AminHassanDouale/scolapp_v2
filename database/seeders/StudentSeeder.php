<?php

namespace Database\Seeders;

use App\Models\Guardian;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class StudentSeeder extends Seeder
{
    public function run(): void
    {
        $school = School::where('slug', 'ecole-demo')->firstOrFail();

        $maleFirstNames   = ['Mohamed', 'Omar', 'Ibrahim', 'Abdi', 'Hassan', 'Yusuf', 'Ismail', 'Aden', 'Guled', 'Kadar', 'Ali', 'Houssein', 'Farah', 'Said', 'Mahad'];
        $femaleFirstNames = ['Amina', 'Fatouma', 'Hodan', 'Safia', 'Nimo', 'Asha', 'Zahra', 'Faadumo', 'Ifrah', 'Filsan', 'Asad', 'Deqa', 'Khadija', 'Rahmo', 'Hawa'];
        $lastNames        = ['Hassan', 'Ali', 'Ahmed', 'Abdillahi', 'Omar', 'Moussa', 'Guedi', 'Ismail', 'Warsame', 'Aden', 'Farah', 'Ibrahim', 'Said', 'Mahamoud', 'Dirieh'];

        $guardianFirstNames = ['Abdi', 'Mohamed', 'Hassan', 'Ali', 'Ibrahim', 'Omar', 'Fatouma', 'Amina', 'Safia', 'Hodan'];
        $relations          = ['father', 'mother', 'uncle', 'aunt', 'grandfather'];
        $quarters           = ['Balbala', 'Arhiba', 'Boulaos', 'Haïti', 'Bé'];
        $professions        = ['Fonctionnaire', 'Commerçant', 'Enseignant', 'Médecin', 'Ingénieur'];

        $studentCount  = 0;
        $guardianCount = 0;

        for ($i = 1; $i <= 60; $i++) {
            $gender    = $i % 2 === 0 ? 'male' : 'female';
            $firstName = $gender === 'male'
                ? $maleFirstNames[array_rand($maleFirstNames)]
                : $femaleFirstNames[array_rand($femaleFirstNames)];
            $lastName  = $lastNames[array_rand($lastNames)];
            $dob       = now()->subYears(rand(6, 18))->subDays(rand(0, 365))->format('Y-m-d');
            $code      = 'STU-' . str_pad($i, 4, '0', STR_PAD_LEFT);
            $address   = 'Quartier ' . $quarters[array_rand($quarters)] . ', Djibouti';

            // ── Student record ─────────────────────────────────────────────────
            $student = Student::firstOrCreate(
                ['school_id' => $school->id, 'student_code' => $code],
                [
                    'uuid'           => (string) Str::uuid(),
                    'school_id'      => $school->id,
                    'name'           => $firstName . ' ' . $lastName,
                    'gender'         => $gender,
                    'date_of_birth'  => $dob,
                    'place_of_birth' => 'Djibouti',
                    'nationality'    => 'DJ',
                    'address'        => $address,
                    'is_active'      => true,
                ]
            );

            // ── Student portal user ────────────────────────────────────────────
            $studentEmail = strtolower(
                Str::ascii($firstName) . '.' . Str::ascii($lastName) . $i . '@eleve.ecole-demo.dj'
            );
            $studentUser = User::firstOrCreate(
                ['email' => $studentEmail],
                [
                    'uuid'      => (string) Str::uuid(),
                    'school_id' => $school->id,
                    'name'      => $firstName . ' ' . $lastName,
                    'password'  => Hash::make('password'),
                    'ui_lang'   => 'fr',
                    'timezone'  => 'Africa/Djibouti',
                ]
            );
            $studentUser->syncRoles(['student']);

            // Link student record to portal user if not already linked
            if (! $student->user_id) {
                $student->update(['user_id' => $studentUser->id]);
            }

            // ── Guardian record ────────────────────────────────────────────────
            $gFirst = $guardianFirstNames[array_rand($guardianFirstNames)];
            $gEmail = strtolower(Str::ascii($gFirst) . '.' . Str::ascii($lastName) . $i . '@parent.ecole-demo.dj');

            // Guardian portal user
            $guardianUser = User::firstOrCreate(
                ['email' => $gEmail],
                [
                    'uuid'      => (string) Str::uuid(),
                    'school_id' => $school->id,
                    'name'      => $gFirst . ' ' . $lastName,
                    'password'  => Hash::make('password'),
                    'ui_lang'   => 'fr',
                    'timezone'  => 'Africa/Djibouti',
                ]
            );
            $guardianUser->syncRoles(['guardian']);

            // Guardian profile record (firstOrCreate prevents duplicates on re-run)
            $guardian = Guardian::firstOrCreate(
                ['school_id' => $school->id, 'email' => $gEmail],
                [
                    'uuid'       => (string) Str::uuid(),
                    'school_id'  => $school->id,
                    'user_id'    => $guardianUser->id,
                    'name'       => $gFirst . ' ' . $lastName,
                    'gender'     => 'male',
                    'phone'      => '+253 77 ' . rand(10, 99) . ' ' . rand(10, 99) . ' ' . rand(10, 99),
                    'email'      => $gEmail,
                    'profession' => $professions[array_rand($professions)],
                    'address'    => $address,
                    'is_active'  => true,
                ]
            );

            if (! $guardian->user_id) {
                $guardian->update(['user_id' => $guardianUser->id]);
            }

            $student->guardians()->syncWithoutDetaching([
                $guardian->id => [
                    'relation'              => $relations[array_rand($relations)],
                    'is_primary'            => true,
                    'has_custody'           => true,
                    'can_pickup'            => true,
                    'receive_notifications' => true,
                ],
            ]);

            $studentCount++;
            $guardianCount++;
        }

        $this->command->info("  → {$studentCount} students + {$guardianCount} guardians created (with portal User accounts).");
        $this->command->line('    Student login  : amina.hassan1@eleve.ecole-demo.dj  / password');
        $this->command->line('    Guardian login : abdi.hassan1@parent.ecole-demo.dj / password');
    }
}
