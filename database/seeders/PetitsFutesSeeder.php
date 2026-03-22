<?php

namespace Database\Seeders;

use App\Models\AcademicCycle;
use App\Models\AcademicYear;
use App\Models\AttendanceEntry;
use App\Models\AttendanceSession;
use App\Models\Enrollment;
use App\Models\FeeItem;
use App\Models\FeeSchedule;
use App\Models\Grade;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Seeder for Groupe Scolaire Les Petits Futés
 * Gabode 5, Djibouti-Ville — contact@petitsfutes.com — +253 21 34 86 96
 * Maternelle · Primaire · Collège — fondé en 2010
 */
class PetitsFutesSeeder extends Seeder
{
    private const SLUG = 'petits-futes';

    public function run(): void
    {
        // ── 1. École ──────────────────────────────────────────────────────────
        $school = School::firstOrCreate(
            ['slug' => self::SLUG],
            [
                'uuid'           => (string) Str::uuid(),
                'name'           => 'Les Petits Futés',
                'code'           => 'LPF001',
                'address'        => 'Gabode 5',
                'city'           => 'Djibouti-Ville',
                'country'        => 'DJ',
                'currency'       => 'DJF',
                'phone'          => '+253 21 34 86 96',
                'email'          => 'contact@petitsfutes.com',
                'website'        => 'https://www.petitsfutes.org',
                'default_locale' => 'fr',
                'timezone'       => 'Africa/Djibouti',
                'date_format'    => 'd/m/Y',
                'vat_rate'       => 0,
                'is_active'      => true,
            ]
        );

        $school->update([
            'plan'                 => 'pro',
            'trial_ends_at'        => null,
            'subscription_ends_at' => now()->addYear(),
            'contact_name'         => 'Direction Les Petits Futés',
            'max_students'         => 0,
            'max_teachers'         => 0,
        ]);

        $this->command->info('✅ École : ' . $school->name . ' [pro]');

        // ── 2. Utilisateurs demo ──────────────────────────────────────────────
        $demoUsers = [
            ['email' => 'admin@petitsfutes.dj',       'name' => 'Super Admin LPF',      'role' => 'super-admin', 'school_id' => null],
            ['email' => 'directeur@petitsfutes.dj',   'name' => 'Moussa Djama',         'role' => 'admin',       'school_id' => $school->id],
            ['email' => 'proviseur@petitsfutes.dj',   'name' => 'Fatouma Abdi',         'role' => 'director',    'school_id' => $school->id],
            ['email' => 'comptable@petitsfutes.dj',   'name' => 'Hodan Ibrahim',        'role' => 'accountant',  'school_id' => $school->id],
            ['email' => 'caissier@petitsfutes.dj',    'name' => 'Aden Warsame',         'role' => 'caissier',    'school_id' => $school->id],
            ['email' => 'surveillant@petitsfutes.dj', 'name' => 'Omar Ali Surveillant', 'role' => 'monitor',     'school_id' => $school->id],
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
        $this->command->info('✅ Utilisateurs demo (6 rôles) créés.');

        // ── 3. Année scolaire ─────────────────────────────────────────────────
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
        $year->update(['is_current' => true, 'is_active' => true]);

        // ── 4. Cycles & Niveaux (uniquement Maternelle, Primaire, Collège) ────
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
        ];

        $gradeMap = []; // code → Grade model
        foreach ($cycles as $cycleData) {
            $grades = $cycleData['grades'];
            unset($cycleData['grades']);
            $cycle = AcademicCycle::firstOrCreate(
                ['school_id' => $school->id, 'code' => $cycleData['code']],
                array_merge($cycleData, ['uuid' => (string) Str::uuid(), 'school_id' => $school->id, 'is_active' => true])
            );
            foreach ($grades as $gradeData) {
                $grade = Grade::firstOrCreate(
                    ['school_id' => $school->id, 'academic_cycle_id' => $cycle->id, 'code' => $gradeData['code']],
                    array_merge($gradeData, ['uuid' => (string) Str::uuid(), 'school_id' => $school->id, 'academic_cycle_id' => $cycle->id, 'is_active' => true])
                );
                $gradeMap[$gradeData['code']] = $grade;
            }
        }
        $this->command->info('✅ Structure académique (3 cycles, 12 niveaux) créée.');

        // ── 5. Matières ───────────────────────────────────────────────────────
        $subjectDefs = [
            ['name' => 'Mathématiques',       'code' => 'MATH', 'color' => '#6366f1', 'default_coefficient' => 3],
            ['name' => 'Français',            'code' => 'FRA',  'color' => '#3b82f6', 'default_coefficient' => 3],
            ['name' => 'Arabe',               'code' => 'ARB',  'color' => '#ef4444', 'default_coefficient' => 2],
            ['name' => 'Sciences Naturelles', 'code' => 'SVT',  'color' => '#22c55e', 'default_coefficient' => 2],
            ['name' => 'Histoire-Géographie', 'code' => 'HG',   'color' => '#f59e0b', 'default_coefficient' => 2],
            ['name' => 'Anglais',             'code' => 'ANG',  'color' => '#14b8a6', 'default_coefficient' => 2],
            ['name' => 'Éducation Physique',  'code' => 'EPS',  'color' => '#f97316', 'default_coefficient' => 1],
            ['name' => 'Éducation Islamique', 'code' => 'EI',   'color' => '#0ea5e9', 'default_coefficient' => 1],
            ['name' => 'Éveil / Arts',        'code' => 'EVE',  'color' => '#ec4899', 'default_coefficient' => 1],
        ];

        $subjectMap = []; // code → Subject model
        foreach ($subjectDefs as $def) {
            $subjectMap[$def['code']] = Subject::firstOrCreate(
                ['school_id' => $school->id, 'code' => $def['code']],
                array_merge($def, ['uuid' => (string) Str::uuid(), 'school_id' => $school->id, 'is_active' => true])
            );
        }
        $this->command->info('✅ ' . count($subjectDefs) . ' matières créées.');

        // ── 6. Enseignants ────────────────────────────────────────────────────
        $teacherDefs = [
            ['name' => 'Amina Guedi',     'gender' => 'female', 'email' => 'amina.guedi@petitsfutes.dj',     'subjects' => ['MATH', 'SVT']],
            ['name' => 'Omar Hassan',     'gender' => 'male',   'email' => 'omar.hassan@petitsfutes.dj',     'subjects' => ['FRA', 'HG']],
            ['name' => 'Safia Aden',      'gender' => 'female', 'email' => 'safia.aden@petitsfutes.dj',      'subjects' => ['ARB', 'EI']],
            ['name' => 'Yusuf Warsame',   'gender' => 'male',   'email' => 'yusuf.warsame@petitsfutes.dj',   'subjects' => ['ANG', 'EPS']],
            ['name' => 'Hodan Farah',     'gender' => 'female', 'email' => 'hodan.farah@petitsfutes.dj',     'subjects' => ['EVE', 'FRA']],
            ['name' => 'Ibrahim Djama',   'gender' => 'male',   'email' => 'ibrahim.djama@petitsfutes.dj',   'subjects' => ['MATH', 'HG']],
        ];

        $teachers = collect();
        foreach ($teacherDefs as $i => $data) {
            $subjects = $data['subjects'];
            unset($data['subjects']);

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
            $user->syncRoles(['teacher']);

            $teacher = Teacher::firstOrCreate(
                ['school_id' => $school->id, 'email' => $data['email']],
                array_merge($data, [
                    'uuid'      => (string) Str::uuid(),
                    'school_id' => $school->id,
                    'user_id'   => $user->id,
                    'phone'     => '+253 77 ' . rand(10, 99) . ' ' . rand(10, 99) . ' ' . rand(10, 99),
                    'hire_date' => now()->subYears(rand(1, 12))->format('Y-m-d'),
                    'is_active' => true,
                    'reference' => 'LPF-TCH-' . str_pad($i + 1, 3, '0', STR_PAD_LEFT),
                ])
            );

            if (! $teacher->user_id) {
                $teacher->update(['user_id' => $user->id]);
            }

            $subjectIds = collect($subjects)
                ->map(fn ($code) => $subjectMap[$code]?->id)
                ->filter();
            $teacher->subjects()->syncWithoutDetaching($subjectIds);

            $teachers->push($teacher);
        }
        $this->command->info('✅ ' . $teachers->count() . ' enseignants créés.');

        // ── 7. Classes ────────────────────────────────────────────────────────
        // Les Petits Futés : 1 classe par niveau (école à taille humaine, ~200 élèves)
        $classMap = [
            'PS'  => ['name' => 'PS',    'capacity' => 18],
            'MS'  => ['name' => 'MS',    'capacity' => 20],
            'GS'  => ['name' => 'GS',    'capacity' => 20],
            'CP'  => ['name' => 'CP',    'capacity' => 22],
            'CE1' => ['name' => 'CE1',   'capacity' => 22],
            'CE2' => ['name' => 'CE2',   'capacity' => 22],
            'CM1' => ['name' => 'CM1',   'capacity' => 24],
            'CM2' => ['name' => 'CM2',   'capacity' => 24],
            '6E'  => ['name' => '6ème',  'capacity' => 28],
            '5E'  => ['name' => '5ème',  'capacity' => 28],
            '4E'  => ['name' => '4ème',  'capacity' => 26],
            '3E'  => ['name' => '3ème',  'capacity' => 25],
        ];

        $classes   = collect();
        $tIdx      = 0;
        foreach ($classMap as $gradeCode => $cls) {
            $grade = $gradeMap[$gradeCode] ?? null;
            if (! $grade) continue;

            $teacher = $teachers->get($tIdx % $teachers->count());
            $tIdx++;

            $schoolClass = SchoolClass::firstOrCreate(
                ['school_id' => $school->id, 'name' => $cls['name'], 'academic_year_id' => $year->id],
                [
                    'uuid'             => (string) Str::uuid(),
                    'school_id'        => $school->id,
                    'grade_id'         => $grade->id,
                    'academic_year_id' => $year->id,
                    'main_teacher_id'  => $teacher->id,
                    'capacity'         => $cls['capacity'],
                    'room'             => 'Salle ' . $cls['name'],
                ]
            );
            $classes->push($schoolClass);
        }
        $this->command->info('✅ ' . $classes->count() . ' classes créées.');

        // ── 8. Barèmes de frais ───────────────────────────────────────────────
        $feeItemDefs = [
            ['name' => 'Frais de scolarité',  'code' => 'SCOL',  'type' => 'tuition'],
            ['name' => "Frais d'inscription", 'code' => 'INSCR', 'type' => 'registration'],
            ['name' => 'Cantine',             'code' => 'CANT',  'type' => 'other'],
            ['name' => 'Transport scolaire',  'code' => 'TRANS', 'type' => 'transport'],
        ];
        $feeItems = [];
        foreach ($feeItemDefs as $def) {
            $feeItems[$def['code']] = FeeItem::firstOrCreate(
                ['school_id' => $school->id, 'code' => $def['code']],
                array_merge($def, ['uuid' => (string) Str::uuid(), 'school_id' => $school->id, 'is_active' => true])
            );
        }

        // Realistic DJF fees for a private school at Djibouti with 98% success rate
        $gradeFees = [
            'PS'  => ['SCOL' => 60000,  'INSCR' => 10000, 'CANT' => 8000],
            'MS'  => ['SCOL' => 60000,  'INSCR' => 10000, 'CANT' => 8000],
            'GS'  => ['SCOL' => 65000,  'INSCR' => 10000, 'CANT' => 8000],
            'CP'  => ['SCOL' => 70000,  'INSCR' => 10000, 'CANT' => 10000],
            'CE1' => ['SCOL' => 70000,  'INSCR' => 10000, 'CANT' => 10000],
            'CE2' => ['SCOL' => 75000,  'INSCR' => 10000, 'CANT' => 10000],
            'CM1' => ['SCOL' => 75000,  'INSCR' => 10000, 'CANT' => 10000],
            'CM2' => ['SCOL' => 80000,  'INSCR' => 10000, 'CANT' => 10000],
            '6E'  => ['SCOL' => 100000, 'INSCR' => 12000, 'CANT' => 12000, 'TRANS' => 8000],
            '5E'  => ['SCOL' => 100000, 'INSCR' => 12000, 'CANT' => 12000, 'TRANS' => 8000],
            '4E'  => ['SCOL' => 105000, 'INSCR' => 12000, 'CANT' => 12000, 'TRANS' => 8000],
            '3E'  => ['SCOL' => 110000, 'INSCR' => 12000, 'CANT' => 12000, 'TRANS' => 8000],
        ];

        foreach ($gradeFees as $gradeCode => $amounts) {
            $grade = $gradeMap[$gradeCode] ?? null;
            if (! $grade) continue;

            $schedule = FeeSchedule::firstOrCreate(
                ['school_id' => $school->id, 'academic_year_id' => $year->id, 'grade_id' => $grade->id],
                [
                    'uuid'             => (string) Str::uuid(),
                    'school_id'        => $school->id,
                    'academic_year_id' => $year->id,
                    'grade_id'         => $grade->id,
                    'name'             => 'Barème ' . $gradeCode . ' — LPF 2025-2026',
                    'schedule_type'    => 'yearly',
                    'is_default'       => true,
                    'is_active'        => true,
                ]
            );

            $sync = [];
            foreach ($amounts as $code => $amount) {
                if (isset($feeItems[$code])) {
                    $sync[$feeItems[$code]->id] = ['amount' => $amount];
                }
            }
            $schedule->feeItems()->sync($sync);
        }
        $this->command->info('✅ Barèmes de frais créés pour les 12 niveaux.');

        // ── 9. Élèves & Parents ───────────────────────────────────────────────
        // Prénoms typiques de Djibouti
        $maleFirst   = ['Mohamed', 'Omar', 'Ibrahim', 'Abdi', 'Hassan', 'Yusuf', 'Ismail', 'Aden', 'Guled', 'Kadar', 'Ali', 'Houssein'];
        $femaleFirst = ['Amina', 'Fatouma', 'Hodan', 'Safia', 'Nimo', 'Asha', 'Zahra', 'Faadumo', 'Ifrah', 'Filsan', 'Khadija', 'Rahmo'];
        $lastNames   = ['Hassan', 'Ali', 'Ahmed', 'Abdillahi', 'Omar', 'Moussa', 'Guedi', 'Ismail', 'Warsame', 'Aden', 'Farah', 'Djama'];

        $guardianFirst  = ['Abdi', 'Mohamed', 'Hassan', 'Ali', 'Ibrahim', 'Fatouma', 'Amina', 'Safia'];
        $relations      = ['father', 'mother', 'uncle', 'aunt'];
        $professions    = ['Fonctionnaire', 'Commerçant', 'Médecin', 'Enseignant', 'Ingénieur', 'Avocat'];
        $quarters       = ['Gabode 5', 'Balbala', 'Arhiba', 'Boulaos', 'Haïti'];

        $studentCount  = 0;
        $guardianCount = 0;

        for ($i = 1; $i <= 36; $i++) {
            $gender    = $i % 2 === 0 ? 'male' : 'female';
            $firstName = $gender === 'male'
                ? $maleFirst[array_rand($maleFirst)]
                : $femaleFirst[array_rand($femaleFirst)];
            $lastName  = $lastNames[array_rand($lastNames)];
            $code      = 'LPF-' . str_pad($i, 4, '0', STR_PAD_LEFT);
            $dob       = now()->subYears(rand(4, 15))->subDays(rand(0, 365))->format('Y-m-d');
            $address   = 'Quartier ' . $quarters[array_rand($quarters)] . ', Djibouti-Ville';

            // Student record
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

            // Student portal user
            $studentEmail = strtolower(Str::ascii($firstName) . '.' . Str::ascii($lastName) . $i . '@eleve.petitsfutes.dj');
            $studentUser  = User::firstOrCreate(
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
            if (! $student->user_id) {
                $student->update(['user_id' => $studentUser->id]);
            }

            // Guardian
            $gFirst = $guardianFirst[array_rand($guardianFirst)];
            $gEmail = strtolower(Str::ascii($gFirst) . '.' . Str::ascii($lastName) . $i . '@parent.petitsfutes.dj');

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
        $this->command->info("✅ {$studentCount} élèves + {$guardianCount} parents créés.");

        // ── 10. Inscriptions ──────────────────────────────────────────────────
        $students    = Student::where('school_id', $school->id)->get()->shuffle();
        $enrollCount = 0;
        $invoiceCount = 0;
        $sIdx        = 0;

        foreach ($classes as $class) {
            $schedule = FeeSchedule::where('school_id', $school->id)
                ->where('academic_year_id', $year->id)
                ->where('grade_id', $class->grade_id)
                ->first();

            $slots = min($class->capacity, max(0, $students->count() - $sIdx), 4); // 3–4 per class

            for ($s = 0; $s < $slots; $s++) {
                $student = $students[$sIdx++] ?? null;
                if (! $student) break;

                if (Enrollment::where('student_id', $student->id)->where('academic_year_id', $year->id)->exists()) {
                    continue;
                }

                $status     = $s < 1 ? 'hold' : 'confirmed';
                $enrollment = Enrollment::create([
                    'uuid'             => (string) Str::uuid(),
                    'reference'        => 'LPF-ENR-' . strtoupper(Str::random(6)),
                    'school_id'        => $school->id,
                    'student_id'       => $student->id,
                    'academic_year_id' => $year->id,
                    'school_class_id'  => $class->id,
                    'grade_id'         => $class->grade_id,
                    'status'           => $status,
                    'enrolled_at'      => now()->subDays(rand(30, 90))->format('Y-m-d'),
                    'confirmed_at'     => $status === 'confirmed' ? now()->subDays(rand(1, 29))->format('Y-m-d') : null,
                ]);
                $enrollCount++;

                // Invoice for confirmed enrollments with a fee schedule
                if ($status === 'confirmed' && $schedule) {
                    $subtotal = $schedule->feeItems->sum(fn ($i) => $i->pivot->amount ?? 0);
                    if ($subtotal > 0) {
                        $isPaid     = rand(0, 3) > 0; // 75% paid
                        $paidTotal  = $isPaid ? $subtotal : (int) ($subtotal * rand(0, 1) * 0.5);
                        $balanceDue = $subtotal - $paidTotal;
                        $invStatus  = match (true) {
                            $paidTotal >= $subtotal => 'paid',
                            $paidTotal > 0          => 'partially_paid',
                            default                 => 'overdue',
                        };

                        Invoice::create([
                            'uuid'             => (string) Str::uuid(),
                            'reference'        => 'LPF-INV-' . strtoupper(Str::random(8)),
                            'school_id'        => $school->id,
                            'student_id'       => $student->id,
                            'enrollment_id'    => $enrollment->id,
                            'academic_year_id' => $year->id,
                            'fee_schedule_id'  => $schedule->id,
                            'invoice_type'     => 'tuition',
                            'schedule_type'    => 'yearly',
                            'status'           => $invStatus,
                            'issue_date'       => now()->startOfYear()->format('Y-m-d'),
                            'due_date'         => now()->startOfYear()->addMonths(1)->format('Y-m-d'),
                            'subtotal'         => $subtotal,
                            'vat_rate'         => 0,
                            'vat_amount'       => 0,
                            'total'            => $subtotal,
                            'paid_total'       => $paidTotal,
                            'balance_due'      => $balanceDue,
                            'penalty_amount'   => 0,
                        ]);
                        $invoiceCount++;
                    }
                }
            }

            if ($sIdx >= $students->count()) break;
        }
        $this->command->info("✅ {$enrollCount} inscriptions + {$invoiceCount} factures créées.");

        // ── 11. Présences (14 derniers jours scolaires) ───────────────────────
        $firstTeacher   = $teachers->first();
        $seededClasses  = $classes->take(4); // 4 premières classes pour les présences demo

        $schoolDays = [];
        $cursor     = Carbon::today()->subDay();
        while (count($schoolDays) < 14) {
            if (! $cursor->isWeekend()) {
                $schoolDays[] = $cursor->copy();
            }
            $cursor->subDay();
        }
        $schoolDays = array_reverse($schoolDays);

        $sessionCount = 0;
        $entryCount   = 0;

        foreach ($seededClasses as $class) {
            $studentIds = Enrollment::where('school_class_id', $class->id)
                ->where('academic_year_id', $year->id)
                ->where('status', 'confirmed')
                ->pluck('student_id');

            if ($studentIds->isEmpty()) continue;

            foreach ($schoolDays as $day) {
                foreach (['morning', 'afternoon'] as $period) {
                    $exists = AttendanceSession::where('school_id', $school->id)
                        ->where('school_class_id', $class->id)
                        ->where('session_date', $day->format('Y-m-d'))
                        ->where('period', $period)
                        ->exists();

                    if ($exists) continue;

                    $session = AttendanceSession::create([
                        'uuid'             => (string) Str::uuid(),
                        'school_id'        => $school->id,
                        'school_class_id'  => $class->id,
                        'teacher_id'       => $firstTeacher?->id,
                        'academic_year_id' => $year->id,
                        'session_date'     => $day->format('Y-m-d'),
                        'period'           => $period,
                        'start_time'       => $period === 'morning' ? '07:30:00' : '13:00:00',
                        'end_time'         => $period === 'morning' ? '12:30:00' : '17:00:00',
                    ]);
                    $sessionCount++;

                    foreach ($studentIds as $studentId) {
                        $rand   = rand(1, 10);
                        $status = match (true) {
                            $rand <= 8 => 'present',  // 80% présent (école d'excellence)
                            $rand <= 9 => 'absent',
                            default    => 'late',
                        };

                        AttendanceEntry::create([
                            'attendance_session_id' => $session->id,
                            'student_id'            => $studentId,
                            'status'                => $status,
                            'reason'                => null,
                            'notified'              => $status !== 'present',
                        ]);
                        $entryCount++;
                    }
                }
            }
        }
        $this->command->info("✅ {$sessionCount} séances + {$entryCount} entrées de présence créées.");

        // ── Résumé final ──────────────────────────────────────────────────────
        $this->command->newLine();
        $this->command->info('🎉 Les Petits Futés — données de démo complètes !');
        $this->command->table(
            ['Rôle', 'Email', 'Mot de passe'],
            [
                ['admin',      'directeur@petitsfutes.dj',   'password'],
                ['director',   'proviseur@petitsfutes.dj',   'password'],
                ['accountant', 'comptable@petitsfutes.dj',   'password'],
                ['caissier',   'caissier@petitsfutes.dj',    'password'],
                ['monitor',    'surveillant@petitsfutes.dj', 'password'],
                ['teacher',    'amina.guedi@petitsfutes.dj', 'password'],
                ['student',    'amina.hassan1@eleve.petitsfutes.dj',   'password'],
                ['guardian',   'abdi.hassan1@parent.petitsfutes.dj',   'password'],
            ]
        );
    }
}
