<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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
            // Skip CSRF check for token refresh endpoint
            $isRefreshEndpoint = $request->route() && $request->route()->uri() === 'api/refresh-token';
            
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
                // Return a specific response for expired tokens
                return response()->json([
                    'error' => 'token_expired',
                    'message' => 'Your session has expired. Please log in again.',
                    'status_code' => Response::HTTP_UNAUTHORIZED
                ], Response::HTTP_UNAUTHORIZED);
            }
            
            // CSRF protection check (except for refresh token endpoint)
            if (!$isRefreshEndpoint && !$this->validateCsrf($request)) {
                Log::warning('CSRF token validation failed', [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'path' => $request->path(),
                    'method' => $request->method(),
                    'user_id' => $request->user()->id ?? null
                ]);
                
                return response()->json([
                    'error' => 'csrf_error',
                    'message' => 'CSRF token validation failed',
                    'status_code' => Response::HTTP_FORBIDDEN
                ], Response::HTTP_FORBIDDEN);
            }

            // If we reach here, authentication passed
            $response = $next($request);
            
            // For successful responses, add a new CSRF token in the header
            if ($response instanceof \Illuminate\Http\JsonResponse && $response->getStatusCode() < 400) {
                $csrfToken = $this->generateCsrfToken();
                $response->headers->set('X-CSRF-TOKEN', $csrfToken);
            }
            
            return $response;

        } catch (AuthenticationException $e) {
            return response()->json([
                'error' => 'unauthenticated',
                'message' => 'You must be logged in to access this resource.',
                'status_code' => Response::HTTP_UNAUTHORIZED
            ], Response::HTTP_UNAUTHORIZED);
        }
    }
    
    /**
     * Validate the CSRF token in the request
     *
     * @param Request $request
     * @return bool
     */
    protected function validateCsrf(Request $request): bool
    {
        // Skip CSRF validation for GET requests or for refresh token endpoint
        if ($request->isMethod('GET')) {
            return true;
        }
        
        $token = $request->header('X-CSRF-TOKEN');
        
        // For API requests, token must be present in header
        if (!$token) {
            return false;
        }
        
        // In a real implementation, you would validate the token against a session or DB
        // For now, we'll implement a simplified validation
        // The token should be a hash of the user ID and a secret
        // In a production environment, consider using Laravel's built-in CSRF protection
        // or implementing a more robust token validation mechanism
        
        // For this implementation, we're just checking that the token exists
        // and is a proper format. In a real implementation, you'd check it against
        // a stored token.
        return Str::length($token) >= 32;
    }
    
    /**
     * Generate a new CSRF token
     *
     * @return string
     */
    protected function generateCsrfToken(): string
    {
        // Generate a new CSRF token
        // In a production environment, you'd store this token in a session or DB
        return Str::random(40);
    }
}
