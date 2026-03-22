<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * POST /api/login
     *
     * Returns a Sanctum token and the portal redirect URL for the authenticated user.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        /** @var User $user */
        $user = Auth::user();

        if ($user->is_blocked) {
            Auth::logout();
            throw ValidationException::withMessages([
                'email' => [__('auth.blocked')],
            ]);
        }

        // For non-super-admins: validate school exists, is active, and has a valid subscription
        if (! $user->hasRole('super-admin')) {
            $school = $user->school;

            if (! $school) {
                Auth::logout();
                throw ValidationException::withMessages([
                    'email' => [__('auth.no_school')],
                ]);
            }

            if (! $school->is_active) {
                Auth::logout();
                return response()->json([
                    'message'      => __('auth.school_suspended'),
                    'redirect_url' => route('school.suspended'),
                ], 403);
            }

            if (! $school->hasValidSubscription()) {
                Auth::logout();
                return response()->json([
                    'message'      => __('auth.school_expired'),
                    'redirect_url' => route('school.expired'),
                ], 403);
            }
        }

        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        // Revoke all previous tokens for this device name to avoid accumulation
        $user->tokens()->where('name', 'api')->delete();

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'token'        => $token,
            'token_type'   => 'Bearer',
            'redirect_url' => $user->portalRoute(),
            'user'         => [
                'id'         => $user->id,
                'uuid'       => $user->uuid,
                'name'       => $user->name,
                'email'      => $user->email,
                'avatar_url' => $user->avatar_url,
                'role'       => $user->getRoleNames()->first(),
            ],
        ]);
    }

    /**
     * POST /api/logout
     *
     * Revokes the current access token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => __('auth.logged_out')]);
    }

    /**
     * GET /api/me
     *
     * Returns the authenticated user's profile and portal URL.
     */
    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'user' => [
                'id'         => $user->id,
                'uuid'       => $user->uuid,
                'name'       => $user->name,
                'email'      => $user->email,
                'avatar_url' => $user->avatar_url,
                'role'       => $user->getRoleNames()->first(),
            ],
            'redirect_url' => $user->portalRoute(),
        ]);
    }
}
