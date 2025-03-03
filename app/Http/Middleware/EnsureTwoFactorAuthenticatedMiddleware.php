<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorAuthenticatedMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @param string $authenticateWhen Type can be "enabled" (default), "required", or "optional"
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string $authenticateWhen = 'enabled')
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated',
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Check 2FA status based on the authenticateWhen parameter
        switch ($authenticateWhen) {
            case 'required':
                // 2FA is required for all users regardless of whether they've enabled it
                if (!$user->hasTwoFactorEnabled()) {
                    return response()->json([
                        'message' => 'Two-factor authentication is required',
                        'two_factor_required' => true
                    ], Response::HTTP_FORBIDDEN);
                }
                break;

            case 'enabled':
                // Only check 2FA if the user has enabled it
                if ($user->hasTwoFactorEnabled() && !$request->session()->has('two_factor_authenticated')) {
                    return response()->json([
                        'message' => 'Two-factor authentication required',
                        'two_factor_required' => true
                    ], Response::HTTP_FORBIDDEN);
                }
                break;

            case 'optional':
                // Don't enforce 2FA, just pass through
                break;

            default:
                // Unknown mode, enforce enabled mode by default
                if ($user->hasTwoFactorEnabled() && !$request->session()->has('two_factor_authenticated')) {
                    return response()->json([
                        'message' => 'Two-factor authentication required',
                        'two_factor_required' => true
                    ], Response::HTTP_FORBIDDEN);
                }
        }

        return $next($request);
    }
}
