<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = session('locale')
            ?? ($request->user()?->ui_lang)
            ?? config('app.locale', 'fr');

        if (in_array($locale, ['fr', 'en', 'ar'])) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
