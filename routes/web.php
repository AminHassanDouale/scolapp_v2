<?php

use App\Http\Controllers\ContactController;
use App\Http\Controllers\DMoneyPaymentController;
use App\Http\Controllers\WebhookController;
use App\Models\Attachment;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

/*
|--------------------------------------------------------------------------
| Public
|--------------------------------------------------------------------------
*/
Route::get('/', fn () => view('welcome'))->name('home');
Route::post('/contact', [ContactController::class, 'store'])->name('contact.store');

/*
|--------------------------------------------------------------------------
| Webhooks (no CSRF, no auth)
|--------------------------------------------------------------------------
*/
Route::post('/webhooks/billing', [WebhookController::class, 'handle'])->name('webhooks.billing');

/*
|--------------------------------------------------------------------------
| Locale switcher
|--------------------------------------------------------------------------
*/
Route::get('/locale/{locale}', function (string $locale) {
    if (in_array($locale, ['fr', 'en', 'ar'])) {
        session(['locale' => $locale]);
        app()->setLocale($locale);
        if (auth()->check()) {
            auth()->user()->update(['ui_lang' => $locale]);
        }
    }
    return back();
})->name('locale.switch');

/*
|--------------------------------------------------------------------------
| Auth (guest only)
|--------------------------------------------------------------------------
*/
Volt::route('/login',  'pages.auth.login')->middleware('guest')->name('login');
Volt::route('/signup', 'pages.auth.register')->middleware('guest')->name('register');

Route::match(['get', 'post'], '/logout', function () {
    auth()->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return redirect()->route('login');
})->name('auth.logout')->middleware('auth');

/*
|--------------------------------------------------------------------------
| Subscription / School status pages (public — shown after logout)
|--------------------------------------------------------------------------
*/
Route::view('/ecole/suspendue', 'errors.school-suspended')->name('school.suspended');
Route::view('/ecole/expiree',   'errors.school-expired')->name('school.expired');

/*
|--------------------------------------------------------------------------
| Authenticated — Profile (all roles)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'school.active'])
    ->prefix('profil')->name('profile.')
    ->group(function () {
        Volt::route('/',          'profile.show')->name('show');
        Volt::route('/securite', 'profile.security')->name('security');
    });

/*
|--------------------------------------------------------------------------
| Authenticated — File downloads (school-scoped)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'school.active'])
    ->get('/attachments/{uuid}/download', function (string $uuid) {
        $attachment = Attachment::where('uuid', $uuid)->firstOrFail();
        abort_unless($attachment->school_id === auth()->user()->school_id, 403);
        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);
        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    })->name('attachments.download');

/*
|--------------------------------------------------------------------------
| Platform — /platform
| SaaS super-admin: manages ALL schools, plans, users across the platform.
| Gate::before in AppServiceProvider grants super-admin full access.
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:super-admin'])
    ->prefix('platform')
    ->name('platform.')
    ->group(function () {
        Volt::route('/',                   'platform.dashboard')->name('dashboard');
        Volt::route('/schools',            'platform.schools.index')->name('schools.index');
        Volt::route('/schools/nouveau',    'platform.schools.create')->name('schools.create');
        Volt::route('/schools/{uuid}',     'platform.schools.show')->name('schools.show');
        Volt::route('/utilisateurs',       'platform.users.index')->name('users.index');
        Volt::route('/plans',              'platform.plans.index')->name('plans.index');
        Volt::route('/parametres',         'platform.settings')->name('settings');
    });

/*
|--------------------------------------------------------------------------
| Admin Portal — /admin
| School-level: admin, director, accountant (+ super-admin access via role).
| Each section is gated by Spatie permission via `can:` middleware.
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:super-admin|admin|director|accountant', 'school.active'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        // Dashboard
        Volt::route('/', 'admin.dashboard.index')->name('dashboard');

        // ── Academic ──────────────────────────────────────────────────────────
        Route::middleware('can:academic.view')
            ->prefix('academique')->name('academic.')
            ->group(function () {
                Volt::route('/',         'admin.academic.index')->name('index');
                Volt::route('/cycles',   'admin.academic.cycles')->name('cycles');
                Volt::route('/niveaux',  'admin.academic.grades')->name('grades');
                Volt::route('/classes',  'admin.academic.classes')->name('classes');
                Volt::route('/matieres', 'admin.academic.subjects')->name('subjects');
            });

        // ── Students ──────────────────────────────────────────────────────────
        Route::middleware('can:students.view')
            ->prefix('eleves')->name('students.')
            ->group(function () {
                Volt::route('/',                'admin.students.index')->name('index');
                Volt::route('/nouveau',         'admin.students.create')->middleware('can:students.create')->name('create');
                Volt::route('/{uuid}/modifier', 'admin.students.edit')->middleware('can:students.edit')->name('edit');
                Volt::route('/{uuid}',          'admin.students.show')->name('show');
            });

        // ── Teachers ──────────────────────────────────────────────────────────
        Route::middleware('can:teachers.view')
            ->prefix('enseignants')->name('teachers.')
            ->group(function () {
                Volt::route('/',                'admin.teachers.index')->name('index');
                Volt::route('/nouveau',         'admin.teachers.create')->middleware('can:teachers.create')->name('create');
                Volt::route('/{uuid}/modifier', 'admin.teachers.edit')->middleware('can:teachers.edit')->name('edit');
                Volt::route('/{uuid}',          'admin.teachers.show')->name('show');
            });

        // ── Guardians ─────────────────────────────────────────────────────────
        Route::middleware('can:guardians.view')
            ->prefix('responsables')->name('guardians.')
            ->group(function () {
                Volt::route('/',                'admin.guardians.index')->name('index');
                Volt::route('/nouveau',         'admin.guardians.create')->middleware('can:guardians.create')->name('create');
                Volt::route('/{uuid}/modifier', 'admin.guardians.edit')->middleware('can:guardians.edit')->name('edit');
                Volt::route('/{uuid}',          'admin.guardians.show')->name('show');
            });

        // ── Enrollments ───────────────────────────────────────────────────────
        Route::middleware('can:enrollments.view')
            ->prefix('inscriptions')->name('enrollments.')
            ->group(function () {
                Volt::route('/',        'admin.enrollments.index')->name('index');
                Volt::route('/nouveau', 'admin.enrollments.create')->middleware('can:enrollments.create')->name('create');
                Volt::route('/{uuid}',  'admin.enrollments.show')->name('show');
            });

        // ── Attendance ────────────────────────────────────────────────────────
        Route::middleware('can:attendance.view')
            ->prefix('absences')->name('attendance.')
            ->group(function () {
                Volt::route('/',        'admin.attendance.index')->name('index');
                Volt::route('/saisie',  'admin.attendance.mark')->middleware('can:attendance.mark')->name('mark');
                Volt::route('/rapport', 'admin.attendance.report')->middleware('can:attendance.report')->name('report');
            });

        // ── Timetable ─────────────────────────────────────────────────────────
        Route::middleware('can:timetable.view')
            ->prefix('emploi-du-temps')->name('timetable.')
            ->group(function () {
                Volt::route('/',                'admin.timetable.index')->name('index');
                Volt::route('/nouveau',         'admin.timetable.create')->middleware('can:timetable.manage')->name('create');
                Volt::route('/{uuid}/modifier', 'admin.timetable.edit')->middleware('can:timetable.manage')->name('edit');
                Volt::route('/{uuid}',          'admin.timetable.show')->name('show');
            });

        // ── Assessments ───────────────────────────────────────────────────────
        Route::middleware('can:assessments.view')
            ->prefix('evaluations')->name('assessments.')
            ->group(function () {
                Volt::route('/',        'admin.assessments.index')->name('index');
                Volt::route('/nouveau', 'admin.assessments.create')->middleware('can:assessments.create')->name('create');
                Volt::route('/{uuid}',  'admin.assessments.show')->name('show');
            });

        // ── Report Cards ──────────────────────────────────────────────────────
        Route::middleware('can:report-cards.view')
            ->prefix('bulletins')->name('report-cards.')
            ->group(function () {
                Volt::route('/',       'admin.report-cards.index')->name('index');
                Volt::route('/modele', 'admin.report-cards.template')->middleware('can:report-cards.generate')->name('template');
                Volt::route('/{uuid}', 'admin.report-cards.show')->name('show');
            });

        // ── Finance ───────────────────────────────────────────────────────────
        Route::prefix('finance')->name('finance.')->group(function () {

            Route::middleware('can:invoices.view')
                ->prefix('factures')->name('invoices.')
                ->group(function () {
                    Volt::route('/',                'admin.finance.invoices.index')->name('index');
                    Volt::route('/nouvelle',        'admin.finance.invoices.create')->middleware('can:invoices.create')->name('create');
                    Route::get('/export/pdf',  [\App\Http\Controllers\Finance\InvoiceExportController::class, 'pdf'])->name('export.pdf');
                    Route::get('/export/xlsx', [\App\Http\Controllers\Finance\InvoiceExportController::class, 'xlsx'])->name('export.xlsx');
                    Route::get('/export',      fn () => redirect()->route('admin.finance.invoices.export.pdf'))->name('export');
                    Volt::route('/{uuid}/imprimer', 'admin.finance.invoices.print')->name('print');
                    Volt::route('/{uuid}',          'admin.finance.invoices.show')->name('show');
                });

            Route::middleware('can:payments.view')
                ->prefix('paiements')->name('payments.')
                ->group(function () {
                    Volt::route('/',        'admin.finance.payments.index')->name('index');
                    Volt::route('/suivi',   'admin.finance.payments.suivi')->name('suivi');
                    Volt::route('/nouveau', 'admin.finance.payments.create')->middleware('can:payments.create')->name('create');
                    Volt::route('/{uuid}',  'admin.finance.payments.show')->name('show');
                });

            Route::middleware('can:fee-schedules.view')
                ->prefix('baremes')->name('fee-schedules.')
                ->group(function () {
                    Volt::route('/',        'admin.finance.fee-schedules.index')->name('index');
                    Volt::route('/nouveau', 'admin.finance.fee-schedules.create')->middleware('can:fee-schedules.manage')->name('create');
                    Volt::route('/{uuid}',  'admin.finance.fee-schedules.show')->name('show');
                });

            Route::middleware('can:payments.view')
                ->prefix('depenses')->name('expenses.')
                ->group(function () {
                    Volt::route('/', 'admin.finance.expenses.index')->name('index');
                });

            Route::middleware('can:payments.view')
                ->prefix('comptabilite')->name('comptabilite.')
                ->group(function () {
                    Volt::route('/', 'admin.finance.comptabilite.index')->name('index');
                });
        });

        // ── Communication ─────────────────────────────────────────────────────
        Route::middleware('can:announcements.view')
            ->prefix('annonces')->name('announcements.')
            ->group(function () {
                Volt::route('/',        'admin.announcements.index')->name('index');
                Volt::route('/nouveau', 'admin.announcements.create')->middleware('can:announcements.create')->name('create');
                Volt::route('/{uuid}',  'admin.announcements.show')->name('show');
            });

        Route::middleware('can:messages.view')
            ->prefix('messages')->name('messages.')
            ->group(function () {
                Volt::route('/',       'admin.messages.index')->name('index');
                Volt::route('/{uuid}', 'admin.messages.thread')->name('thread');
            });

        // ── Reports ───────────────────────────────────────────────────────────
        Volt::route('/rapports', 'admin.reports.index')
            ->middleware('can:reports.view')->name('reports.index');

        // ── Scheduled Tasks ────────────────────────────────────────────────────
        Route::middleware('can:scheduled-tasks.view')
            ->prefix('taches')->name('scheduled-tasks.')
            ->group(function () {
                Volt::route('/', 'admin.scheduled-tasks.index')->name('index');
            });

        // ── Settings ──────────────────────────────────────────────────────────
        Route::prefix('parametres')->name('settings.')->group(function () {
            Volt::route('/ecole',        'admin.settings.school')->middleware('can:settings.school.view')->name('school');
            Volt::route('/utilisateurs', 'admin.settings.users')->middleware('can:settings.users.view')->name('users');
            Volt::route('/roles',        'admin.settings.roles')->middleware('can:settings.roles.view')->name('roles');
            Volt::route('/appareils',    'admin.settings.device-tokens')->middleware('can:settings.users.view')->name('device-tokens');
            Volt::route('/facturation',  'admin.settings.billing-api')->middleware('can:billing.manage')->name('billing-api');
        });

        // ── Billing / D-Money transactions ────────────────────────────────────
        Route::middleware('can:billing.view')
            ->prefix('facturation')->name('billing.')
            ->group(function () {
                Volt::route('/', 'admin.billing.index')->name('index');
            });
    });

/*
|--------------------------------------------------------------------------
| Teacher Portal — /teacher
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:teacher|super-admin|admin', 'school.active'])
    ->prefix('teacher')->name('teacher.')
    ->group(function () {
        Volt::route('/',            'portals.teacher.dashboard')->name('dashboard');
        Volt::route('/emploi',      'portals.teacher.timetable')->name('timetable');
        Volt::route('/presences',   'portals.teacher.attendance')->name('attendance');
        Volt::route('/evaluations', 'portals.teacher.assessments')->name('assessments');
        Volt::route('/eleves',      'portals.teacher.students')->name('students');
        Volt::route('/messages',         'portals.teacher.messages')->name('messages');
        Volt::route('/messages/{uuid}',  'portals.teacher.message-thread')->name('messages.thread');
    });

/*
|--------------------------------------------------------------------------
| Monitor Portal — /monitor
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:monitor|super-admin|admin', 'school.active'])
    ->prefix('monitor')->name('monitor.')
    ->group(function () {
        Volt::route('/',          'portals.monitor.dashboard')->name('dashboard');
        Volt::route('/presences', 'portals.monitor.attendance')->name('attendance');
        Volt::route('/eleves',    'portals.monitor.students')->name('students');
        Volt::route('/planning',  'portals.monitor.schedule')->name('schedule');
    });

/*
|--------------------------------------------------------------------------
| Guardian Portal — /guardian
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:guardian|super-admin|admin', 'school.active'])
    ->prefix('guardian')->name('guardian.')
    ->group(function () {
        Volt::route('/',                      'portals.guardian.dashboard')->name('dashboard');
        Volt::route('/enfants',               'portals.guardian.children')->name('children');
        Volt::route('/presences',             'portals.guardian.attendance')->name('attendance');
        Volt::route('/notes',                 'portals.guardian.grades')->name('grades');
        Volt::route('/factures',              'portals.guardian.invoices')->name('invoices');
        Volt::route('/factures/{uuid}/print', 'portals.guardian.invoice-print')->name('invoices.print');
        Route::get('/paiement/succes',  [DMoneyPaymentController::class, 'success'])->name('dmoney.success');
        Route::get('/paiement/annule',  [DMoneyPaymentController::class, 'cancel'])->name('dmoney.cancel');
        Volt::route('/annonces',              'portals.guardian.announcements')->name('announcements');
        Volt::route('/messages',              'portals.guardian.messages')->name('messages');
        Volt::route('/messages/{uuid}',       'portals.guardian.message-thread')->name('messages.thread');
    });

/*
|--------------------------------------------------------------------------
| Student Portal — /student
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:student|super-admin|admin', 'school.active'])
    ->prefix('student')->name('student.')
    ->group(function () {
        Volt::route('/',          'portals.student.dashboard')->name('dashboard');
        Volt::route('/emploi',    'portals.student.timetable')->name('timetable');
        Volt::route('/notes',     'portals.student.grades')->name('grades');
        Volt::route('/presences', 'portals.student.attendance')->name('attendance');
        Volt::route('/annonces',  'portals.student.announcements')->name('announcements');
    });

/*
|--------------------------------------------------------------------------
| Caissier Portal — /caissier
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:caissier|super-admin|admin', 'school.active'])
    ->prefix('caissier')->name('caissier.')
    ->group(function () {
        Volt::route('/',         'portals.caissier.dashboard')->name('dashboard');
        Volt::route('/paiement', 'portals.caissier.payment')->name('payment');
        Volt::route('/factures', 'portals.caissier.invoices')->name('invoices');
        Volt::route('/rapport',  'portals.caissier.report')->name('report');
    });
