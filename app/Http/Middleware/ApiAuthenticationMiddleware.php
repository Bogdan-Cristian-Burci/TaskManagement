<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Auth\AuthenticationException;

class ApiAuthenticationMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        try {
            if (!$request->user()) {
                throw new AuthenticationException('Unauthenticated.');
            }

            return $next($request);
        } catch (AuthenticationException $e) {
            return response()->json([
                'error' => 'Unauthenticated',
                'message' => 'You must be logged in to access this resource.',
            ], 401);
        }
    }
}
