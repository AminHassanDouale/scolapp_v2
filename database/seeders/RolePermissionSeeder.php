<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    // ──────────────────────────────────────────────────────────────────────────
    // Role hierarchy:
    //
    //  PLATFORM LEVEL (SaaS):
    //    super-admin  → manages the platform (all schools, plans, billing)
    //                   Gate::before bypass means no permission checks needed
    //
    //  SCHOOL LEVEL (admin panel at /admin):
    //    admin        → full school admin (except role management)
    //    director     → academic management + read-only finance
    //    accountant   → finance management
    //
    //  PORTAL LEVEL (dedicated portals, role-gated via route middleware):
    //    teacher      → /teacher portal
    //    monitor      → /monitor portal
    //    guardian     → /guardian portal (parent)
    //    student      → /student portal
    //    caissier     → /caissier portal (cashier)
    //
    // Portal roles do not need admin panel permissions.
    // Admin routes are gated: role:super-admin|admin|director|accountant
    // ──────────────────────────────────────────────────────────────────────────

    private array $permissions = [

        // ── Platform (SaaS management) ────────────────────────────────────────
        // super-admin bypasses all via Gate::before — listed here for clarity
        'platform.schools.view'   => [],  // super-admin only
        'platform.schools.manage' => [],
        'platform.users.view'     => [],
        'platform.users.manage'   => [],
        'platform.plans.manage'   => [],
        'platform.settings'       => [],

        // ── Academic structure ────────────────────────────────────────────────
        // Portal roles (teacher, monitor, guardian, student, caissier) are blocked
        // from /admin routes by role middleware, so only school-level roles need
        // these permissions. Portal access is controlled by route role middleware.
        'academic.view'   => ['admin', 'director', 'accountant'],
        'academic.manage' => ['admin', 'director'],

        // ── Students ──────────────────────────────────────────────────────────
        'students.view'   => ['admin', 'director', 'accountant'],
        'students.create' => ['admin', 'director'],
        'students.edit'   => ['admin', 'director'],
        'students.delete' => ['admin'],

        // ── Guardians ─────────────────────────────────────────────────────────
        'guardians.view'   => ['admin', 'director', 'accountant'],
        'guardians.create' => ['admin', 'director'],
        'guardians.edit'   => ['admin', 'director'],
        'guardians.delete' => ['admin'],

        // ── Teachers ──────────────────────────────────────────────────────────
        'teachers.view'   => ['admin', 'director'],
        'teachers.create' => ['admin'],
        'teachers.edit'   => ['admin'],
        'teachers.delete' => ['admin'],

        // ── Enrollments ───────────────────────────────────────────────────────
        'enrollments.view'   => ['admin', 'director', 'accountant'],
        'enrollments.create' => ['admin', 'director'],
        'enrollments.edit'   => ['admin', 'director'],
        'enrollments.delete' => ['admin'],

        // ── Attendance (admin panel only) ─────────────────────────────────────
        'attendance.view'   => ['admin', 'director'],
        'attendance.mark'   => ['admin', 'director'],
        'attendance.report' => ['admin', 'director'],

        // ── Timetable ─────────────────────────────────────────────────────────
        'timetable.view'   => ['admin', 'director'],
        'timetable.manage' => ['admin', 'director'],

        // ── Assessments ───────────────────────────────────────────────────────
        'assessments.view'    => ['admin', 'director'],
        'assessments.create'  => ['admin', 'director'],
        'assessments.edit'    => ['admin', 'director'],
        'assessments.delete'  => ['admin', 'director'],
        'assessments.publish' => ['admin', 'director'],

        // ── Report Cards ──────────────────────────────────────────────────────
        'report-cards.view'     => ['admin', 'director'],
        'report-cards.generate' => ['admin', 'director'],
        'report-cards.publish'  => ['admin', 'director'],

        // ── Invoices ──────────────────────────────────────────────────────────
        // caissier keeps invoices/payments permissions — used via can() in the
        // caissier portal views and PDF export controller.
        'invoices.view'   => ['admin', 'director', 'accountant', 'caissier'],
        'invoices.create' => ['admin', 'accountant', 'caissier'],
        'invoices.edit'   => ['admin', 'accountant'],
        'invoices.delete' => ['admin'],

        // ── Payments ──────────────────────────────────────────────────────────
        'payments.view'    => ['admin', 'director', 'accountant', 'caissier'],
        'payments.create'  => ['admin', 'accountant', 'caissier'],
        'payments.confirm' => ['admin', 'accountant', 'caissier'],
        'payments.cancel'  => ['admin', 'accountant'],

        // ── Fee Schedules ─────────────────────────────────────────────────────
        'fee-schedules.view'   => ['admin', 'director', 'accountant'],
        'fee-schedules.manage' => ['admin', 'accountant'],

        // ── Announcements (admin panel) ───────────────────────────────────────
        'announcements.view'   => ['admin', 'director', 'accountant'],
        'announcements.create' => ['admin', 'director'],
        'announcements.edit'   => ['admin', 'director'],
        'announcements.delete' => ['admin', 'director'],

        // ── Messages (admin panel at /admin/messages) ─────────────────────────
        // teacher/guardian/caissier use portal messaging — no can: gate used there
        'messages.view' => ['admin', 'director', 'accountant'],
        'messages.send' => ['admin', 'director', 'accountant'],

        // ── Reports ───────────────────────────────────────────────────────────
        'reports.view' => ['admin', 'director', 'accountant'],

        // ── Scheduled Tasks ───────────────────────────────────────────────────
        'scheduled-tasks.view'   => ['admin', 'director'],
        'scheduled-tasks.manage' => ['admin'],

        // ── Settings ──────────────────────────────────────────────────────────
        'settings.school.view'  => ['admin', 'director'],
        'settings.school.edit'  => ['admin'],
        'settings.users.view'   => ['admin'],
        'settings.users.manage' => ['admin'],
        'settings.roles.view'   => ['admin'],
        'settings.roles.manage' => [], // super-admin only (via Gate::before)

        // ── Billing / D-Money API ─────────────────────────────────────────────
        // billing.view  → see local D-Money transactions (admin panel)
        // billing.manage → full API management: plans, subs, refunds, webhooks
        'billing.view'   => ['admin', 'director', 'accountant'],
        'billing.manage' => ['admin'],
    ];

    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Create all permissions
        foreach (array_keys($this->permissions) as $permName) {
            Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);
        }
        $this->command->info('✅ ' . count($this->permissions) . ' permissions created/verified.');

        // 2. Ensure all roles exist
        $roleNames = [
            'super-admin', 'admin', 'director', 'accountant',
            'teacher', 'monitor', 'guardian', 'student', 'caissier',
        ];
        foreach ($roleNames as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }
        $this->command->info('✅ ' . count($roleNames) . ' roles created/verified.');

        // 3. Sync permissions to roles
        $rolePerms = array_fill_keys($roleNames, []);
        foreach ($this->permissions as $perm => $roles) {
            foreach ($roles as $role) {
                $rolePerms[$role][] = $perm;
            }
        }
        foreach ($rolePerms as $roleName => $perms) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                $role->syncPermissions($perms);
                $this->command->info("  → {$roleName}: " . count($perms) . ' permissions synced.');
            }
        }

        $this->command->info('✅ All role permissions synced.');
    }
}
