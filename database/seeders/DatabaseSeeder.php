<?php

namespace Database\Seeders;

use App\Models\AcademicCycle;
use App\Models\AcademicYear;
use App\Models\Grade;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. Roles & Permissions ─────────────────────────────────────────────
        $this->call(RolePermissionSeeder::class);

        // ── 2. Demo School ─────────────────────────────────────────────────────
        $school = School::firstOrCreate(
            ['slug' => 'ecole-demo'],
            [
                'uuid'           => (string) Str::uuid(),
                'name'           => 'École Démo ScolApp',
                'code'           => 'DEMO001',
                'city'           => 'Djibouti',
                'country'        => 'DJ',
                'currency'       => 'DJF',
                'default_locale' => 'fr',
                'timezone'       => 'Africa/Djibouti',
                'date_format'    => 'd/m/Y',
                'vat_rate'       => 0,
                'is_active'      => true,
            ]
        );

        // Always keep SaaS fields in sync on re-seed (firstOrCreate won't update them)
        $school->update([
            'plan'                 => 'pro',
            'trial_ends_at'        => null,
            'subscription_ends_at' => now()->addYear(),
            'contact_name'         => 'Ahmed Diriye',
            'max_students'         => 0, // 0 = use plan default
            'max_teachers'         => 0,
        ]);

        $this->command->info('✅ School: ' . $school->name . ' [pro, expires ' . now()->addYear()->format('d/m/Y') . ']');

        // ── 3. Demo Users (school-level roles) ────────────────────────────────
        $demoUsers = [
            // Platform
            ['email' => 'admin@scolapp.dj',       'name' => 'Super Admin',      'role' => 'super-admin', 'school_id' => null],
            // Admin panel roles
            ['email' => 'directeur@scolapp.dj',   'name' => 'Ahmed Diriye',     'role' => 'admin',       'school_id' => $school->id],
            ['email' => 'proviseur@scolapp.dj',   'name' => 'Khadija Abdi',     'role' => 'director',    'school_id' => $school->id],
            ['email' => 'comptable@scolapp.dj',   'name' => 'Mariam Osman',     'role' => 'accountant',  'school_id' => $school->id],
            // Portal-only roles
            ['email' => 'caissier@scolapp.dj',    'name' => 'Hassan Caissier',  'role' => 'caissier',    'school_id' => $school->id],
            ['email' => 'surveillant@scolapp.dj', 'name' => 'Omar Surveillant', 'role' => 'monitor',     'school_id' => $school->id],
        ];

        foreach ($demoUsers as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'uuid'      => (string) Str::uuid(),
                    'school_id' => $data['school_id'],
                    'name'      => $data['name'],
                    'password'  => Hash::make('password'),
                    'ui_lang'   => 'fr',
                    'timezone'  => 'Africa/Djibouti',
                ]
            );
            $user->syncRoles([$data['role']]);
        }
        $this->command->info('✅ Demo users (6 school-level roles) created.');

        // ── 4. Academic Year ───────────────────────────────────────────────────
        $year = AcademicYear::firstOrCreate(
            ['school_id' => $school->id, 'name' => '2025-2026'],
            [
                'uuid'       => (string) Str::uuid(),
                'start_date' => '2025-09-01',
                'end_date'   => '2026-06-30',
                'is_current' => true,
                'is_active'  => true,
            ]
        );
        // Ensure it stays current on re-seed
        $year->update(['is_current' => true, 'is_active' => true]);

        // ── 5. Academic Cycles & Grades ────────────────────────────────────────
        $cycles = [
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

        foreach ($cycles as $cycleData) {
            $grades = $cycleData['grades'];
            unset($cycleData['grades']);
            $cycle = AcademicCycle::firstOrCreate(
                ['school_id' => $school->id, 'code' => $cycleData['code']],
                array_merge($cycleData, ['uuid' => (string) Str::uuid(), 'school_id' => $school->id, 'is_active' => true])
            );
            foreach ($grades as $gradeData) {
                Grade::firstOrCreate(
                    ['school_id' => $school->id, 'academic_cycle_id' => $cycle->id, 'code' => $gradeData['code']],
                    array_merge($gradeData, ['uuid' => (string) Str::uuid(), 'school_id' => $school->id, 'academic_cycle_id' => $cycle->id, 'is_active' => true])
                );
            }
        }
        $this->command->info('✅ Academic structure (4 cycles, 15 grades) created.');

        // ── 6. Sub-seeders ─────────────────────────────────────────────────────
        $this->call([
            SubjectSeeder::class,
            TeacherSeeder::class,
            SchoolClassSeeder::class,
            FeeSeeder::class,
            StudentSeeder::class,
            EnrollmentSeeder::class,
            PaymentSeeder::class,
            AssessmentSeeder::class,
            AttendanceSeeder::class,
            AnnouncementSeeder::class,
            MessageSeeder::class,
            RoomSeeder::class,
            TimetableSeeder::class,
        ]);

        // ── 7. Additional schools ──────────────────────────────────────────────
        $this->call(PetitsFutesSeeder::class);

        // ── Summary ────────────────────────────────────────────────────────────
        $this->command->newLine();
        $this->command->info('🎉 ScolApp SMS fully seeded!');
        $this->command->table(
            ['Rôle', 'Email', 'Mot de passe', 'Portail'],
            [
                ['super-admin', 'admin@scolapp.dj',              'password', '/platform — gestion plateforme SaaS'],
                ['admin',       'directeur@scolapp.dj',           'password', '/admin    — tout sauf rôles'],
                ['director',    'proviseur@scolapp.dj',           'password', '/admin    — académique + lecture finance'],
                ['accountant',  'comptable@scolapp.dj',           'password', '/admin    — finance complète'],
                ['caissier',    'caissier@scolapp.dj',            'password', '/caissier — paiements + factures'],
                ['monitor',     'surveillant@scolapp.dj',         'password', '/monitor  — présences + élèves'],
                ['teacher',     'amina.hassan@ecole-demo.dj',     'password', '/teacher  — EDT + présences + notes'],
                ['guardian',    'abdi.hassan1@parent.ecole-demo.dj',  'password', '/guardian — enfants + factures + notes'],
                ['student',     'amina.hassan1@eleve.ecole-demo.dj',  'password', '/student  — EDT + notes + présences'],
            ]
        );
    }
}
