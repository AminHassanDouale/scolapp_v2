<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // ── Global web middleware ──────────────────────────────────────────────
        $middleware->web(append: [
            \App\Http\Middleware\SetLocale::class,
        ]);

        // ── Middleware aliases ─────────────────────────────────────────────────
        $middleware->alias([
            'role'               => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission'         => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'school.active'      => \App\Http\Middleware\EnsureSchoolIsActive::class,
        ]);

        // ── Authenticated users visiting guest routes → portal redirect ────────
        $middleware->redirectUsersTo(function () {
            $user = auth()->user();
            if (! $user) return route('login');
            return $user->portalRoute();
        });

        // ── Unauthenticated users → login ──────────────────────────────────────
        $middleware->redirectGuestsTo(fn () => route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
