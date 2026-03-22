<?php

namespace App\Providers;

use App\Services\AttendanceService;
use App\Services\EnrollmentService;
use App\Services\InvoiceService;
use App\Services\ReportCardService;
use App\Actions\ConfirmEnrollmentAction;
use App\Actions\CreateEnrollmentAction;
use App\Actions\RecordPaymentAction;
use App\Actions\GenerateReportCardAction;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind services
        $this->app->singleton(InvoiceService::class);
        $this->app->singleton(AttendanceService::class);
        $this->app->singleton(ReportCardService::class);

        $this->app->singleton(EnrollmentService::class, function ($app) {
            return new EnrollmentService(
                $app->make(CreateEnrollmentAction::class),
                $app->make(ConfirmEnrollmentAction::class),
                $app->make(InvoiceService::class),
            );
        });

        $this->app->singleton(RecordPaymentAction::class, function ($app) {
            return new RecordPaymentAction($app->make(InvoiceService::class));
        });

        $this->app->singleton(GenerateReportCardAction::class, function ($app) {
            return new GenerateReportCardAction($app->make(ReportCardService::class));
        });
    }

    public function boot(): void
    {
        // Set locale from session or authenticated user preference
        $this->app['events']->listen('Illuminate\Auth\Events\Authenticated', function ($event) {
            $user = $event->user;
            if ($user->ui_lang && in_array($user->ui_lang, ['fr', 'en', 'ar'])) {
                app()->setLocale($user->ui_lang);
                session(['locale' => $user->ui_lang]);
            }
        });

        // Enforce HTTPS in production
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // Super-admin bypasses all gates
        Gate::before(function ($user, $ability) {
            return $user->hasRole('super-admin') ? true : null;
        });
    }
}
