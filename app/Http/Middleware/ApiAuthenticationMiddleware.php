<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ApiAuthenticationMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        try {
            // Check if user is authenticated
            if (!$request->user()) {
                // Log authentication failure
                Log::warning('API authentication failure', [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'path' => $request->path(),
                    'method' => $request->method()
                ]);

                throw new AuthenticationException('Unauthenticated.');
            }

            // Check if token is still valid (not expired or revoked)
            $token = $request->user()->token();
            if ($token && ($token->revoked || ($token->expires_at && $token->expires_at < now()))) {
                throw new AuthenticationException('Token expired or revoked.');
            }

            // If we reach here, authentication passed
            return $next($request);

        } catch (AuthenticationException $e) {
            return response()->json([
                'error' => 'Unauthenticated',
                'message' => 'You must be logged in to access this resource.',
                'status_code' => Response::HTTP_UNAUTHORIZED
            ], Response::HTTP_UNAUTHORIZED);
        }
    }
}
