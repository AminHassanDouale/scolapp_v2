<?php

namespace App\Console\Commands;

use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Create one test user per role for a school so each portal can be tried.
 * Teacher gets a Teacher record + a class; student/guardian reuse the school's
 * enrolled student (and its guardian) so their portals show real seeded data.
 *
 *   php artisan school:seed-users ECOLEBARWAQO
 */
class SeedSchoolTestUsers extends Command
{
    protected $signature = 'school:seed-users {code : School code/UUID/slug} {--password=password}';
    protected $description = 'Create one test user per role for a school (to test each portal)';

    /** @var array<int, array{0:string,1:string,2:string}> */
    private array $rows = [];

    public function handle(): int
    {
        $code   = $this->argument('code');
        $school = School::where('code', strtoupper($code))->orWhere('uuid', $code)->orWhere('slug', $code)->first();
        if (! $school) {
            $this->error("École introuvable : {$code}");
            return self::FAILURE;
        }

        $pwd = (string) $this->option('password');
        $dom = strtolower(preg_replace('/[^A-Za-z0-9]/', '', $school->code));

        $make = function (string $role, string $name) use ($school, $pwd, $dom): User {
            $email = $role . '@' . $dom . '.dj';
            $user  = User::firstOrCreate(
                ['email' => $email],
                ['uuid' => (string) Str::uuid(), 'school_id' => $school->id, 'name' => $name,
                 'ui_lang' => 'fr', 'timezone' => $school->timezone ?? 'Africa/Djibouti', 'password' => Hash::make($pwd)],
            );
            $user->update(['password' => Hash::make($pwd), 'school_id' => $school->id]);
            $user->syncRoles([$role]);
            $this->rows[] = [$role, $email, $pwd];
            return $user;
        };

        // ── Staff portals (no domain record needed) ───────────────────────────
        $make('admin', 'Admin Test');
        $make('director', 'Directeur Test');
        $make('accountant', 'Comptable Test');
        $make('caissier', 'Caissier Test');
        $make('monitor', 'Surveillant Test');

        // ── Teacher (+ Teacher record + a class + subjects) ───────────────────
        $teacherUser = $make('teacher', 'Enseignant Test');
        $teacher = Teacher::firstOrCreate(
            ['school_id' => $school->id, 'user_id' => $teacherUser->id],
            ['uuid' => (string) Str::uuid(), 'name' => 'Enseignant Test', 'email' => $teacherUser->email, 'is_active' => true],
        );
        $enr     = Enrollment::where('school_id', $school->id)->where('status', 'confirmed')->with('student')->first();
        $classId = $enr?->school_class_id ?? SchoolClass::where('school_id', $school->id)->value('id');
        if ($classId) {
            $teacher->schoolClasses()->syncWithoutDetaching([$classId]);
            SchoolClass::whereKey($classId)->update(['main_teacher_id' => $teacherUser->id]);
        }
        $teacher->subjects()->syncWithoutDetaching(Subject::where('school_id', $school->id)->limit(4)->pluck('id')->all());

        // ── Student — reuse the enrolled student so its portal shows data ─────
        $student = $enr?->student ?? Student::where('school_id', $school->id)->first();
        if ($student) {
            if ($student->user_id && ($su = User::find($student->user_id))) {
                $su->update(['password' => Hash::make($pwd)]);
                $su->syncRoles(['student']);
                $this->rows[] = ['student', $su->email, $pwd];
            } else {
                $su = $make('student', $student->full_name ?? 'Élève Test');
                $student->update(['user_id' => $su->id]);
            }
        }

        // ── Guardian — reuse the student's guardian if any ────────────────────
        if ($student) {
            $guardian = $student->guardians()->first();
            if ($guardian && $guardian->user_id && ($gu = User::find($guardian->user_id))) {
                $gu->update(['password' => Hash::make($pwd)]);
                $gu->syncRoles(['guardian']);
                $this->rows[] = ['guardian', $gu->email, $pwd];
            } else {
                $gu = $make('guardian', $guardian?->full_name ?? 'Parent Test');
                if (! $guardian) {
                    $guardian = Guardian::create([
                        'uuid' => (string) Str::uuid(), 'school_id' => $school->id, 'user_id' => $gu->id,
                        'name' => 'Parent Test', 'email' => $gu->email, 'is_active' => true,
                    ]);
                    $guardian->students()->syncWithoutDetaching([
                        $student->id => ['has_custody' => true, 'can_pickup' => true, 'receive_notifications' => true],
                    ]);
                } else {
                    $guardian->update(['user_id' => $gu->id]);
                }
            }
        }

        $this->newLine();
        $this->info("Comptes de test — {$school->name} [{$school->code}]");
        $this->table(['Rôle', 'Email', 'Mot de passe'], $this->rows);
        $this->newLine();
        $this->line('super-admin : admin@scolapp.com / password (accès plateforme, toutes les écoles)');

        return self::SUCCESS;
    }
}
