<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSchoolIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Super-admin bypasses all school checks (platform-level access)
        if ($user?->hasRole('super-admin')) {
            return $next($request);
        }

        // Individual user must not be blocked
        if ($user?->is_blocked) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('login')
                ->withErrors(['email' => __('auth.blocked')]);
        }

        // User must belong to a school
        $school = $user?->school;
        if (! $school) {
            auth()->logout();
            return redirect()->route('login')->withErrors(['email' => __('auth.no_school')]);
        }

        // School must be active (not suspended by platform admin)
        if (! $school->is_active) {
            auth()->logout();
            return redirect()->route('school.suspended')->with('school', $school);
        }

        // Subscription must be valid
        if (! $school->hasValidSubscription()) {
            return redirect()->route('school.expired')->with('school', $school);
        }

        return $next($request);
    }
}
