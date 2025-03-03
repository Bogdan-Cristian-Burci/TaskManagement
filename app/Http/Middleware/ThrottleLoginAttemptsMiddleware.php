<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;
use Symfony\Component\HttpFoundation\Response;

class ThrottleLoginAttemptsMiddleware
{
    /**
     * The rate limiter instance.
     *
     * @var RateLimiter
     */
    protected $limiter;

    /**
     * Create a new middleware instance.
     *
     * @param RateLimiter $limiter
     * @return void
     */
    public function __construct(RateLimiter $limiter)
    {
        $this->limiter = $limiter;
    }

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @param int $maxAttempts
     * @param int $decayMinutes
     * @return mixed
     */
    public function handle(Request $request, Closure $next, int $maxAttempts = 5, int $decayMinutes = 1)
    {
        $key = $this->resolveRequestSignature($request);

        if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
            return $this->buildResponse($key, $maxAttempts);
        }

        $this->limiter->hit($key, $decayMinutes * 60);

        $response = $next($request);

        // Check if the response indicates a successful login
        $statusCode = $response->getStatusCode();

        // If login was successful (HTTP 200 OK), clear the rate limiter for this key
        if ($statusCode === Response::HTTP_OK) {
            $this->limiter->clear($key);
        }

        return $response;
    }

    /**
     * Resolve request signature.
     *
     * @param Request $request
     * @return string
     */
    protected function resolveRequestSignature(Request $request): string
    {
        // Use email and IP address as the key
        return sha1($request->input('email') . '|' . $request->ip());
    }

    /**
     * Create a response indicating too many login attempts.
     *
     * @param string $key
     * @param int $maxAttempts
     * @return JsonResponse
     */
    protected function buildResponse(string $key, int $maxAttempts)
    {
        $retryAfter = $this->limiter->availableIn($key);

        $message = Lang::get('auth.throttle', [
            'seconds' => $retryAfter,
            'minutes' => ceil($retryAfter / 60),
        ]);

        return response()->json([
            'message' => $message,
            'retry_after' => $retryAfter,
        ], Response::HTTP_TOO_MANY_REQUESTS);
    }
}
